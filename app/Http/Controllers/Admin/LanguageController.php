<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super-admin control over which languages are live. Deactivating a language
 * hides it from the public /config bootstrap (and thus enrolment) without
 * touching its courses. `content.languages.manage` is super-admin-only.
 */
class LanguageController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        $languages = Language::query()
            ->withCount([
                'courses',
                'courses as published_courses_count' => fn ($q) => $q->where('is_published', true),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Language $l) => [
                'id' => $l->id,
                'code' => $l->code,
                'name' => $l->name,
                'script' => $l->script,
                'rtl' => (bool) $l->rtl,
                'is_active' => (bool) $l->is_active,
                'position' => (int) $l->position,
                'courses_total' => $l->courses_count,
                'courses_published' => $l->getAttribute('published_courses_count'),
            ]);

        return response()->json(['data' => $languages]);
    }

    /**
     * Persist a new display order. Accepts the full list of language ids in the
     * desired order; positions are reassigned 0..n so the public /config and the
     * admin list read back in the same sequence.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:languages,id'],
        ]);

        foreach ($validated['ids'] as $position => $id) {
            Language::where('id', $id)->update(['position' => $position]);
        }

        $this->audit->record('languages.reordered', null, [], ['order' => $validated['ids']]);

        return response()->json(['data' => ['ids' => $validated['ids']]]);
    }

    public function update(Request $request, Language $language): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $before = (bool) $language->is_active;
        $language->update(['is_active' => $validated['is_active']]);

        $this->audit->record(
            'language.updated',
            $language,
            ['is_active' => $before],
            ['is_active' => (bool) $language->is_active],
        );

        return response()->json(['data' => ['id' => $language->id, 'is_active' => (bool) $language->is_active]]);
    }
}
