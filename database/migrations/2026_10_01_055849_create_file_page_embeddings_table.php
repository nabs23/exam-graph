<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('file_page_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_file_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subject_file_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source_key');
            $table->unsignedInteger('page_number');
            $table->string('source_hash', 64);
            $table->string('provider');
            $table->string('model');
            $table->unsignedSmallInteger('dimensions');
            $table->json('embedding');
            $table->timestamp('embedded_at');
            $table->timestamps();
            $table->unique(['source_key', 'page_number', 'model'], 'file_page_embeddings_source_unique');
            $table->index(['program_file_id', 'page_number']);
            $table->index(['subject_file_id', 'page_number']);
        });

        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql') {
            $extensionExists = $connection->pretending()
                || DB::table('pg_extension')->where('extname', 'vector')->exists();

            if (! $extensionExists) {
                Schema::dropIfExists('file_page_embeddings');

                throw new RuntimeException('The pgvector extension must be installed before applying file embedding migrations.');
            }

            DB::statement('ALTER TABLE file_page_embeddings ALTER COLUMN embedding TYPE vector(1024) USING embedding::text::vector');
            DB::statement('ALTER TABLE file_page_embeddings ADD CONSTRAINT file_page_embeddings_owner_check CHECK ((program_file_id IS NULL) <> (subject_file_id IS NULL))');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_page_embeddings');
    }
};
