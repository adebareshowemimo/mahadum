<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdvertPlacementRequest;
use App\Http\Requests\Admin\UpdateAdvertPlacementRequest;
use App\Models\AdvertPlacement;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertPlacementController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = AdvertPlacement::query()->with('mediaAsset')->latest();

        if ($position = $request->query('position')) {
            $query->where('position', $position);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $page = $query->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (AdvertPlacement $a) => $this->present($a)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(AdvertPlacement $advertPlacement): JsonResponse
    {
        return response()->json(['data' => $this->present($advertPlacement->load('mediaAsset'))]);
    }

    public function store(StoreAdvertPlacementRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['is_active'])) {
            $data['activated_at'] = now();
        }

        $advertPlacement = AdvertPlacement::create($data);

        $this->audit->record(
            'advert.created',
            $advertPlacement,
            [],
            ['name' => $advertPlacement->name, 'position' => $advertPlacement->position, 'is_active' => $advertPlacement->is_active],
        );

        return response()->json(['data' => $this->present($advertPlacement->load('mediaAsset'))], 201);
    }

    public function update(UpdateAdvertPlacementRequest $request, AdvertPlacement $advertPlacement): JsonResponse
    {
        $before = $advertPlacement->only(['name', 'position', 'size', 'media_asset_id', 'target_url', 'is_active', 'starts_at', 'ends_at']);

        $data = $request->validated();
        if (array_key_exists('is_active', $data) && $data['is_active'] && ! $advertPlacement->is_active) {
            $data['activated_at'] = now();
        }

        $advertPlacement->update($data);

        $this->audit->record(
            'advert.updated',
            $advertPlacement,
            $before,
            $advertPlacement->only(array_keys($before)),
        );

        return response()->json(['data' => $this->present($advertPlacement->load('mediaAsset'))]);
    }

    /**
     * Flip is_active. Refreshes activated_at whenever the flip turns it on, so
     * AdvertPlacement::currentFor()'s "most recently activated wins" rule stays
     * meaningful.
     */
    public function toggleActive(AdvertPlacement $advertPlacement): JsonResponse
    {
        $before = $advertPlacement->is_active;
        $advertPlacement->is_active = ! $advertPlacement->is_active;
        if ($advertPlacement->is_active) {
            $advertPlacement->activated_at = now();
        }
        $advertPlacement->save();

        $this->audit->record(
            'advert.toggled',
            $advertPlacement,
            ['is_active' => $before],
            ['is_active' => $advertPlacement->is_active],
        );

        return response()->json(['data' => $this->present($advertPlacement->load('mediaAsset'))]);
    }

    public function destroy(AdvertPlacement $advertPlacement): JsonResponse
    {
        $this->audit->record(
            'advert.deleted',
            $advertPlacement,
            ['name' => $advertPlacement->name, 'position' => $advertPlacement->position],
            [],
        );

        $advertPlacement->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AdvertPlacement $advertPlacement): array
    {
        return [
            'id' => $advertPlacement->id,
            'name' => $advertPlacement->name,
            'position' => $advertPlacement->position,
            'size' => $advertPlacement->size,
            'media_asset_id' => $advertPlacement->media_asset_id,
            'image_url' => $this->resolveUrl((string) $advertPlacement->mediaAsset->url),
            'target_url' => $advertPlacement->target_url,
            'is_active' => $advertPlacement->is_active,
            'activated_at' => $advertPlacement->activated_at?->toIso8601String(),
            'starts_at' => $advertPlacement->starts_at?->toIso8601String(),
            'ends_at' => $advertPlacement->ends_at?->toIso8601String(),
            'impressions_count' => (int) $advertPlacement->impressions_count,
            'clicks_count' => (int) $advertPlacement->clicks_count,
            'created_at' => $advertPlacement->created_at?->toIso8601String(),
        ];
    }

    /** Absolute URL for a stored path (already-absolute URLs pass through). */
    private function resolveUrl(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : url('storage/'.ltrim($url, '/'));
    }
}
