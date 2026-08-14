<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $pidgin = DB::table('languages')->where('code', 'pcm')->first();
            if (! $pidgin) {
                return;
            }

            $english = DB::table('languages')->where('code', 'en')->first();
            if (! $english) {
                DB::table('languages')->where('id', $pidgin->id)->update([
                    'code' => 'en',
                    'name' => 'English',
                    'updated_at' => now(),
                ]);

                return;
            }

            foreach ([
                ['courses', 'language_id'],
                ['learner_profiles', 'target_language_id'],
                ['videos', 'language_id'],
                ['placement_assessments', 'language_id'],
                ['cultural_contents', 'language_id'],
                ['competition_entries', 'language_id'],
            ] as [$table, $column]) {
                DB::table($table)->where($column, $pidgin->id)->update([$column => $english->id]);
            }

            DB::table('languages')->where('id', $pidgin->id)->delete();
        });
    }

    public function down(): void
    {
        // Intentional no-op: if an English row already existed, the migration
        // merged references and cannot safely infer which records were Pidgin.
    }
};
