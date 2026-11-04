<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\LearnerProfile;
use App\Models\Story;
use App\Models\StoryPage;
use App\Models\User;
use App\Services\Learning\StoryAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class StoryCatalogController extends Controller
{
    public function __construct(private StoryAccess $access) {}

    public function index(Request $request): JsonResponse
    {
        $learner = $this->learner($request);
        $query = Story::with('language')->withCount(['pages', 'questions'])
            ->whereNotNull('published_at');
        if (! $this->maySeeAdult($learner, $request->user())) {
            $query->where('age_band', '!=', '18_plus');
        }
        if ($request->filled('band')) {
            $query->where('age_band', $request->query('band'));
        }
        if ($request->filled('language')) {
            $query->whereHas('language', fn ($q) => $q->where('code', $request->query('language')));
        }
        $stories = $query->latest()->paginate(24);

        return response()->json([
            'data' => collect($stories->items())->map(fn (Story $story) => $this->summary($story, $learner, $request->user()))->values(),
            'meta' => [
                'current_page' => $stories->currentPage(), 'last_page' => $stories->lastPage(),
                'per_page' => $stories->perPage(), 'total' => $stories->total(),
            ],
        ]);
    }

    public function show(Request $request, Story $story): JsonResponse
    {
        $learner = $this->learner($request);
        abort_unless($story->published_at && $this->access->isVisible($story, $learner, $request->user()), 404);
        $purchased = $this->access->isPurchased($story, $learner);
        $limit = $purchased ? null : $this->access->previewPageCount($story);
        $pages = $story->pages()->when($limit !== null, fn ($q) => $q->limit($limit))->get();
        $questions = $story->format === 'quiz'
            ? $story->questions()->when(! $purchased, fn ($q) => $q->limit(1))->get()
            : collect();
        $media = fn (string $slot, ?StoryPage $page = null) => URL::temporarySignedRoute('stories.asset', now()->addMinutes(30), [
            'story' => $story->id, 'viewer' => $request->user()->id, 'learner' => $learner->id,
            'slot' => $slot, ...($page ? ['page' => $page->id] : []),
        ], absolute: false);

        return response()->json(['data' => $this->summary($story->load('language'), $learner, $request->user()) + [
            'pages' => $pages->map(fn (StoryPage $page) => [
                'id' => $page->id, 'position' => $page->position, 'title' => $page->title,
                'body' => $page->body, 'image_url' => $page->image_path ? $media('page', $page) : null,
            ])->values(),
            'questions' => $questions->map(fn ($question) => $question->only(['id', 'position', 'prompt', 'options']))->values(),
            'preview_video_url' => $story->preview_video_path ? $media('preview') : null,
            'full_video_url' => $purchased && $story->full_video_path ? $media('full') : null,
            'preview_ends_at' => $limit,
        ]]);
    }

    public function answer(Request $request, Story $story): JsonResponse
    {
        $learner = $this->learner($request);
        abort_unless($story->published_at && $story->format === 'quiz'
            && $this->access->isVisible($story, $learner, $request->user()), 404);
        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_index' => ['required', 'integer', 'min:0'],
        ]);
        $question = $story->questions()->findOrFail($data['question_id']);
        abort_unless($question->id === $story->questions()->first()?->id || $this->access->isPurchased($story, $learner), 403);
        abort_unless($data['option_index'] < count($question->options), 422);

        return response()->json(['data' => [
            'correct' => $data['option_index'] === $question->correct_index,
            'correct_index' => $question->correct_index,
            'explanation' => $question->explanation,
        ]]);
    }

    /** A short-lived signed URL allows native images/video to stream without exposing private files. */
    public function asset(Request $request, Story $story)
    {
        abort_unless($request->hasValidSignature(false), 403);
        $viewer = User::findOrFail($request->integer('viewer'));
        $learner = LearnerProfile::findOrFail($request->integer('learner'));
        Gate::forUser($viewer)->authorize('view', $learner);
        abort_unless($story->published_at && $this->access->isVisible($story, $learner, $viewer), 404);
        $purchased = $this->access->isPurchased($story, $learner);
        $slot = $request->query('slot');
        $path = match ($slot) {
            'preview' => $story->preview_video_path,
            'full' => $purchased ? $story->full_video_path : null,
            'page' => $this->pagePath($story, $request->integer('page'), $purchased),
            default => null,
        };
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store']);
    }

    private function learner(Request $request): LearnerProfile
    {
        $request->validate(['learner_id' => ['required', 'integer', 'exists:learner_profiles,id']]);
        $learner = LearnerProfile::findOrFail($request->integer('learner_id'));
        Gate::authorize('view', $learner);

        return $learner;
    }

    private function maySeeAdult(LearnerProfile $learner, User $viewer): bool
    {
        return ! $viewer->hasAnyRole(['teacher', 'school_admin', 'supervisor'])
            && $learner->organization_id === null
            && $learner->date_of_birth !== null
            && $learner->date_of_birth->age >= 18;
    }

    private function pagePath(Story $story, int $pageId, bool $purchased): ?string
    {
        $page = $story->pages()->find($pageId);
        if (! $page || (! $purchased && $page->position > $this->access->previewPageCount($story))) {
            return null;
        }

        return $page->image_path;
    }

    private function summary(Story $story, LearnerProfile $learner, User $viewer): array
    {
        return [
            'id' => $story->id, 'title' => $story->title, 'summary' => $story->summary,
            'level' => $story->standalone_level, 'language' => $story->language->code,
            'age_band' => $story->age_band, 'format' => $story->format,
            'price_minor' => $story->price_minor,
            'cover_url' => $story->cover_path ? Storage::disk('public')->url($story->cover_path) : null,
            'pages_count' => $story->pages_count ?? $story->pages()->count(),
            'questions_count' => $story->questions_count ?? $story->questions()->count(),
            'purchased' => $this->access->isPurchased($story, $learner),
            'can_purchase' => $this->access->canPurchase($learner, $viewer),
        ];
    }
}
