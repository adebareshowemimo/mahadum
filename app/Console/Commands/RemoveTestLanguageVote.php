<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveTestLanguageVote extends Command
{
    protected $signature = 'language-votes:remove-test {vote_id} {email} {--execute}';

    protected $description = 'Preview or remove one identified test vote without changing the voter account';

    public function handle(): int
    {
        return DB::transaction(function (): int {
            $vote = DB::table('language_votes')
                ->join('users', 'users.id', '=', 'language_votes.user_id')
                ->where('language_votes.id', $this->argument('vote_id'))
                ->where('users.email', $this->argument('email'))
                ->select('language_votes.id', 'language_votes.language', 'language_votes.created_at', 'users.email')
                ->lockForUpdate()->first();

            if (! $vote || $vote->language !== 'Bini') {
                $this->error('No Bini vote matches that vote ID and account email. Nothing changed.');

                return self::FAILURE;
            }

            $this->line("Vote #{$vote->id}: {$vote->language}, {$vote->email}, created {$vote->created_at}.");
            if (! $this->option('execute')) {
                $this->info('Preview only. After confirming this is the test vote, add --execute.');

                return self::SUCCESS;
            }

            DB::table('language_votes')->where('id', $vote->id)->delete();
            $this->info("Removed test vote #{$vote->id}. The voter account remains intact.");

            return self::SUCCESS;
        });
    }
}
