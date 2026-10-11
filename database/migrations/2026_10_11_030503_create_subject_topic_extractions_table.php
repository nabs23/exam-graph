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
        Schema::create('subject_topic_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('queued')->index();
            $table->string('source_hash', 64);
            $table->json('source_files');
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('prompt_version', 50);
            $table->json('proposal')->nullable();
            $table->json('reviewed_proposal')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->uuid('correlation_id')->unique();
            $table->timestamp('reviewed_at')->nullable();
            $table->index(['subject_id', 'source_hash', 'status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subject_topic_extractions');
    }
};
