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
        Schema::table('curriculum_extractions', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->json('source_files')->nullable()->after('source_hash');
            $table->foreignId('program_file_id')->nullable()->change();
            $table->index(['program_id', 'source_hash', 'status']);
        });

        DB::table('curriculum_extractions')
            ->orderBy('id')
            ->each(function (object $extraction): void {
                $file = DB::table('program_files')->find($extraction->program_file_id);

                if ($file === null) {
                    return;
                }

                DB::table('curriculum_extractions')->where('id', $extraction->id)->update([
                    'program_id' => $file->program_id,
                    'source_files' => json_encode([[
                        'id' => $file->id,
                        'content_hash' => $extraction->source_hash,
                        'title' => $file->title,
                    ]], JSON_THROW_ON_ERROR),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('curriculum_extractions')->whereNull('program_file_id')->exists()) {
            throw new RuntimeException('Cannot restore file-scoped curriculum extractions while program-scoped extractions exist.');
        }

        Schema::table('curriculum_extractions', function (Blueprint $table) {
            $table->dropIndex(['program_id', 'source_hash', 'status']);
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn('source_files');
            $table->foreignId('program_file_id')->nullable(false)->change();
        });
    }
};
