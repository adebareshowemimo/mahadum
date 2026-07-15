<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\ComponentProgress;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LessonPlayController extends Controller
{
    /**
     * The play payload: ordered components with media + questions, but every
     * is_correct flag is STRIPPED (grading is server-side only). When a viewable
     * `learner_id` is supplied, each video carries that learner's saved playhead
     * and completion so the player can resume where they left off.
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        $lesson->load([
            'components' => fn ($q) => $q->orderBy('position'),
            'components.video.sourceAsset',
            'components.quiz.questions' => fn ($q) => $q->orderBy('position'),
            'components.quiz.questions.options' => fn ($q) => $q->orderBy('position'),
            'components.quiz.questions.promptAudioAsset',
            'components.quiz.questions.promptImageAsset',
            'components.speakingPrompt',
            'components.assignment',
            'components.exercise.flashcards.audioAsset',
            'components.exercise.flashcards.imageAsset',
            'components.game',
        ]);

        $progress = $this->progressByComponent($request->integer('learner_id'), $lesson);

        $components = $lesson->components->map(function ($c) use ($progress) {
            $cp = $progress[$c->id] ?? null;

            return [
                'id' => $c->id,
                'type' => $c->type,
                'position' => $c->position,
                'xp' => $c->xp_value,
                // Video gate: when true the learner must finish the clip to advance.
                'require_watch' => (bool) data_get($c->settings, 'require_watch', false),
                // Resume support — saved playhead + whether already completed.
                'resume_position' => (float) data_get($cp?->data, 'position_seconds', 0),
                'completed' => $cp?->status === 'complete',
                $c->type => $this->payloadFor($c),
            ];
        })->values();

        return response()->json(['data' => [
            'lesson' => ['id' => $lesson->id, 'title' => $lesson->title, 'est_minutes' => $lesson->est_minutes],
            'components' => $components,
        ]]);
    }

    /**
     * Map of component id → ComponentProgress for a (viewable) learner on this
     * lesson. Empty when no learner is given or the caller can't view them.
     *
     * @return array<int, ComponentProgress>
     */
    private function progressByComponent(int $learnerId, Lesson $lesson): array
    {
        if ($learnerId <= 0) {
            return [];
        }
        $learner = LearnerProfile::find($learnerId);
        if (! $learner || ! Gate::allows('view', $learner)) {
            return [];
        }

        $lessonProgress = LessonProgress::where('learner_profile_id', $learner->id)
            ->where('lesson_id', $lesson->id)
            ->first();
        if (! $lessonProgress) {
            return [];
        }

        return ComponentProgress::where('lesson_progress_id', $lessonProgress->id)
            ->get()
            ->keyBy('lesson_component_id')
            ->all();
    }

    private function payloadFor($component): ?array
    {
        return match ($component->type) {
            'video' => $component->video ? [
                'duration' => $component->video->duration_seconds,
                'quality' => $component->video->default_quality,
                'source_type' => $component->video->source_type,
                // Direct file URL from the local-disk upload (MP4/WebM). A managed
                // vendor would populate `hls` instead. Null for source_type=youtube.
                'src' => $component->video->sourceAsset
                    ? url('storage/'.$component->video->sourceAsset->url)
                    : null,
                'external_url' => $component->video->external_url,
                'hls' => null,
                'poster' => null,
                'captions' => [],
            ] : null,
            'quiz' => $component->quiz ? [
                'pass_threshold' => (float) $component->quiz->pass_threshold,
                'hearts_enabled' => (bool) $component->quiz->hearts_enabled,
                'max_attempts' => $component->quiz->max_attempts,
                'questions' => $component->quiz->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'type' => $q->type,
                    'prompt' => $q->prompt,
                    // Prompt media for audio/image question types (signed later with a vendor).
                    'prompt_audio' => $this->assetUrl($q->promptAudioAsset),
                    'prompt_image' => $this->assetUrl($q->promptImageAsset),
                    // options WITHOUT is_correct
                    'options' => $q->options->map(fn ($o) => [
                        'id' => $o->id,
                        'label' => $o->label,
                    ])->values(),
                    // For match_pairs, the shuffled right-side pool. The pairing
                    // (which option maps to which target) stays server-side.
                    'match_pool' => $q->type === 'match_pairs'
                        ? $q->options->pluck('match_target')->filter()->shuffle()->values()
                        : [],
                ])->values(),
            ] : null,
            'speaking' => $component->speakingPrompt ? [
                'prompt' => $component->speakingPrompt->prompt_text,
                'target_text' => $component->speakingPrompt->target_text,
            ] : null,
            'assignment' => $component->assignment ? [
                'prompt' => $component->assignment->prompt,
                'expected_media' => $component->assignment->expected_media,
                'max_duration_seconds' => $component->assignment->max_duration_seconds,
                'coin_reward' => $component->assignment->coin_reward,
            ] : null,
            'exercise' => $component->exercise ? [
                'mode' => $component->exercise->mode,
                'cards' => $component->exercise->flashcards->map(fn ($f) => [
                    'id' => $f->id,
                    'front' => $f->front_text,
                    'back' => $f->back_text,
                    'mnemonic' => $f->mnemonic,
                    'audio' => $this->assetUrl($f->audioAsset),
                    'image' => $this->assetUrl($f->imageAsset),
                ])->values(),
            ] : null,
            'game' => $component->game ? [
                'game_type' => $component->game->game_type,
                // Only the pair values reach the client; the game engine shuffles them.
                'pairs' => collect(data_get($component->game->config, 'pairs', []))
                    ->map(fn ($p) => ['a' => $p['a'] ?? '', 'b' => $p['b'] ?? ''])->values(),
            ] : null,
            default => null,
        };
    }

    /** Absolute URL for a local-disk media asset (null when unset). */
    private function assetUrl(?MediaAsset $asset): ?string
    {
        return $asset ? url('storage/'.$asset->url) : null;
    }
}
