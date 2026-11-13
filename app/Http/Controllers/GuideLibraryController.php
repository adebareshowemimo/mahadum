<?php

namespace App\Http\Controllers;

use App\Models\GuideFaq;
use App\Models\GuideVideo;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuideLibraryController extends Controller
{
    private function authorizeEditor(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['super_admin', 'content_owner']), 403);
    }

    public function videos(Request $request): JsonResponse
    {
        $this->authorizeEditor($request);
        $filters = $request->validate([
            'section' => ['nullable', Rule::in(['how_to', 'sample'])],
            'search' => ['nullable', 'string', 'max:100'],
            'visibility' => ['nullable', Rule::in(['visible', 'hidden'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = GuideVideo::query()->orderBy('sort_order')->orderBy('id');
        if (! empty($filters['section'])) {
            $query->where('section', $filters['section']);
        }
        if (! empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$filters['search'].'%')->orWhere('description', 'like', '%'.$filters['search'].'%'));
        }
        if (! empty($filters['visibility'])) {
            $query->where('is_visible', $filters['visibility'] === 'visible');
        }

        return response()->json($query->paginate(12)->through(fn (GuideVideo $video) => $this->videoData($video)));
    }

    public function saveVideo(Request $request, AuditLogger $audit, ?GuideVideo $video = null): JsonResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'section' => ['required', Rule::in(['how_to', 'sample'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'language' => ['required_if:section,sample', 'nullable', Rule::in(['yoruba', 'igbo', 'hausa', 'english'])],
            'topic' => ['nullable', Rule::in(['getting_started', 'parent_guide', 'learning', 'billing', 'schools'])],
            'source_type' => ['required', Rule::in(['youtube', 'upload'])],
            'youtube_url' => ['nullable', 'string', 'max:500'],
            'file' => ['nullable', 'file', 'mimes:mp4,webm,mov', 'max:102400'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_visible' => ['required', 'boolean'],
        ]);
        if ($data['source_type'] === 'youtube') {
            $id = $this->youtubeId((string) ($data['youtube_url'] ?? ''));
            if (! $id) {
                throw ValidationException::withMessages(['youtube_url' => 'Enter a valid YouTube video URL.']);
            }
            $data['youtube_id'] = $id;
            $data['file_path'] = null;
        } else {
            if (! $request->hasFile('file') && ! ($video && $video->source_type === 'upload' && $video->file_path)) {
                throw ValidationException::withMessages(['file' => 'Choose a video file to upload.']);
            }
            $data['youtube_id'] = null;
            $data['file_path'] = $video?->source_type === 'upload' ? $video->file_path : null;
        }
        unset($data['youtube_url'], $data['file']);
        $oldPath = $video?->file_path;
        if ($request->hasFile('file') && $data['source_type'] === 'upload') {
            $data['file_path'] = $request->file('file')->store('guide-videos', 'public');
        }
        try {
            $before = $video?->toArray() ?? [];
            $video = DB::transaction(function () use ($video, $data, $audit, $before) {
                if ($video) {
                    $video->update($data);
                } else {
                    $video = GuideVideo::create($data);
                }
                $audit->record($before ? 'guide.video.updated' : 'guide.video.created', $video, $before, $data);

                return $video;
            });
        } catch (\Throwable $error) {
            if ($data['file_path'] && $data['file_path'] !== $oldPath) {
                Storage::disk('public')->delete($data['file_path']);
            }
            throw $error;
        }
        if ($oldPath && $oldPath !== $video->file_path) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json(['data' => $this->videoData($video)], $before ? 200 : 201);
    }

    public function deleteVideo(Request $request, GuideVideo $video, AuditLogger $audit): JsonResponse
    {
        $this->authorizeEditor($request);
        $before = $video->toArray();
        $path = $video->file_path;
        DB::transaction(function () use ($video, $audit, $before) {
            $video->delete();
            $audit->record('guide.video.deleted', $video, $before, []);
        });
        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['message' => 'Video removed.']);
    }

    public function faqs(Request $request): JsonResponse
    {
        $this->authorizeEditor($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = GuideFaq::query()->orderBy('sort_order')->orderBy('id');
        if (! empty($filters['search'])) {
            $query->where(fn ($q) => $q->where('question', 'like', '%'.$filters['search'].'%')->orWhere('answer', 'like', '%'.$filters['search'].'%'));
        }

        return response()->json($query->paginate(20));
    }

    public function saveFaq(Request $request, AuditLogger $audit, ?GuideFaq $faq = null): JsonResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'], 'answer' => ['required', 'string', 'max:3000'],
            'sort_order' => ['required', 'integer', 'min:0'], 'is_visible' => ['required', 'boolean'],
        ]);
        $before = $faq?->toArray() ?? [];
        $faq = DB::transaction(function () use ($faq, $data, $audit, $before) {
            if ($faq) {
                $faq->update($data);
            } else {
                $faq = GuideFaq::create($data);
            }
            $audit->record($before ? 'guide.faq.updated' : 'guide.faq.created', $faq, $before, $data);

            return $faq;
        });

        return response()->json(['data' => $faq], $before ? 200 : 201);
    }

    public function deleteFaq(Request $request, GuideFaq $faq, AuditLogger $audit): JsonResponse
    {
        $this->authorizeEditor($request);
        $before = $faq->toArray();
        DB::transaction(function () use ($faq, $audit, $before) {
            $faq->delete();
            $audit->record('guide.faq.deleted', $faq, $before, []);
        });

        return response()->json(['message' => 'Question removed.']);
    }

    private function youtubeId(string $url): ?string
    {
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https') {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');
        if ($host === 'youtu.be') {
            $id = $path;
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $id = $path === 'watch' ? ($query['v'] ?? '') : (preg_match('~^(?:shorts|embed)/([A-Za-z0-9_-]{11})$~', $path, $matches) ? $matches[1] : '');
        } else {
            return null;
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) ? $id : null;
    }

    private function videoData(GuideVideo $video): array
    {
        return $video->toArray() + ['file_url' => $video->file_path ? Storage::disk('public')->url($video->file_path) : null];
    }
}
