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
        Schema::create('curriculum_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('syllabus_topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 30);
            $table->string('status', 30)->default('queued');
            $table->text('request_context')->nullable();
            $table->boolean('ungrounded_requested')->default(false);
            $table->string('request_hash', 64);
            $table->string('source_hash', 64);
            $table->json('source_files');
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->string('prompt_version', 50);
            $table->string('schema_version', 50);
            $table->json('proposal')->nullable();
            $table->json('reviewed_proposal')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->uuid('correlation_id')->unique();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->index(['program_id', 'kind', 'status']);
            $table->index(['subject_id', 'kind', 'status']);
            $table->index(['syllabus_topic_id', 'kind', 'status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_generations');
    }
};
