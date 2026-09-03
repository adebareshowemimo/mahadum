<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('courses')
                ->where('title', 'like', '%Pidgin%')
                ->orWhere('description', 'like', '%Pidgin%')
                ->get(['id', 'title', 'description'])
                ->each(function (object $course) {
                    DB::table('courses')->where('id', $course->id)->update([
                        'title' => str_ireplace('Pidgin', 'English', (string) $course->title),
                        'description' => $course->description === null
                            ? null
                            : str_ireplace('Pidgin', 'English', (string) $course->description),
                        'updated_at' => now(),
                    ]);
                });

            DB::table('school_classes')
                ->where('name', 'like', '%Pidgin%')
                ->get(['id', 'name'])
                ->each(function (object $class) {
                    DB::table('school_classes')->where('id', $class->id)->update([
                        'name' => str_ireplace('Pidgin', 'English', (string) $class->name),
                        'updated_at' => now(),
                    ]);
                });
        });
    }

    public function down(): void
    {
        // Product terminology changed from Pidgin to English. Reversing names
        // would incorrectly relabel content created after this migration.
    }
};
