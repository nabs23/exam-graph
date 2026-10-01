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
        foreach (['program_files', 'subject_files'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('embedding_status')->default('not_requested')->index();
                $table->string('embedding_error_code')->nullable();
                $table->string('embedding_model')->nullable();
                $table->timestamp('embedded_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['program_files', 'subject_files'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_embedding_status_index');
                $table->dropColumn(['embedding_status', 'embedding_error_code', 'embedding_model', 'embedded_at']);
            });
        }
    }
};
