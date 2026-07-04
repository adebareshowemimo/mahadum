<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\UploadMediaRequest;
use App\Models\MediaAsset;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Every table/column that can hold a media_assets FK. An asset referenced by
     * none of these is an orphan (safe to delete). Keep in sync when a new
     * media-referencing column is added.
     *
     * @var array<string, array<int, string>>
     */
    private const REFERENCES = [
        'videos' => ['source_asset_id', 'poster_asset_id'],
        'questions' => ['prompt_audio_asset_id', 'prompt_image_asset_id'],
        'question_options' => ['media_asset_id'],
        'speaking_prompts' => ['target_audio_asset_id'],
        'flashcards' => ['image_asset_id', 'audio_asset_id'],
        'cultural_contents' => ['media_asset_id'],
        'speaking_submissions' => ['audio_asset_id'],
        'assignment_submissions' => ['media_asset_id'],
        'chore_submissions' => ['evidence_media_id'],
        'invoices' => ['pdf_asset_id'],
        'competition_entries' => ['media_asset_id'],
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * Paginated media library. Filterable by type and searchable by original
     * filename so it scales past a handful of assets (the old latest-100 cap
     * hid everything else). `per_page` is clamped to keep payloads sane.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 24), 1), 100);

        $query = MediaAsset::query()->latest();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('original_name', 'like', "%{$q}%");
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (MediaAsset $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'url' => $this->resolveUrl($a->url),
                'original_name' => $a->getAttribute('original_name'),
                'created_at' => $a->created_at,
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** Delete an asset and its local file (FK nulls any video that used it). */
    public function destroy(MediaAsset $asset): JsonResponse
    {
        if (! str_starts_with((string) $asset->url, 'http')) {
            Storage::disk('public')->delete($asset->url);
        }
        $asset->delete();

        return response()->json(null, 204);
    }

    /**
     * Orphaned assets: MediaAssets referenced by no content, submission, or invoice.
     * Paginated + filterable like the library, plus the full orphan count so the UI
     * can offer a "clean up" affordance without loading everything.
     */
    public function orphans(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 24), 1), 100);
        $referenced = $this->referencedAssetIds();

        $query = MediaAsset::query()->whereNotIn('id', $referenced ?: [0])->latest();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('original_name', 'like', "%{$q}%");
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (MediaAsset $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'url' => $this->resolveUrl($a->url),
                'original_name' => $a->getAttribute('original_name'),
                'created_at' => $a->created_at,
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Bulk-delete orphaned assets. Each id is re-checked against live references
     * (defensive: an asset may have been linked since the list was fetched) and
     * only genuine orphans are removed. Returns the deleted + skipped counts.
     */
    public function purgeOrphans(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $referenced = $this->referencedAssetIds();
        $deletable = array_values(array_diff($validated['ids'], $referenced));

        $deleted = 0;
        foreach (MediaAsset::whereIn('id', $deletable)->get() as $asset) {
            if (! str_starts_with((string) $asset->url, 'http')) {
                Storage::disk('public')->delete($asset->url);
            }
            $asset->delete();
            $deleted++;
        }

        $skipped = count($validated['ids']) - $deleted;

        $this->audit->record('media.orphans_purged', null, [], ['deleted' => $deleted, 'skipped' => $skipped]);

        return response()->json(['data' => ['deleted' => $deleted, 'skipped' => $skipped]]);
    }

    /**
     * Distinct media_assets ids referenced anywhere in the app (union across every
     * FK column in self::REFERENCES).
     *
     * @return array<int, int>
     */
    private function referencedAssetIds(): array
    {
        $ids = [];
        foreach (self::REFERENCES as $table => $columns) {
            foreach ($columns as $column) {
                $ids[] = DB::table($table)->whereNotNull($column)->distinct()->pluck($column)->all();
            }
        }

        return array_values(array_unique(array_map('intval', array_merge(...$ids))));
    }

    /** Absolute URL for a stored path (already-absolute URLs pass through). */
    private function resolveUrl(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : url('storage/'.ltrim($url, '/'));
    }

    /**
     * Store an uploaded file on the local `public` disk and record a MediaAsset.
     * Returns an absolute URL (request-host based) so it works behind the dev
     * proxy and in production. Swap this for a managed video vendor later — the
     * MediaAsset contract (id/type/url) stays the same.
     */
    public function upload(UploadMediaRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store('media', 'public'); // storage/app/public/media/...

        $group = explode('/', (string) $file->getMimeType())[0]; // video|audio|image
        $type = in_array($group, ['video', 'audio', 'image'], true) ? $group : 'file';

        $asset = MediaAsset::create([
            'type' => $type,
            'url' => $path,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['data' => [
            'id' => $asset->id,
            'type' => $asset->type,
            'url' => url('storage/'.$path),
            'original_name' => $asset->original_name,
        ]], 201);
    }
}
