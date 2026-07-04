<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            } else {
                $clean[$key] = $value;
            }
        }

        if ($errors !== []) {
            return response()->json(['message' => 'Some settings are invalid.', 'errors' => $errors], 422);
        }

        $before = collect(array_keys($clean))->mapWithKeys(fn ($k) => [$k => $this->settings->get($k)])->all();
        $applied = $this->settings->set($clean);
        $this->audit->record('system.settings_updated', null, $before, $applied);

        return response()->json(['data' => ['groups' => $this->settings->describe()]]);
    }
}
