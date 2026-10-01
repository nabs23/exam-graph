<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['program_files', 'subject_files'] as $table) {
            DB::table($table)
                ->where('upload_status', 'uploaded')
                ->where('embedding_status', 'not_requested')
                ->where('mime_type', 'application/pdf')
                ->update(['embedding_status' => 'supported']);

            DB::table($table)
                ->where('upload_status', 'uploaded')
                ->where('embedding_status', 'not_requested')
                ->where(function ($query): void {
                    $query->whereNull('mime_type')->orWhere('mime_type', '!=', 'application/pdf');
                })
                ->update([
                    'embedding_status' => 'unsupported',
                    'embedding_error_code' => 'unsupported_file_type',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['program_files', 'subject_files'] as $table) {
            DB::table($table)
                ->where('embedding_status', 'supported')
                ->update(['embedding_status' => 'not_requested']);

            DB::table($table)
                ->where('embedding_status', 'unsupported')
                ->where('embedding_error_code', 'unsupported_file_type')
                ->update([
                    'embedding_status' => 'not_requested',
                    'embedding_error_code' => null,
                ]);
        }
    }
};
