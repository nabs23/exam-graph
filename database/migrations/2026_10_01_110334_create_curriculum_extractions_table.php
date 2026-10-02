<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('curriculum_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('queued')->index();
            $table->string('source_hash', 64);
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('prompt_version', 50);
            $table->json('proposal')->nullable();
            $table->json('reviewed_proposal')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['program_file_id', 'source_hash', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_extractions');
    }
};
