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
        Schema::create('syllabus_topic_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('syllabus_topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_file_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('source_file_id_snapshot');
            $table->string('source_title');
            $table->string('source_hash', 64);
            $table->unsignedInteger('page_number');
            $table->timestamps();
            $table->unique(['syllabus_topic_id', 'source_file_id_snapshot', 'page_number'], 'syllabus_topic_sources_citation_unique');
            $table->index(['subject_file_id', 'page_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('syllabus_topic_sources');
    }
};
