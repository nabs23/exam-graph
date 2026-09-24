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
        Schema::create('concept_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->decimal('last_score', 5, 2)->nullable();
            $table->decimal('best_score', 5, 2)->nullable();
            $table->unsignedInteger('attempts_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->unique(['user_id', 'concept_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('concept_progress');
    }
};
