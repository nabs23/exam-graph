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
            $table->string('code')->nullable()->change();
        });

        Schema::table('syllabus_topics', function (Blueprint $table): void {
            $table->string('code')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('subjects')->whereNull('code')->exists() || DB::table('syllabus_topics')->whereNull('code')->exists()) {
            throw new RuntimeException('Cannot require curriculum codes while records have no code. Add codes before rolling back this migration.');
        }

        Schema::table('subjects', function (Blueprint $table): void {
            $table->string('code')->nullable(false)->change();
        });

        Schema::table('syllabus_topics', function (Blueprint $table): void {
            $table->string('code')->nullable(false)->change();
        });
    }
};
