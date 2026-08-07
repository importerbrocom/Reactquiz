<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifetime mastery per student per question, accumulating ACROSS cycles.
 *
 * Because it survives cycles, first_attempt_correct + cycle counters give the
 * retention metric that tells us whether the product actually teaches:
 * cycle-2 first-attempt accuracy vs cycle-1 (docs/adr/003 section 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_question_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->char('last_selected_option', 1)->nullable();
            $table->boolean('is_mastered')->default(false);
            $table->timestamp('mastered_at')->nullable();
            $table->boolean('first_attempt_correct')->nullable();
            // Per-cycle first-attempt outcome: {"1": true, "2": false}
            $table->json('cycle_first_attempt')->nullable();
            $table->unsignedSmallInteger('last_cycle_seen')->default(1);
            $table->timestamp('last_attempted_at')->nullable();
            $table->unsignedInteger('total_time_seconds')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'question_id'], 'uq_sqp_user_question');
            $table->index(['user_id', 'level_id', 'is_mastered'], 'ix_sqp_user_level_mastered');
            $table->index(['user_id', 'level_id', 'wrong_count'], 'ix_sqp_user_level_wrong');
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_question_progress');
    }
};
