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
        Schema::table('subjects', function (Blueprint $table): void {
            $table->unique(['id', 'program_id'], 'subjects_id_program_id_unique');
        });

        Schema::table('program_files', function (Blueprint $table): void {
            $table->string('file_type')->default('other');
            $table->unsignedBigInteger('subject_id')->nullable()->after('program_id');
            $table->index(['subject_id', 'file_type', 'created_at'], 'program_files_subject_type_created_index');
            $table->foreign(['subject_id', 'program_id'], 'program_files_subject_program_foreign')
                ->references(['id', 'program_id'])->on('subjects')->restrictOnDelete();
        });

        Schema::create('legacy_program_file_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('legacy_type');
            $table->unsignedBigInteger('legacy_id');
            $table->foreignId('program_file_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('file_type');
            $table->string('title');
            $table->string('original_filename');
            $table->string('storage_disk');
            $table->string('storage_key');
            $table->string('content_hash')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('created_program_file')->default(false);
            $table->timestamps();
            $table->unique(['legacy_type', 'legacy_id'], 'legacy_program_file_sources_identity_unique');
            $table->index(['storage_disk', 'storage_key'], 'legacy_program_file_sources_storage_index');
        });

        Schema::table('syllabus_topic_sources', function (Blueprint $table): void {
            $table->foreignId('program_file_id')->nullable()->after('subject_file_id')->constrained()->nullOnDelete();
            $table->index(['program_file_id', 'page_number'], 'syllabus_topic_sources_program_file_page_index');
        });

        $this->backfillVerifiedProgramFileTypes();

        $this->reconcileLegacySources();
    }

    public function backfillVerifiedProgramFileTypes(): void
    {
        DB::table('program_files')
            ->where('content_hash', 'd11428585fc47e456fe5b63ab089f9799719735ff1f4aac2b243a50dac42048b')
            ->update(['file_type' => 'official_reference']);
    }

    public function reconcileLegacySources(): void
    {
        foreach (DB::table('subject_files')->orderBy('id')->get() as $source) {
            $subject = DB::table('subjects')->where('id', $source->subject_id)->first();
            if ($subject === null) {
                continue;
            }

            $existingMapping = DB::table('legacy_program_file_sources')
                ->where('legacy_type', 'subject_file')
                ->where('legacy_id', $source->id)
                ->first();

            $programFile = $existingMapping?->program_file_id !== null
                ? DB::table('program_files')->where('id', $existingMapping->program_file_id)->first()
                : ($source->content_hash === null ? null : DB::table('program_files')
                    ->where('program_id', $subject->program_id)
                    ->where('content_hash', $source->content_hash)
                    ->first());
            $createdProgramFile = (bool) ($existingMapping?->created_program_file ?? false);

            if ($programFile === null) {
                $programFileId = DB::table('program_files')->insertGetId([
                    'program_id' => $subject->program_id,
                    'subject_id' => $subject->id,
                    'file_type' => $source->file_type,
                    'title' => $source->title,
                    'original_filename' => $source->original_filename,
                    'storage_disk' => $source->storage_disk,
                    'storage_key' => $source->storage_key,
                    'mime_type' => $source->mime_type,
                    'file_size' => $source->file_size,
                    'content_hash' => $source->content_hash,
                    'upload_status' => $source->upload_status,
                    'uploaded_by' => $source->uploaded_by,
                    'uploaded_at' => $source->uploaded_at,
                    'metadata' => $source->metadata,
                    'embedding_status' => $source->embedding_status,
                    'embedding_error_code' => $source->embedding_error_code,
                    'embedding_model' => $source->embedding_model,
                    'embedded_at' => $source->embedded_at,
                    'created_at' => $source->created_at,
                    'updated_at' => $source->updated_at,
                ]);
                $programFile = DB::table('program_files')->where('id', $programFileId)->first();
                $createdProgramFile = true;
            }

            DB::table('legacy_program_file_sources')->updateOrInsert(
                ['legacy_type' => 'subject_file', 'legacy_id' => $source->id],
                [
                    'program_file_id' => $programFile->id,
                    'program_id' => $subject->program_id,
                    'subject_id' => $subject->id,
                    'file_type' => $source->file_type,
                    'title' => $source->title,
                    'original_filename' => $source->original_filename,
                    'storage_disk' => $source->storage_disk,
                    'storage_key' => $source->storage_key,
                    'content_hash' => $source->content_hash,
                    'metadata' => $source->metadata,
                    'created_program_file' => $createdProgramFile,
                    'created_at' => $source->created_at,
                    'updated_at' => $source->updated_at,
                ],
            );
        }

        foreach (DB::table('legacy_program_file_sources')->where('legacy_type', 'subject_file')->orderBy('legacy_id')->get() as $mapping) {
            foreach (DB::table('file_page_embeddings')->where('subject_file_id', $mapping->legacy_id)->orderBy('id')->get() as $page) {
                $canonicalPage = DB::table('file_page_embeddings')
                    ->where('program_file_id', $mapping->program_file_id)
                    ->where('page_number', $page->page_number)
                    ->where('model', $page->model)
                    ->first();

                if ($canonicalPage !== null) {
                    if ($canonicalPage->source_hash !== $page->source_hash
                        || $canonicalPage->provider !== $page->provider
                        || (int) $canonicalPage->dimensions !== (int) $page->dimensions
                        || $canonicalPage->content !== $page->content
                        || (string) $canonicalPage->embedding !== (string) $page->embedding) {
                        throw new RuntimeException('A legacy subject-file page conflicts with its canonical program-file page.');
                    }

                    DB::table('file_page_embeddings')->where('id', $page->id)->delete();

                    continue;
                }

                DB::table('file_page_embeddings')->where('id', $page->id)->update([
                    'subject_file_id' => null,
                    'program_file_id' => $mapping->program_file_id,
                    'source_key' => 'program_file:'.$mapping->program_file_id,
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('syllabus_topic_sources')->orderBy('id')->get()->each(function (object $citation): void {
            $mapping = DB::table('legacy_program_file_sources')
                ->where('legacy_type', 'subject_file')
                ->where('legacy_id', $citation->subject_file_id)
                ->first();
            if ($mapping !== null) {
                DB::table('syllabus_topic_sources')->where('id', $citation->id)
                    ->update(['program_file_id' => $mapping->program_file_id]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('program_files as files')->whereNotNull('subject_id')->whereNotExists(function ($query): void {
            $query->selectRaw('1')->from('legacy_program_file_sources as sources')
                ->whereColumn('sources.program_file_id', 'files.id')->where('sources.created_program_file', true);
        })->exists()) {
            throw new RuntimeException('Cannot reverse source-file unification while new subject-scoped program files exist.');
        }

        $createdIds = DB::table('legacy_program_file_sources')->where('created_program_file', true)->pluck('program_file_id');
        if ($createdIds->isNotEmpty() && DB::table('curriculum_extractions')->whereIn('program_file_id', $createdIds)->exists()) {
            throw new RuntimeException('Cannot reverse source-file unification while migrated files have curriculum extractions.');
        }

        foreach (DB::table('legacy_program_file_sources')->where('legacy_type', 'subject_file')->orderBy('legacy_id')->get() as $mapping) {
            $pages = DB::table('file_page_embeddings')->where('program_file_id', $mapping->program_file_id)->orderBy('page_number')->get();
            foreach ($pages as $page) {
                $legacyPage = DB::table('file_page_embeddings')
                    ->where('subject_file_id', $mapping->legacy_id)
                    ->where('page_number', $page->page_number)
                    ->where('model', $page->model)
                    ->first();

                if ($legacyPage !== null) {
                    if ($legacyPage->source_hash !== $page->source_hash || $legacyPage->content !== $page->content) {
                        throw new RuntimeException('Cannot restore a conflicting legacy subject-file page during rollback.');
                    }

                    continue;
                }

                DB::table('file_page_embeddings')->insert([
                    'program_file_id' => null,
                    'subject_file_id' => $mapping->legacy_id,
                    'source_key' => 'subject_file:'.$mapping->legacy_id,
                    'page_number' => $page->page_number,
                    'source_hash' => $page->source_hash,
                    'provider' => $page->provider,
                    'model' => $page->model,
                    'dimensions' => $page->dimensions,
                    'content' => $page->content,
                    'embedding' => $page->embedding,
                    'embedded_at' => $page->embedded_at,
                    'created_at' => $page->created_at,
                    'updated_at' => $page->updated_at,
                ]);
            }
        }

        DB::table('syllabus_topic_sources')->update(['program_file_id' => null]);
        Schema::table('syllabus_topic_sources', function (Blueprint $table): void {
            $table->dropForeign(['program_file_id']);
            $table->dropIndex('syllabus_topic_sources_program_file_page_index');
            $table->dropColumn('program_file_id');
        });

        if ($createdIds->isNotEmpty()) {
            DB::table('program_files')->whereIn('id', $createdIds)->delete();
        }

        Schema::dropIfExists('legacy_program_file_sources');
        Schema::table('program_files', function (Blueprint $table): void {
            $table->dropForeign('program_files_subject_program_foreign');
            $table->dropIndex('program_files_subject_type_created_index');
            $table->dropColumn(['subject_id', 'file_type']);
        });
        Schema::table('subjects', function (Blueprint $table): void {
            $table->dropUnique('subjects_id_program_id_unique');
        });
    }
};
