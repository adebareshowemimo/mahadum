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
use App\Services\School\RosterIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * CSV / JSON roster import. Blank-email rows create school-managed profiles;
 * an email reuses an existing profile already linked to the same school.
 * Import identities prevent replayed blank-email rows from consuming more seats.
 */
class RosterController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private ClassCourseEnrollmentService $courseEnrollments, private RosterIdentityService $identities) {}

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
        $batchKey = $this->identities->batchKey($rows);

        DB::transaction(function () use ($rows, $fromCsv, $organization, $defaultClassId, $batchKey, &$created, &$matched, &$errors) {
            // All imports for a school take the same lock before identity/seat writes.
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $occurrences = [];
            $studentIds = [];
            $prepared = [];
            foreach ($rows as $i => $row) {
                $rowNumber = $fromCsv ? $i : $i + 1;
                if (isset($row['_error'])) {
                    $errors[] = ['row' => $rowNumber, 'error' => $row['_error']];

                    continue;
                }
                $name = trim($row['display_name'] ?? '');
                $email = strtolower(trim($row['email'] ?? ''));
                $row['display_name'] = $name;
                $row['email'] = $email ?: null;
                if (array_key_exists('firstname', $row) && (trim((string) $row['firstname']) === '' || trim((string) $row['lastname']) === '')) {
                    $errors[] = ['row' => $rowNumber, 'error' => 'First name and last name are required.'];

                    continue;
                }
                $validator = Validator::make($row, [
                    'display_name' => ['required', 'string', 'max:255'],
                    'email' => ['nullable', 'email', 'max:255'],
                    'level' => ['nullable', 'string', 'regex:/^L[0-5]$/'],
                    'class_id' => ['nullable', 'integer'],
                    'student_id' => ['nullable', 'string', 'max:100'],
                ], ['level.regex' => 'Level must be L0, L1, L2, L3, L4 or L5.']);
                if ($validator->fails()) {
                    $errors[] = ['row' => $rowNumber, 'error' => $validator->errors()->first()];

                    continue;
                }

                $studentId = trim((string) ($row['student_id'] ?? ''));
                if ($studentId !== '') {
                    if (isset($studentIds[$studentId])) {
                        $errors[] = ['row' => $rowNumber, 'error' => 'StudentId is repeated in this file (first seen at row '.$studentIds[$studentId].').'];

                        continue;
                    }
                    $studentIds[$studentId] = $rowNumber;
                }

                $classId = $row['class_id'] ?? $defaultClassId;
                $class = $classId ? SchoolClass::where('organization_id', $organization->id)->find($classId) : null;
                if ($classId && ! $class) {
                    $errors[] = ['row' => $rowNumber, 'error' => "Class {$classId} not in this organization"];

                    continue;
                }
                if ($class && ($row['level'] ?? '') !== '') {
                    $position = (int) substr($row['level'], 1);
                    $missing = $class->courseAssignments()->whereHas('course', fn ($query) => $query->where('is_published', true)
                        ->whereDoesntHave('levels', fn ($levels) => $levels->where('position', $position)))->exists();
                    if ($missing) {
                        $errors[] = ['row' => $rowNumber, 'error' => 'Level '.$row['level'].' is not available in every course assigned to this class.'];

                        continue;
                    }
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

                $occurrence = 0;
                if ($learner === null) {
                    $rowKey = $this->identities->rowKey($row);
                    $occurrence = $occurrences[$rowKey] ?? 0;
                    $occurrences[$rowKey] = $occurrence + 1;
                    $identity = $this->identities->resolve($organization, $row, $batchKey, $occurrence, false);
                    if ($identity['error'] !== null) {
                        $errors[] = ['row' => $rowNumber, 'error' => $identity['error']];

                        continue;
                    }
                    $learner = $identity['learner'];
                }
                $prepared[] = compact('row', 'rowNumber', 'learner', 'occurrence', 'class');
            }

            // Preflight the entire file before profiles, identities, seats or enrolments change.
            if ($errors !== []) {
                return;
            }
            foreach ($prepared as $item) {
                $learner = $item['learner'];
                $class = $item['class'];
                $isNew = false;
                if ($learner === null) {
                    $identity = $this->identities->resolve($organization, $item['row'], $batchKey, $item['occurrence']);
                    if ($identity['error'] !== null || $identity['learner'] === null) {
                        throw ValidationException::withMessages(['file' => 'Row '.$item['rowNumber'].': '.($identity['error'] ?? 'Roster identity could not be resolved.')]);
                    }
                    $learner = $identity['learner'];
                    $isNew = $identity['created'];
                }
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
     * Array keys identify physical starting lines, including quoted multiline
     * records. Headerless records start at one.
     *
     * @return array<int, array<string, string|null>>
     */
    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $header = null;
            $line = 1;
            while (! feof($handle)) {
                $start = ftell($handle);
                $cols = fgetcsv($handle, escape: '');
                if ($cols === false) {
                    break;
                }
                $end = ftell($handle);
                fseek($handle, $start);
                $raw = fread($handle, $end - $start);
                fseek($handle, $end);
                $rowNumber = $line;
                $line += max(1, substr_count(str_replace("\r\n", "\n", $raw), "\n"));
                if ($header === null) {
                    $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($cols[0] ?? ''));
                    $header = array_map(fn ($h) => strtolower(trim((string) $h)), $cols);
                    if (! in_array('firstname', $header, true) && ! in_array('display_name', $header, true)) {
                        $header = count($cols) >= 4 ? ['firstname', 'lastname', 'email', 'level'] : ['firstname', 'lastname', 'level'];
                        $rows[$rowNumber] = $this->rowFromCols($header, $cols);
                    } elseif (count(array_unique($header)) !== count($header)) {
                        $rows[$rowNumber] = ['_error' => 'CSV column names must be unique.'];

                        break;
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
     * @return array<string, string|null>
     */
    private function rowFromCols(array $header, array $cols): array
    {
        if (count($cols) !== count($header)) {
            return ['_error' => 'Expected '.count($header).' CSV columns; found '.count($cols).'.'];
        }
        $byKey = array_combine($header, $cols);

        $displayName = array_key_exists('display_name', $byKey) && trim((string) $byKey['display_name']) !== ''
            ? trim((string) $byKey['display_name'])
            : trim(($byKey['firstname'] ?? '').' '.($byKey['lastname'] ?? ''));

        $row = ['display_name' => $displayName, 'level' => $byKey['level'] ?? null, 'email' => $byKey['email'] ?? null, 'student_id' => $byKey['studentid'] ?? null];
        if (array_key_exists('firstname', $byKey) || array_key_exists('lastname', $byKey)) {
            $row['firstname'] = $byKey['firstname'] ?? null;
            $row['lastname'] = $byKey['lastname'] ?? null;
        }

        return $row;
    }
}
