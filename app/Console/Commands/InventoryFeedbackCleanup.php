<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InventoryFeedbackCleanup extends Command
{
    protected $signature = 'feedback:cleanup-inventory';

    protected $description = 'Read-only September 4 account cleanup inventory; never deletes or updates data';

    public function handle(): int
    {
        $retain = [
            'mahadum360@gmail.com' => ['name' => 'Ifeoma Okafor-Obi', 'phone' => '+2348022224495'],
            'vcamadi@yahoo.co.uk' => ['name' => 'Val Amadi'],
            'adebareshowemimo2023@gmail.com' => ['name' => 'Adebare Showemimo'],
            'kamsiobi76@gmail.com' => ['name' => 'Kamsy Obi', 'phone' => '+2348178669330'],
        ];
        $rows = User::query()->orderBy('id')->get()->map(function (User $user) use ($retain) {
            return [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'phone' => $user->phone,
                'proposed_action' => isset($retain[strtolower($user->email)]) ? 'retain_and_review_correction' : 'review_for_removal',
                'proposed_correction' => $retain[strtolower($user->email)] ?? null,
                'owned_families' => DB::table('families')->where('owner_user_id', $user->id)->pluck('id'),
                'learner_profiles' => DB::table('learner_profiles')->where('user_id', $user->id)->pluck('id'),
                'subscriptions' => DB::table('subscriptions')->where('subscriber_type', User::class)->where('subscriber_id', $user->id)->pluck('id'),
                'wallets' => DB::table('wallets')->where('owner_type', User::class)->where('owner_id', $user->id)->pluck('id'),
            ];
        });
        $this->line(json_encode([
            'environment' => app()->environment(), 'generated_at' => now()->toISOString(),
            'read_only' => true, 'users' => $rows,
            'missing_retained_emails' => array_values(array_diff(array_keys($retain), $rows->pluck('email')->map(fn ($email) => strtolower($email))->all())),
            'review_required' => 'Verify the target database, backup, dependent family/financial/audit records and exact IDs before approving any removal. No delete operation is provided by this command.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
