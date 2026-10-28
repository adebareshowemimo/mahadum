<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class StoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Story::with('course.language')->withCount('pages')->latest();
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->integer('course_id'));
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.trim((string) $request->query('q')).'%');
        }

        $page = $query->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (Story $story) => $this->summary($story))->values(),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(), 'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $this->validated($request, true);
        $course = Course::findOrFail($data['course_id']);
        Gate::authorize('update', $course);
        $story = Story::create($data + ['price_minor' => Story::PRICE_MINOR]);
        $audit->record('story.created', $story, [], $story->only(['title', 'course_id', 'age_band', 'format']));

        return response()->json(['data' => $this->detail($story->load('course.language', 'pages'))], 201);
    }

    public function show(Story $story): JsonResponse
    {
        Gate::authorize('view', $story->course);

        return response()->json(['data' => $this->detail($story->load('course.language', 'pages'))]);
    }

    /** Signed media links let an author preview private pages and video in a browser tab. */
    public function asset(Request $request, Story $story)
    {
        abort_unless($request->hasValidSignature(false), 403);
        $viewer = User::findOrFail($request->integer('viewer'));
        abort_unless($viewer->can('content.courses.view'), 403);
        Gate::forUser($viewer)->authorize('view', $story->course);
        $path = match ($request->query('slot')) {
            'preview' => $story->preview_video_path,
            'full' => $story->full_video_path,
            'page' => $story->pages()->find($request->integer('page'))?->image_path,
            default => null,
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store']);
    }

    public function update(Request $request, Story $story, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('update', $story->course);
        $before = $story->only(['title', 'summary', 'age_band', 'format']);
        $data = $this->validated($request, false);
        if ($story->published_at !== null) {
            abort_if(($data['age_band'] ?? $story->age_band) !== $story->age_band
                || ($data['format'] ?? $story->format) !== $story->format,
                409, 'Unpublish this story before changing its age band or format.');
        }
        $story->update($data);
        $audit->record('story.updated', $story, $before, $story->only(array_keys($before)));

        return response()->json(['data' => $this->detail($story->load('course.language', 'pages'))]);
    }

    public function publish(Story $story, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('update', $story->course);
        $ready = match ($story->format) {
            'animated' => (bool) ($story->preview_video_path && $story->full_video_path),
            'colouring', 'tracing' => $story->pages()->count() > 12
                && ! $story->pages()->whereNull('image_path')->exists(),
            default => $story->pages()->count() > 1
                && ! $story->pages()->where(fn ($query) => $query->whereNull('body')->orWhere('body', ''))->exists(),
        };
        abort_unless($ready, 422, 'Add the complete story and its free preview before publishing.');
        $story->update(['published_at' => now()]);
        $audit->record('story.published', $story);

        return response()->json(['data' => $this->detail($story->load('course.language', 'pages'))]);
    }

    public function unpublish(Story $story, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('update', $story->course);
        $story->update(['published_at' => null]);
        $audit->record('story.unpublished', $story);

        return response()->json(['data' => $this->detail($story->load('course.language', 'pages'))]);
    }

    public function addPage(Request $request, Story $story): JsonResponse
    {
        Gate::authorize('update', $story->course);
        abort_if($story->published_at !== null, 409, 'Unpublish this story before adding pages.');
        $data = $request->validate(['title' => ['nullable', 'string', 'max:255'], 'body' => ['nullable', 'string']]);
        $page = $story->pages()->create($data + ['position' => ($story->pages()->max('position') ?? 0) + 1]);

        return response()->json(['data' => $this->page($page)], 201);
    }

    public function updatePage(Request $request, Story $story, StoryPage $page): JsonResponse
    {
        Gate::authorize('update', $story->course);
        abort_unless($page->story_id === $story->id, 404);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:255'], 'body' => ['nullable', 'string']]);
        if ($story->published_at !== null && $story->format === 'reading' && array_key_exists('body', $data)) {
            abort_if(trim((string) $data['body']) === '', 422, 'Published chapters need text.');
        }
        $page->update($data);

        return response()->json(['data' => $this->page($page)]);
    }

    public function deletePage(Story $story, StoryPage $page): JsonResponse
    {
        Gate::authorize('update', $story->course);
        abort_if($story->published_at !== null, 409, 'Unpublish this story before deleting pages.');
        abort_unless($page->story_id === $story->id, 404);
        if ($page->image_path) {
            Storage::disk('local')->delete($page->image_path);
        }
        $page->delete();

        return response()->json(null, 204);
    }

    public function upload(Request $request, Story $story): JsonResponse
    {
        Gate::authorize('update', $story->course);
        $data = $request->validate([
            'slot' => ['required', Rule::in(['cover', 'preview_video', 'full_video', 'page_image'])],
            'page_id' => ['required_if:slot,page_image', 'nullable', 'integer', 'exists:story_pages,id'],
            'file' => ['required', 'file', 'max:102400'],
        ]);
        $slot = $data['slot'];
        $image = in_array($slot, ['cover', 'page_image'], true);
        abort_unless($request->file('file')->isValid(), 422);
        abort_unless($image
            ? str_starts_with((string) $request->file('file')->getMimeType(), 'image/')
            : str_starts_with((string) $request->file('file')->getMimeType(), 'video/'),
            422, 'Choose the correct image or video file.');
        $page = null;
        if ($slot === 'page_image') {
            $page = StoryPage::where('story_id', $story->id)->findOrFail($data['page_id']);
        }
        $disk = $slot === 'cover' ? 'public' : 'local';
        $path = $request->file('file')->store('stories/'.$story->id, $disk);
        $field = match ($slot) {
            'cover' => 'cover_path', 'preview_video' => 'preview_video_path',
            'full_video' => 'full_video_path', default => 'image_path',
        };
        $target = $page ?? $story;
        $old = $target->{$field};
        $target->update([$field => $path]);
        if ($old) {
            Storage::disk($disk)->delete($old);
        }

        return response()->json(['data' => ['path' => $path]]);
    }

    public function destroy(Story $story): JsonResponse
    {
        Gate::authorize('update', $story->course);
        abort_if($story->purchases()->exists(), 409, 'Unpublish stories with payment attempts instead of deleting them.');
        foreach ([$story->preview_video_path, $story->full_video_path] as $path) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
        }
        if ($story->cover_path) {
            Storage::disk('public')->delete($story->cover_path);
        }
        foreach ($story->pages as $page) {
            if ($page->image_path) {
                Storage::disk('local')->delete($page->image_path);
            }
        }
        $story->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $creating): array
    {
        $rules = [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'age_band' => [$creating ? 'required' : 'sometimes', Rule::in(['0_5', '6_12', '13_17', '18_plus', 'folklore'])],
            'format' => [$creating ? 'required' : 'sometimes', Rule::in(['colouring', 'tracing', 'animated', 'reading'])],
        ];
        if ($creating) {
            $rules['course_id'] = ['required', 'integer', 'exists:courses,id'];
        }
        $data = $request->validate($rules);
        $band = $data['age_band'] ?? $request->route('story')->age_band;
        $format = $data['format'] ?? $request->route('story')->format;
        abort_unless(match ($band) {
            '0_5' => in_array($format, ['colouring', 'tracing'], true),
            '6_12' => $format === 'animated',
            '13_17', '18_plus' => $format === 'reading',
            default => true,
        }, 422, 'This story format is unavailable for the selected age band.');

        return $data;
    }

    private function summary(Story $story): array
    {
        return [
            'id' => $story->id, 'course_id' => $story->course_id,
            'course_title' => $story->course->title, 'language' => $story->course->language->code,
            'title' => $story->title, 'summary' => $story->summary,
            'age_band' => $story->age_band, 'format' => $story->format,
            'price_minor' => $story->price_minor, 'published_at' => $story->published_at,
            'pages_count' => $story->pages_count ?? $story->pages()->count(),
            'cover_url' => $story->cover_path ? Storage::disk('public')->url($story->cover_path) : null,
        ];
    }

    private function detail(Story $story): array
    {
        $viewerId = auth()->id();
        $media = fn (string $slot, ?StoryPage $page = null) => URL::temporarySignedRoute('stories.authorAsset', now()->addMinutes(30), [
            'story' => $story->id, 'viewer' => $viewerId, 'slot' => $slot,
            ...($page ? ['page' => $page->id] : []),
        ], absolute: false);

        return $this->summary($story) + [
            'pages' => $story->pages->map(fn (StoryPage $page) => $this->page($page) + [
                'image_url' => $page->image_path ? $media('page', $page) : null,
            ])->values(),
            'has_preview_video' => (bool) $story->preview_video_path,
            'has_full_video' => (bool) $story->full_video_path,
            'preview_video_url' => $story->preview_video_path ? $media('preview') : null,
            'full_video_url' => $story->full_video_path ? $media('full') : null,
        ];
    }

    private function page(StoryPage $page): array
    {
        return [
            'id' => $page->id, 'position' => $page->position,
            'title' => $page->title, 'body' => $page->body,
            'has_image' => (bool) $page->image_path,
        ];
    }
}
