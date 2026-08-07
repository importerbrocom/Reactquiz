<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * is_correct is deliberately NULL until grading. Batch saves are pure upserts that
 * neither read `questions` nor leak correctness during the test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_test_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_test_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->char('selected_option', 1)->nullable();   // null = visited, unanswered
            $table->boolean('is_correct')->nullable();        // filled at grading only
            $table->boolean('is_flagged')->default(false);
            $table->decimal('marks_awarded', 5, 2)->nullable();
            $table->unsignedInteger('time_spent_ms')->nullable();
            $table->uuid('client_batch_uuid')->nullable();    // idempotent batch sync
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['level_test_attempt_id', 'question_id'], 'uq_lta_answer');
            $table->index(['level_test_attempt_id', 'is_correct'], 'ix_lta_answer_correct');
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_test_answers');
    }
};
