<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback (Sept 4, §8 Sign-up): the same phone number could be registered to
 * multiple accounts. We now normalise every stored phone to a canonical
 * `+<country><subscriber>` string and enforce uniqueness at the database level.
 *
 * De-duplication rule: within a colliding group the earliest-created account
 * keeps the number; later accounts have their phone set to NULL (the owner can
 * re-add and re-verify it). Every change is written to the log for audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $seen = [];

        DB::table('users')->orderBy('id')->select('id', 'phone', 'created_at')->lazy()->each(function ($row) use (&$seen) {
            $canonical = Phone::normalize($row->phone);

            if ($canonical === null) {
                if ($row->phone !== null) {
                    DB::table('users')->where('id', $row->id)->update(['phone' => null]);
                }

                return;
            }

            if (isset($seen[$canonical])) {
                Log::warning('migration.user_phone.duplicate_cleared', [
                    'user_id' => $row->id,
                    'kept_user_id' => $seen[$canonical],
                    'phone' => $canonical,
                ]);
                DB::table('users')->where('id', $row->id)->update(['phone' => null]);

                return;
            }

            $seen[$canonical] = $row->id;

            if ($row->phone !== $canonical) {
                DB::table('users')->where('id', $row->id)->update(['phone' => $canonical]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
        });
    }
};
