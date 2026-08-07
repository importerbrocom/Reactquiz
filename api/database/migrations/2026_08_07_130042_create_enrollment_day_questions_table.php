<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * THE per-student question assignment (docs/adr/001).
 *
 * Written once per level enrolment as a single bulk insert of 300 rows, then read
 * with one indexed range scan per day. uq_edq_enrol_question is what makes
 * "a student never sees the same question twice in a cycle" a database guarantee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_day_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // denormalised
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();  // denormalised
            $table->unsignedSmallInteger('day_number');
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->unique(['level_enrollment_id', 'question_id'], 'uq_edq_enrol_question');
            $table->unique(['level_enrollment_id', 'day_number', 'position'], 'uq_edq_enrol_day_pos');
            $table->index(['level_enrollment_id', 'day_number'], 'ix_edq_enrol_day');
            $table->index(['user_id', 'level_id'], 'ix_edq_user_level');
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_day_questions');
    }
};
