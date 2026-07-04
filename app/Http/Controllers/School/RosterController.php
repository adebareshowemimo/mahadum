<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\ImportRosterRequest;
use App\Models\ClassEnrollment;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * CSV / JSON roster import. Each row creates an org learner_profile (no login —
 * school-managed) and optionally enrolls into a class. Rows are validated
 * individually so a bad row is reported, not fatal.
 */
class RosterController extends Controller
{
    use ResolvesOrganization;

    public function import(ImportRosterRequest $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $rows = $request->has('students')
            ? $request->input('students')
            : $this->parseCsv($request->file('file')->getRealPath());

        $defaultClassId = $request->integer('class_id') ?: null;
        $created = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $organization, $defaultClassId, &$created, &$errors) {
            foreach ($rows as $i => $row) {
                $name = trim($row['display_name'] ?? '');
                if ($name === '') {
                    $errors[] = ['row' => $i + 1, 'error' => 'Missing display_name'];

                    continue;
                }

                $learner = LearnerProfile::create([
                    'organization_id' => $organization->id,
                    'display_name' => $name,
                    'age_band' => $row['level'] ?? null,
                ]);

                $classId = $row['class_id'] ?? $defaultClassId;
                if ($classId) {
                    $belongs = SchoolClass::where('organization_id', $organization->id)->whereKey($classId)->exists();
                    if ($belongs) {
                        ClassEnrollment::create(['school_class_id' => $classId, 'learner_profile_id' => $learner->id]);
                    } else {
                        $errors[] = ['row' => $i + 1, 'error' => "Class {$classId} not in this organization"];
                    }
                }

                $created++;
            }

            // Reflect filled seats (best-effort against the latest allocation).
            if ($created > 0 && $allocation = $organization->seatAllocations()->latest()->first()) {
                $allocation->increment('active_filled', $created);
            }
        });

        return response()->json(['data' => ['created' => $created, 'errors' => $errors]], 201);
    }

    /** @return array<int, array{display_name:string, level?:string}> */
    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $header = null;
            while (($cols = fgetcsv($handle)) !== false) {
                if ($header === null) {
                    $header = array_map(fn ($h) => strtolower(trim($h)), $cols);
                    // Headerless file: treat first row as data.
                    if (! in_array('display_name', $header, true)) {
                        $rows[] = ['display_name' => $cols[0] ?? '', 'level' => $cols[1] ?? null];
                        $header = ['display_name', 'level'];
                    }

                    continue;
                }
                $rows[] = ['display_name' => $cols[0] ?? '', 'level' => $cols[1] ?? null];
            }
            fclose($handle);
        }

        return $rows;
    }
}
