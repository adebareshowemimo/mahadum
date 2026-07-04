<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_upload_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained('contact_lists')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            // active | rolled_back
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // Link each imported contact to its batch so an import can be rolled back.
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('upload_batch_id')->nullable()->after('source')
                ->constrained('contact_upload_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upload_batch_id');
        });
        Schema::dropIfExists('contact_upload_batches');
    }
};
