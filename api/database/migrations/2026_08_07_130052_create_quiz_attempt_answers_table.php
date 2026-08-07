<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only log of every submission, including retries (business rule 19).
 *
 * uq_answer_client_uuid is the durable idempotency guarantee: a replayed offline
 * answer hits a duplicate-key error instead of being recorded twice. This holds
 * even if Redis is flushed, which is why it is a constraint and not just a cache.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_answer_uuid');
            $table->char('selected_option', 1);
            $table->boolean('is_correct');
            $table->unsignedSmallInteger('submission_number')->default(1);
            $table->unsignedInteger('time_spent_ms')->nullable();
            $table->timestamp('answered_at');    // client-reported, clamped server-side
            $table->timestamp('recorded_at');    // server truth
            $table->boolean('was_offline')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->unique(['quiz_attempt_id', 'client_answer_uuid'], 'uq_answer_client_uuid');
            $table->index(['quiz_attempt_id', 'question_id'], 'ix_answer_attempt_question');
            $table->index(['question_id', 'is_correct'], 'ix_answer_question_correct');
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_answers');
    }
};
