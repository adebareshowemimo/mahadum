<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_profiles', function (Blueprint $table) {
            $table->string('parental_pin')->nullable()->after('parental_pin_protected'); // hashed, unique per child
        });

        Schema::table('learner_profiles', function (Blueprint $table) {
            $table->dropColumn('parental_pin_protected');
        });

        Schema::table('families', function (Blueprint $table) {
            $table->dropColumn('parental_pin');
        });
    }

    public function down(): void
    {
        Schema::table('families', function (Blueprint $table) {
            $table->string('parental_pin')->nullable()->after('name');
        });

        Schema::table('learner_profiles', function (Blueprint $table) {
            $table->boolean('parental_pin_protected')->default(false)->after('current_level');
            $table->dropColumn('parental_pin');
        });
    }
};
