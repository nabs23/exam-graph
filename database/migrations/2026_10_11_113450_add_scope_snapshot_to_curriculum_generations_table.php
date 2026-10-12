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
        Schema::table('curriculum_generations', function (Blueprint $table): void {
            $table->json('scope_snapshot')->nullable()->after('source_files');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_generations', function (Blueprint $table): void {
            $table->dropColumn('scope_snapshot');
        });
    }
};
