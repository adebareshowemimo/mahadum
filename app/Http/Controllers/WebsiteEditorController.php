<?php

namespace App\Http\Controllers;

use App\Models\PublicDocument;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebsiteEditorController extends Controller
{
    private function authorizeEditor(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['super_admin', 'content_owner']), 403);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeEditor($request);

        return response()->json(['data' => PublicDocument::query()->orderBy('kind')->orderBy('path')->get()]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $this->authorizeEditor($request);
        $data = $this->validated($request);
        if ($data['published']) {
            $request->validate(['heading' => ['required'], 'intro' => ['required'], 'body' => ['required'], 'author' => ['required'], 'content.language' => ['required']]);
        }
        $request->validate(['path' => ['required', 'regex:~^/guides/[a-z0-9]+(?:-[a-z0-9]+)*$~', 'max:255', Rule::unique('public_documents', 'path')]]);
        $data['kind'] = 'guide';
        $data['path'] = $request->input('path');
        $document = DB::transaction(function () use ($data, $audit) {
            $document = PublicDocument::create($data);
            $audit->record('website.document.created', $document, [], $data);

            return $document;
        });

        return response()->json(['data' => $document], 201);
    }

    public function update(Request $request, PublicDocument $document, AuditLogger $audit): JsonResponse
    {
        $this->authorizeEditor($request);
        $data = $this->validated($request);
        if ($document->kind === 'guide' && $data['published']) {
            $request->validate(['heading' => ['required'], 'intro' => ['required'], 'body' => ['required'], 'author' => ['required'], 'content.language' => ['required']]);
        }
        if ($document->kind !== 'guide') {
            $request->validate(['published' => ['accepted']]);
        }
        if ($document->published_at && $data['published']) {
            $data['published_at'] = $document->published_at;
        }
        DB::transaction(function () use ($document, $data, $audit) {
            $before = $document->only(array_keys($data));
            $document->update($data);
            $audit->record('website.document.updated', $document, $before, $data);
        });

        return response()->json(['data' => $document->fresh()]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:60'], 'description' => ['required', 'string', 'max:155'],
            'heading' => ['nullable', 'string', 'max:255'], 'intro' => ['nullable', 'string', 'max:3000'],
            'body' => ['nullable', 'string', 'max:30000'], 'author' => ['nullable', 'string', 'max:255'],
            'published' => ['required', 'boolean'], 'content' => ['required', 'array:language,phrases,faqs,videos,levels,playlist_url'],
            'content.language' => ['nullable', Rule::in(['igbo', 'yoruba', 'hausa'])],
            'content.playlist_url' => ['nullable', 'url:https', 'regex:~^https://(?:www\.)?youtube\.com/playlist\?list=[a-zA-Z0-9_-]+$~'],
            'content.phrases' => ['present', 'array', 'max:30'],
            'content.phrases.*' => ['array:text,meaning,pronunciation,audio_url'],
            'content.phrases.*.text' => ['required', 'string', 'max:255'],
            'content.phrases.*.meaning' => ['required', 'string', 'max:255'],
            'content.phrases.*.pronunciation' => ['required', 'string', 'max:255'],
            'content.phrases.*.audio_url' => ['required', 'url:https', 'max:1000'],
            'content.faqs' => ['present', 'array', 'max:20'],
            'content.faqs.*' => ['array:question,answer'],
            'content.faqs.*.question' => ['required', 'string', 'max:255'],
            'content.faqs.*.answer' => ['required', 'string', 'max:3000'],
            'content.levels' => ['present', 'array', 'max:30'],
            'content.levels.*' => ['array:title,description'],
            'content.levels.*.title' => ['required', 'string', 'max:255'],
            'content.levels.*.description' => ['required', 'string', 'max:1000'],
            'content.videos' => ['present', 'array', 'max:10'],
            'content.videos.*' => ['array:youtube_id,title,description,upload_date'],
            'content.videos.*.youtube_id' => ['required', 'regex:/^[a-zA-Z0-9_-]{11}$/D'],
            'content.videos.*.title' => ['required', 'string', 'max:255'],
            'content.videos.*.description' => ['required', 'string', 'max:1000'],
            'content.videos.*.upload_date' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $data['published_at'] = $data['published'] ? now() : null;

        return $data;
    }

    public function reports(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        $votes = DB::table('language_votes')->select('language', 'other_language', DB::raw('COUNT(*) as total'))->groupBy('language', 'other_language')->orderBy('language')->get();
        $sources = DB::table('discovery_events')->join('users', 'users.id', '=', 'discovery_events.user_id')
            ->select('users.utm_source', 'users.utm_medium', 'users.utm_campaign', 'discovery_events.name', DB::raw('COUNT(*) as total'))
            ->groupBy('users.utm_source', 'users.utm_medium', 'users.utm_campaign', 'discovery_events.name')->get();

        return response()->json(['data' => ['votes' => $votes, 'sources' => $sources]]);
    }

    public function votesCsv(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Language', 'Other language', 'Votes'], escape: '');
            foreach (DB::table('language_votes')->select('language', 'other_language', DB::raw('COUNT(*) as total'))->groupBy('language', 'other_language')->orderBy('language')->get() as $row) {
                $other = (string) $row->other_language;
                if (preg_match('/^[=+@\-\t\r]/', $other)) {
                    $other = "'".$other;
                }
                fputcsv($handle, [$row->language, $other, $row->total], escape: '');
            }
            fclose($handle);
        }, 'language-votes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
