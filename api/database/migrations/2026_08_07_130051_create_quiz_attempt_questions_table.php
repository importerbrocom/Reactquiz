<?php

declare(strict_types=1);

use App\Enums\QuestionState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Current state of each question within an attempt — one row per question.
 *
 * This is the table the 10/10 check counts, so completion never has to aggregate
 * the append-only answer log. `submission_count` also seeds per-exposure option
 * shuffling (docs/adr/003), which is why that feature needs no new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('state', 16)->default(QuestionState::Unanswered->value);
            $table->char('selected_option', 1)->nullable();     // last selection
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->unsignedSmallInteger('submission_count')->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->timestamp('first_answered_at')->nullable();
            $table->timestamp('mastered_at')->nullable();
            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'question_id'], 'uq_aq_attempt_question');
            $table->index(['quiz_attempt_id', 'state'], 'ix_aq_attempt_state');
            $table->index(['quiz_attempt_id', 'position'], 'ix_aq_attempt_position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_questions');
    }
};
