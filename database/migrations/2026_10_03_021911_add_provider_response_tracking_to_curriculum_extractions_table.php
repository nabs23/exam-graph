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
        Schema::table('curriculum_extractions', function (Blueprint $table): void {
            $table->string('provider_response_id')->nullable();
            $table->timestamp('provider_started_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_extractions', function (Blueprint $table): void {
            $table->dropColumn(['provider_response_id', 'provider_started_at']);
        });
    }
};
