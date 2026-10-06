<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\ImportRosterRequest;
use App\Models\ClassEnrollment;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Services\School\ClassCourseEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * CSV / JSON roster import. Blank-email rows create school-managed profiles;
 * an email reuses an existing profile already linked to the same school.
 * Invalid rows are reported before any profile or enrollment is written.
 */
class RosterController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private ClassCourseEnrollmentService $courseEnrollments) {}

    public function import(ImportRosterRequest $request, Organization $organization): JsonResponse
    {
        $this->authorizeOrg($request->user(), $organization);

        $fromCsv = ! $request->has('students');
        $rows = $fromCsv
            ? $this->parseCsv($request->file('file')->getRealPath())
            : $request->input('students');

        $defaultClassId = $request->integer('class_id') ?: null;
        $created = 0;
        $matched = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $fromCsv, $organization, $defaultClassId, &$created, &$matched, &$errors) {
            foreach ($rows as $i => $row) {
                $rowNumber = $fromCsv ? $i : $i + 1;
                $name = trim($row['display_name'] ?? '');
                $email = strtolower(trim($row['email'] ?? ''));
                $row['display_name'] = $name;
                $row['email'] = $email ?: null;
                $validator = Validator::make($row, [
                    'display_name' => ['required', 'string', 'max:255'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'level' => ['nullable', 'string', 'max:100'],
                    'class_id' => ['nullable', 'integer'],
                ]);
                if ($validator->fails()) {
                    $errors[] = ['row' => $rowNumber, 'error' => $validator->errors()->first()];

                    continue;
                }

                $classId = $row['class_id'] ?? $defaultClassId;
                $class = $classId ? SchoolClass::where('organization_id', $organization->id)->find($classId) : null;
                if ($classId && ! $class) {
                    $errors[] = ['row' => $rowNumber, 'error' => "Class {$classId} not in this organization"];

                    continue;
                }

                $learner = null;
                if ($email !== '') {
                    $matches = LearnerProfile::where('organization_id', $organization->id)
                        ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))
                        ->lockForUpdate()->limit(2)->get();
                    if ($matches->count() !== 1) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'error' => $matches->isEmpty()
                                ? 'No learner profile with this email is linked to this school. Use the class invitation flow for a new login.'
                                : 'Multiple learner profiles in this school use this email. Review those profiles before importing.',
                        ];

                        continue;
                    }
                    $learner = $matches->first();
                }

                $isNew = $learner === null;
                $learner ??= LearnerProfile::create([
                    'organization_id' => $organization->id,
                    'display_name' => $name,
                    'age_band' => $row['level'] ?? null,
                ]);
                if ($class) {
                    ClassEnrollment::firstOrCreate(['school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);
                    $this->courseEnrollments->syncLearner($class, $learner);
                }

                if ($isNew) {
                    $created++;
                } else {
                    $matched++;
                }
            }

            // Only new profiles fill new seats; matching preserves the existing allocation.
            if ($created > 0 && $allocation = $organization->seatAllocations()->latest()->first()) {
                $allocation->increment('active_filled', $created);
            }
        });

        return response()->json(['data' => ['created' => $created, 'matched' => $matched, 'errors' => $errors]], 201);
    }

    /**
     * Header-driven; accepts Email plus legacy three-column/display_name files.
     * Array keys identify CSV records including the header, so errors line up
     * with the usual spreadsheet rows. Headerless records start at one.
     *
     * @return array<int, array{display_name:string, level:?string, email:?string}>
     */
    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $header = null;
            $rowNumber = 0;
            while (($cols = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($header === null) {
                    $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($cols[0] ?? ''));
                    $header = array_map(fn ($h) => strtolower(trim((string) $h)), $cols);
                    if (! in_array('firstname', $header, true) && ! in_array('display_name', $header, true)) {
                        $header = count($cols) >= 4 ? ['firstname', 'lastname', 'email', 'level'] : ['firstname', 'lastname', 'level'];
                        $rows[$rowNumber] = $this->rowFromCols($header, $cols);
                    }

                    continue;
                }
                $rows[$rowNumber] = $this->rowFromCols($header, $cols);
            }
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<int, string|null>  $cols
     * @return array{display_name:string, level:?string, email:?string}
     */
    private function rowFromCols(array $header, array $cols): array
    {
        $byKey = array_combine($header, array_slice(array_pad($cols, count($header), null), 0, count($header)));

        $displayName = array_key_exists('display_name', $byKey) && trim((string) $byKey['display_name']) !== ''
            ? trim((string) $byKey['display_name'])
            : trim(($byKey['firstname'] ?? '').' '.($byKey['lastname'] ?? ''));

        return ['display_name' => $displayName, 'level' => $byKey['level'] ?? null, 'email' => $byKey['email'] ?? null];
    }
}
