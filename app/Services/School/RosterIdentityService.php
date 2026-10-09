<?php

namespace App\Services\School;

use App\Models\LearnerProfile;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

/** Persistent replay identities; callers serialize writes on the school row. */
class RosterIdentityService
{
    public function rowKey(array $row): string
    {
        return hash('sha256', json_encode([
            mb_strtolower(trim((string) ($row['display_name'] ?? ''))),
            mb_strtolower(trim((string) ($row['email'] ?? ''))),
            mb_strtolower(trim((string) ($row['level'] ?? ''))),
            trim((string) ($row['student_id'] ?? '')),
        ], JSON_THROW_ON_ERROR));
    }

    public function batchKey(array $rows): string
    {
        $keys = array_map($this->rowKey(...), array_values($rows));
        sort($keys, SORT_STRING);

        return hash('sha256', implode(':', $keys));
    }

    /**
     * Identical names inside a source file remain separate by occurrence.
     * Changed files need a StudentId when names collide; names never merge people.
     *
     * @return array{learner:?LearnerProfile,created:bool,error:?string}
     */
    public function resolve(Organization $school, array $row, string $batchKey, int $occurrence, bool $create = true, ?LearnerProfile $matchedLearner = null): array
    {
        $studentId = trim((string) ($row['student_id'] ?? ''));
        $identityKey = hash('sha256', $studentId !== ''
            ? 'student:'.$studentId
            : 'batch:'.$batchKey.':'.$this->rowKey($row).':'.$occurrence);
        $identity = DB::table('school_roster_identities')
            ->where('organization_id', $school->id)->where('identity_key', $identityKey)->first();
        if ($identity) {
            $learner = LearnerProfile::where('organization_id', $school->id)->find($identity->learner_profile_id);
            if ($learner && $matchedLearner && $learner->id !== $matchedLearner->id) {
                return ['learner' => null, 'created' => false, 'error' => 'StudentId and email identify different school learners. Correct the row before importing.'];
            }

            return ['learner' => $learner, 'created' => false, 'error' => $learner ? null : 'This roster identity belongs to an unavailable profile. Review it before importing.'];
        }

        if ($matchedLearner === null && $studentId === '') {
            $sameBatchIds = DB::table('school_roster_identities')->where('organization_id', $school->id)
                ->where('batch_key', $batchKey)->pluck('learner_profile_id');
            $collision = LearnerProfile::withTrashed()->where('organization_id', $school->id)
                ->whereRaw('LOWER(TRIM(display_name)) = ?', [mb_strtolower(trim($row['display_name']))])
                ->whereNotIn('id', $sameBatchIds)->exists();
            if ($collision) {
                return ['learner' => null, 'created' => false, 'error' => 'A school profile already has this name. Supply its established StudentId or email; use a distinct StudentId for a different learner.'];
            }
        }

        if (! $create) {
            return ['learner' => $matchedLearner, 'created' => false, 'error' => null];
        }

        $learner = $matchedLearner ?? LearnerProfile::create([
            'organization_id' => $school->id,
            'display_name' => $row['display_name'],
            'roster_level_position' => ($row['level'] ?? '') !== '' ? (int) substr($row['level'], 1) : null,
        ]);
        DB::table('school_roster_identities')->insert([
            'organization_id' => $school->id, 'identity_key' => $identityKey, 'batch_key' => $batchKey,
            'learner_profile_id' => $learner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['learner' => $learner, 'created' => $matchedLearner === null, 'error' => null];
    }
}
