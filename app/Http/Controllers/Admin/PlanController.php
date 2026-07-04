<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin management of subscription plans: create new tiers (e.g. a
 * quarterly parent plan or a yearly teacher plan) and edit price / interval /
 * profile cap / audience / entitlement flags. `code` is immutable once set — the
 * app keys on it.
 */
class PlanController extends Controller
{
    /** Boolean entitlement flags that are safe to toggle from the admin UI. */
    private const FEATURE_FLAGS = ['ads', 'offline_download', 'unlimited_hearts', 'family_dashboard', 'teacher_analytics'];

    private const INTERVALS = 'month,quarter,year,term,week';

    private const AUDIENCES = 'individual,family,teacher,school,any';

    public function __construct(private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => Plan::orderBy('price_minor')->get()->map($this->present(...))]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:plans,code'],
            'name' => ['required', 'string', 'max:120'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'interval' => ['required', 'in:'.self::INTERVALS],
            'audience' => ['nullable', 'in:'.self::AUDIENCES],
            'max_profiles' => ['nullable', 'integer', 'min:1'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['boolean'],
        ]);

        $features = [];
        foreach (self::FEATURE_FLAGS as $flag) {
            $features[$flag] = (bool) ($validated['features'][$flag] ?? false);
        }

        $plan = Plan::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'price_minor' => $validated['price_minor'],
            'currency' => $validated['currency'] ?? 'NGN',
            'interval' => $validated['interval'],
            'audience' => $validated['audience'] ?? null,
            'max_profiles' => $validated['max_profiles'] ?? null,
            'features' => $features,
        ]);

        $this->audit->record('plan.created', $plan, [], $this->present($plan));

        return response()->json(['data' => $this->present($plan)], 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'price_minor' => ['sometimes', 'required', 'integer', 'min:0'],
            'interval' => ['sometimes', 'required', 'in:'.self::INTERVALS],
            'audience' => ['sometimes', 'nullable', 'in:'.self::AUDIENCES],
            'max_profiles' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['boolean'],
        ]);

        $before = $this->present($plan);

        // Merge only the whitelisted boolean flags into the existing features map,
        // so non-editable keys (seats, priced_per_seat, …) are preserved.
        if (array_key_exists('features', $validated)) {
            $features = $plan->features ?? [];
            foreach (self::FEATURE_FLAGS as $flag) {
                if (array_key_exists($flag, $validated['features'])) {
                    $features[$flag] = (bool) $validated['features'][$flag];
                }
            }
            $plan->features = $features;
            unset($validated['features']);
        }

        $plan->fill($validated)->save();

        $this->audit->record('plan.updated', $plan, $before, $this->present($plan->fresh()));

        return response()->json(['data' => $this->present($plan)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Plan $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'price_minor' => $p->price_minor,
            'currency' => $p->currency,
            'interval' => $p->interval,
            'audience' => $p->audience,
            'max_profiles' => $p->max_profiles,
            'features' => $p->features,
            'editable_flags' => self::FEATURE_FLAGS,
            'intervals' => explode(',', self::INTERVALS),
            'audiences' => explode(',', self::AUDIENCES),
        ];
    }
}
