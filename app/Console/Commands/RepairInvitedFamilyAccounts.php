<?php

namespace App\Console\Commands;

use App\Models\TeacherInvitation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairInvitedFamilyAccounts extends Command
{
    protected $signature = 'accounts:repair-invited-families {--user= : Limit to one user ID} {--execute : Apply the previewed repairs}';

    protected $description = 'Restore Family identity and parent role for families changed by teacher invitation acceptance';

    public function handle(AuditLogger $audit): int
    {
        $userId = $this->option('user');
        if ($userId !== null && (! ctype_digit((string) $userId) || (int) $userId < 1)) {
            $this->error('Use a positive user ID.');

            return self::FAILURE;
        }

        $invitations = TeacherInvitation::query()->whereNotNull('accepted_at')->whereNotNull('accepted_by_user_id');
        if ($userId !== null) {
            $invitations->where('accepted_by_user_id', (int) $userId);
        }
        $seen = [];
        $count = 0;
        foreach ($invitations->orderByDesc('accepted_at')->get() as $invitation) {
            $id = (int) $invitation->accepted_by_user_id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            DB::transaction(function () use ($id, $invitation, $audit, &$count) {
                $user = User::whereKey($id)->lockForUpdate()->first();
                if (! $user) {
                    return;
                }
                // A family that predates teaching is evidence of the original
                // consumer identity. Do not rewrite an account formed later.
                $family = $user->ownedFamilies()->where('created_at', '<=', $invitation->accepted_at)->first();
                if (! $family) {
                    return;
                }
                $changeType = $user->signup_account_type === 'teacher';
                $addParent = ! $user->hasRole('parent');
                if (! $changeType && ! $addParent) {
                    return;
                }
                $count++;
                $this->line("User {$id}, family {$family->id}: ".($changeType ? 'restore Family type' : 'keep account type')
                    .($addParent ? ', restore parent role' : '').'; teacher role remains.');
                if (! $this->option('execute')) {
                    return;
                }
                $before = ['signup_account_type' => $user->signup_account_type, 'has_parent_role' => ! $addParent];
                if ($changeType) {
                    $user->update(['signup_account_type' => 'family']);
                }
                if ($addParent) {
                    $user->assignRole('parent');
                }
                $audit->record('account.invited_family_repaired', $user, $before,
                    ['signup_account_type' => $user->signup_account_type, 'has_parent_role' => true, 'family_id' => $family->id]);
            });
        }
        $this->info("{$count} affected account(s) ".($this->option('execute') ? 'repaired.' : 'found; preview only.'));

        return self::SUCCESS;
    }
}
