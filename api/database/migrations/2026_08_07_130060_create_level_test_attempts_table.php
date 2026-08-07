<?php

declare(strict_types=1);

use App\Enums\TestAttemptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Month-end test attempt. `question_order` is the authoritative shuffled order for
 * this attempt; a NEW shuffle_seed is generated per attempt, so unlimited retakes
 * never present the same order twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_test_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('cycle_number')->default(1);
            $table->unsignedSmallInteger('attempt_number')->default(1);

            $table->string('status', 16)->default(TestAttemptStatus::InProgress->value);
            $table->json('question_order');                  // [questionId, ...] authoritative
            $table->unsignedInteger('shuffle_seed');
            $table->unsignedSmallInteger('total_questions');
            $table->unsignedSmallInteger('current_position')->default(1);
            $table->unsignedSmallInteger('answered_count')->default(0);
            $table->unsignedSmallInteger('flagged_count')->default(0);

            $table->unsignedSmallInteger('correct_count')->nullable();
            $table->unsignedSmallInteger('incorrect_count')->nullable();
            $table->unsignedSmallInteger('unanswered_count')->nullable();
            $table->decimal('score', 7, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('passed')->nullable();

            $table->unsignedInteger('time_limit_seconds')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->timestamp('expires_at')->nullable();      // server-computed deadline
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('result_released_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'level_test_id', 'cycle_number', 'attempt_number'],
                'uq_lta_user_test_cycle_no',
            );
            $table->index(['user_id', 'level_test_id'], 'ix_lta_user_test');
            $table->index(['status', 'last_sync_at']);
            $table->index(['level_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_test_attempts');
    }
};
