<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreLessonComponentRequest;
use App\Http\Resources\LessonComponentResource;
use App\Models\ExerciseDeck;
use App\Models\Lesson;
use App\Models\LessonComponent;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LessonComponentController extends Controller
{
    /**
     * Create a component (the base sequence row) plus its typed detail.
     * For quizzes, nested questions + options can be created in the same call.
     */
    public function store(StoreLessonComponentRequest $request, Lesson $lesson): JsonResponse
    {
        $type = (string) $request->input('type');

        $component = DB::transaction(function () use ($request, $lesson, $type) {
            $position = $request->input('position')
                ?? (($lesson->components()->max('position') ?? 0) + 1);

            $component = $lesson->components()->create([
                'type' => $type,
                'position' => $position,
                'title' => $request->input('title'),
                'is_required' => $request->boolean('is_required', true),
                'xp_value' => $request->input('xp_value', 0),
                'settings' => $request->input('settings'),
            ]);

            match ($type) {
                'video' => $this->createVideo($component, $request),
                'quiz' => $this->createQuiz($component, $request),
                'speaking' => $this->createSpeaking($component, $request),
                'assignment' => $this->createAssignment($component, $request),
                'exercise' => $this->createExercise($component, $request),
                'game' => $this->createGame($component, $request),
                default => null,
            };

            return $component;
        });

        $component->load($this->detailLoads());

        return (new LessonComponentResource($component))->response()->setStatusCode(201);
    }

    /**
     * Edit a component in place. The component type is immutable; only its base
     * fields and typed detail change. Quiz questions are replaced wholesale.
     */
    public function update(StoreLessonComponentRequest $request, LessonComponent $component): JsonResponse
    {
        DB::transaction(function () use ($request, $component) {
            $component->update([
                'title' => $request->input('title'),
                'is_required' => $request->boolean('is_required', (bool) $component->is_required),
                'xp_value' => $request->input('xp_value', $component->xp_value),
                'settings' => $request->input('settings'),
            ]);

            match ($component->type) {
                'video' => $this->updateVideo($component, $request),
                'quiz' => $this->updateQuiz($component, $request),
                'speaking' => $this->updateSpeaking($component, $request),
                'assignment' => $this->updateAssignment($component, $request),
                'exercise' => $this->updateExercise($component, $request),
                'game' => $this->updateGame($component, $request),
                default => null,
            };
        });

        $component->load($this->detailLoads());

        return (new LessonComponentResource($component))->response();
    }

    /** Delete a component. Quiz/speaking/progress cascade; video is detached, so remove it. */
    public function destroy(LessonComponent $component): JsonResponse
    {
        DB::transaction(function () use ($component) {
            $component->video?->delete();
            $component->delete();
        });

        return response()->json(null, 204);
    }

    private function createVideo(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $v = $request->input('video');
        $externalUrl = $v['external_url'] ?? null;

        $component->video()->create([
            'language_id' => $v['language_id'] ?? null,
            'title' => $v['title'],
            'presenter_name' => $v['presenter_name'] ?? null,
            'duration_seconds' => $v['duration_seconds'] ?? null,
            'default_quality' => $v['default_quality'] ?? '360p',
            // Local-disk source file (uploaded via /media/upload) or a YouTube
            // link. A managed vendor would set HLS renditions instead for the
            // uploaded case; status marks readiness.
            'source_asset_id' => $externalUrl ? null : ($v['source_asset_id'] ?? null),
            'source_type' => $externalUrl ? 'youtube' : 'upload',
            'external_url' => $externalUrl,
            'status' => $v['status'] ?? 'ready',
            'kind' => 'lesson',
        ]);
    }

    private function createQuiz(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $q = $request->input('quiz');

        $quiz = $component->quiz()->create([
            'title' => $q['title'] ?? null,
            'pass_threshold' => $q['pass_threshold'] ?? 0.6,
            'shuffle_questions' => (bool) ($q['shuffle_questions'] ?? false),
            'max_attempts' => $q['max_attempts'] ?? null,
            'hearts_enabled' => (bool) ($q['hearts_enabled'] ?? true),
        ]);

        $this->writeQuestions($quiz, $q['questions'] ?? []);
    }

    private function createSpeaking(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $s = $request->input('speaking');
        $component->speakingPrompt()->create([
            'prompt_text' => $s['prompt_text'],
            'target_text' => $s['target_text'] ?? null,
            'tone_targets' => $s['tone_targets'] ?? null,
        ]);
    }

    private function updateVideo(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $v = $request->input('video');
        $payload = [
            'title' => $v['title'],
            'presenter_name' => $v['presenter_name'] ?? null,
            'duration_seconds' => $v['duration_seconds'] ?? null,
            'default_quality' => $v['default_quality'] ?? '360p',
            'status' => $v['status'] ?? 'ready',
            'language_id' => $v['language_id'] ?? null,
            'kind' => 'lesson',
        ];

        // Only swap the source when a new one is supplied; keep the current one otherwise.
        if (! empty($v['external_url'])) {
            $payload['source_type'] = 'youtube';
            $payload['external_url'] = $v['external_url'];
            $payload['source_asset_id'] = null;
        } elseif (array_key_exists('source_asset_id', $v) && $v['source_asset_id'] !== null) {
            $payload['source_type'] = 'upload';
            $payload['source_asset_id'] = $v['source_asset_id'];
            $payload['external_url'] = null;
        }

        $component->video()->updateOrCreate([], $payload);
    }

    private function updateQuiz(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $q = $request->input('quiz');

        $quiz = $component->quiz()->updateOrCreate([], [
            'title' => $q['title'] ?? null,
            'pass_threshold' => $q['pass_threshold'] ?? 0.6,
            'shuffle_questions' => (bool) ($q['shuffle_questions'] ?? false),
            'max_attempts' => $q['max_attempts'] ?? null,
            'hearts_enabled' => (bool) ($q['hearts_enabled'] ?? true),
        ]);

        // Replace questions wholesale (options cascade on delete).
        $quiz->questions()->delete();
        $this->writeQuestions($quiz, $q['questions'] ?? []);
    }

    private function updateSpeaking(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $s = $request->input('speaking');
        $component->speakingPrompt()->updateOrCreate([], [
            'prompt_text' => $s['prompt_text'],
            'target_text' => $s['target_text'] ?? null,
            'tone_targets' => $s['tone_targets'] ?? null,
        ]);
    }

    private function createAssignment(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $a = $request->input('assignment');
        $component->assignment()->create([
            'prompt' => $a['prompt'],
            'expected_media' => $a['expected_media'] ?? 'video',
            'max_duration_seconds' => $a['max_duration_seconds'] ?? null,
            'coin_reward' => $a['coin_reward'] ?? 0,
        ]);
    }

    private function updateAssignment(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $a = $request->input('assignment');
        $component->assignment()->updateOrCreate([], [
            'prompt' => $a['prompt'],
            'expected_media' => $a['expected_media'] ?? 'video',
            'max_duration_seconds' => $a['max_duration_seconds'] ?? null,
            'coin_reward' => $a['coin_reward'] ?? 0,
        ]);
    }

    private function createExercise(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $e = $request->input('exercise');
        $deck = $component->exercise()->create(['mode' => $e['mode'] ?? 'flashcards']);
        $this->writeFlashcards($deck, $e['cards'] ?? []);
    }

    private function updateExercise(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $e = $request->input('exercise');
        $deck = $component->exercise()->updateOrCreate([], ['mode' => $e['mode'] ?? 'flashcards']);
        $deck->flashcards()->delete();
        $this->writeFlashcards($deck, $e['cards'] ?? []);
    }

    private function createGame(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $g = $request->input('game');
        $component->game()->create(['game_type' => $g['game_type'] ?? 'memory', 'config' => $g['config'] ?? null]);
    }

    private function updateGame(LessonComponent $component, StoreLessonComponentRequest $request): void
    {
        $g = $request->input('game');
        $component->game()->updateOrCreate([], ['game_type' => $g['game_type'] ?? 'memory', 'config' => $g['config'] ?? null]);
    }

    /** @param  array<int, array<string, mixed>>  $cards */
    private function writeFlashcards(ExerciseDeck $deck, array $cards): void
    {
        foreach ($cards as $card) {
            $deck->flashcards()->create([
                'front_text' => $card['front_text'],
                'back_text' => $card['back_text'],
                'mnemonic' => $card['mnemonic'] ?? null,
                'audio_asset_id' => $card['audio_asset_id'] ?? null,
                'image_asset_id' => $card['image_asset_id'] ?? null,
            ]);
        }
    }

    /** @return array<int, string> eager-loads for the full authoring detail. */
    private function detailLoads(): array
    {
        return [
            'video', 'quiz.questions.options', 'quiz.questions.promptAudioAsset', 'quiz.questions.promptImageAsset',
            'speakingPrompt', 'assignment', 'exercise.flashcards.audioAsset', 'exercise.flashcards.imageAsset', 'game',
        ];
    }

    /** @param  array<int, array<string, mixed>>  $questions */
    private function writeQuestions(Quiz $quiz, array $questions): void
    {
        foreach ($questions as $i => $questionData) {
            $question = $quiz->questions()->create([
                'type' => $questionData['type'],
                'prompt' => $questionData['prompt'],
                'explanation' => $questionData['explanation'] ?? null,
                'target_text' => $questionData['target_text'] ?? null,
                'prompt_audio_asset_id' => $questionData['prompt_audio_asset_id'] ?? null,
                'prompt_image_asset_id' => $questionData['prompt_image_asset_id'] ?? null,
                'points' => $questionData['points'] ?? 1,
                'position' => $i + 1,
            ]);

            foreach ($questionData['options'] ?? [] as $j => $option) {
                $question->options()->create([
                    'label' => $option['label'],
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'match_target' => $option['match_target'] ?? null,
                    'position' => $j + 1,
                ]);
            }
        }
    }
}
