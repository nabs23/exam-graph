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
        Schema::create('program_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->string('file_type');
            $table->string('title');
            $table->string('original_filename');
            $table->string('storage_disk')->default('s3');
            $table->string('storage_key')->unique();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('content_hash')->nullable()->index();
            $table->string('upload_status')->default('pending_upload')->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['program_id', 'file_type', 'created_at']);
            $table->index(['upload_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_files');
    }
};
