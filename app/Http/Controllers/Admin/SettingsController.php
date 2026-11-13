<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LessonComponent;
use App\Services\AuditLogger;
use App\Services\Learning\LearningGoals;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    public function __construct(private Settings $settings, private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => ['groups' => $this->settings->describe()]]);
    }

    public function update(Request $request): JsonResponse
    {
        $values = $request->input('values');
        abort_unless(is_array($values), 422, 'The "values" field must be an object.');

        // Setting keys contain dots (e.g. "compliance.minor_age"), which collide
        // with Laravel's dot-notation, so validate each value on its own.
        $clean = [];
        $errors = [];
        foreach ($values as $key => $value) {
            $def = $this->settings->definition($key);
            if ($def === null) {
                continue; // ignore unknown keys
            }

            $rules = match ($def['type']) {
                'int' => array_values(array_filter([
                    'integer',
                    isset($def['min']) ? 'min:'.$def['min'] : null,
                    isset($def['max']) ? 'max:'.$def['max'] : null,
                ])),
                'bool' => ['boolean'],
                default => ['string', 'max:255'],
            };

            $v = Validator::make(['value' => $value], ['value' => $rules]);
            if ($v->fails()) {
                $errors[$key] = $v->errors()->get('value');
            } elseif (str_starts_with($key, 'learning_goal.') && ! str_ends_with($key, '.enabled') && trim((string) $value) === '') {
                $errors[$key] = ['This learning goal field cannot be empty.'];
            } elseif (str_ends_with($key, '.keywords') && ! collect(explode(',', (string) $value))->contains(fn ($word) => trim($word) !== '')) {
                $errors[$key] = ['Enter at least one lesson keyword.'];
            } else {
                $clean[$key] = $value;
            }
        }

        if (collect(LearningGoals::KEYS)->every(fn ($key) => ! (bool) ($clean["learning_goal.$key.enabled"] ?? $this->settings->get("learning_goal.$key.enabled")))) {
            $errors['learning_goals'] = ['Keep at least one learning goal available.'];
        }

        if ($errors !== []) {
            return response()->json(['message' => 'Some settings are invalid.', 'errors' => $errors], 422);
        }

        $before = collect(array_keys($clean))->mapWithKeys(fn ($k) => [$k => $this->settings->get($k)])->all();
        $applied = DB::transaction(function () use ($clean) {
            $applied = $this->settings->set($clean);

            $xpSettings = [
                'learning.quiz_completion_xp' => 'quiz',
                'learning.video_completion_xp' => 'video',
            ];
            foreach ($xpSettings as $key => $type) {
                if (array_key_exists($key, $applied)) {
                    LessonComponent::where('type', $type)->update(['xp_value' => (int) $applied[$key]]);
                }
            }

            return $applied;
        });
        $this->audit->record('system.settings_updated', null, $before, $applied);

        return response()->json(['data' => ['groups' => $this->settings->describe()]]);
    }
}
