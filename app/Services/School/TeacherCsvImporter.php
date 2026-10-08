<?php

namespace App\Services\School;

use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\TeacherInvitation;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TeacherCsvImporter
{
    public function run(Organization $organization, User $issuer, UploadedFile $file, bool $preview): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        abort_unless($handle !== false, 422, 'Could not read the teacher file.');
        try {
            $rawHeader = fgetcsv($handle, escape: '');
            $header = array_map(fn ($value) => strtolower(trim(ltrim((string) $value, "\xEF\xBB\xBF"))), $rawHeader ?: []);
            abort_unless(count($header) === 4 && count(array_unique($header)) === 4
                && array_diff(['firstname', 'lastname', 'email', 'phone'], $header) === [], 422, 'Use the CSV columns Firstname, Lastname, Email and Phone.');
            $rows = [];
            $errors = [];
            $emails = [];
            $line = 2;
            while (! feof($handle)) {
                $start = ftell($handle);
                $values = fgetcsv($handle, escape: '');
                $end = ftell($handle);
                if ($values === false) {
                    break;
                }
                fseek($handle, $start);
                $raw = fread($handle, $end - $start);
                fseek($handle, $end);
                $rowNumber = $line;
                $line += max(1, substr_count(str_replace("\r\n", "\n", $raw), "\n"));
                if (count($values) === 1 && trim((string) $values[0]) === '') {
                    continue;
                }
                abort_if(count($rows) >= 500, 422, 'Import at most 500 teachers at a time.');
                if (count($values) !== 4) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Expected four columns.'];

                    continue;
                }
                $data = array_combine($header, array_map(fn ($value) => trim((string) $value), $values));
                $data['email'] = strtolower($data['email']);
                $data['phone'] = Phone::normalize($data['phone']);
                $validation = Validator::make($data, ['firstname' => ['required', 'string', 'max:120'], 'lastname' => ['required', 'string', 'max:120'],
                    'email' => ['required', 'email:rfc', 'max:255'], 'phone' => ['required', 'regex:/^\+[1-9][0-9]{7,14}$/']]);
                foreach ($validation->errors()->all() as $message) {
                    $errors[] = ['row' => $rowNumber, 'message' => $message];
                }
                if (isset($emails[$data['email']])) {
                    $errors[] = ['row' => $rowNumber, 'message' => 'Email is repeated in this file (first seen at row '.$emails[$data['email']].').'];
                }
                $emails[$data['email']] = $rowNumber;
                $rows[] = ['row' => $rowNumber, 'name' => $data['firstname'].' '.$data['lastname'], 'email' => $data['email'], 'phone' => $data['phone']];
            }
            abort_if($rows === [] && $errors === [], 422, 'The teacher file has no rows.');
        } finally {
            fclose($handle);
        }

        return DB::transaction(function () use ($organization, $issuer, $rows, $errors, $preview) {
            Organization::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            foreach ($rows as &$row) {
                $existing = User::whereRaw('LOWER(email) = ?', [$row['email']])->first();
                $membership = $existing ? OrganizationUser::where('organization_id', $organization->id)->where('user_id', $existing->id)->first() : null;
                if ($membership && ($membership->role !== 'teacher' || $membership->status !== 'active')) {
                    $errors[] = ['row' => $row['row'], 'message' => 'Review this account’s existing school membership before inviting it.'];
                }
                $pending = TeacherInvitation::where('organization_id', $organization->id)->where('email', $row['email'])
                    ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists();
                $row['action'] = $membership || $pending ? 'skip' : 'invite';
            }
            unset($row);
            $created = 0;
            $skipped = 0;
            if (! $preview && $errors === []) {
                foreach ($rows as $row) {
                    $issued = app(TeacherInvitationService::class)->issue($organization, $issuer, $row['name'], $row['email'], $row['phone'], true);
                    $issued ? $created++ : $skipped++;
                }
            }

            return ['preview' => $preview, 'rows' => $rows, 'errors' => $errors, 'created' => $created, 'skipped' => $skipped,
                'delivery_status' => in_array(config('mail.default'), ['log', 'array'], true) ? 'not_configured' : 'queued'];
        });
    }
}
