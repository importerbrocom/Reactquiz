<?php

declare(strict_types=1);

use App\Enums\AttemptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One attempt at one day's quiz. The row is locked FOR UPDATE during answer
 * submission and completion, which is what serialises concurrent submissions
 * and makes "10/10 exactly once" safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');      // denormalised, avoids a join
            $table->unsignedSmallInteger('cycle_number')->default(1);
            $table->unsignedSmallInteger('attempt_number')->default(1);

            $table->string('status', 16)->default(AttemptStatus::InProgress->value);
            $table->unsignedSmallInteger('required_count');  // snapshot of daily_question_count
            $table->unsignedSmallInteger('mastered_count')->default(0);
            $table->unsignedSmallInteger('correct_submissions')->default(0);
            $table->unsignedSmallInteger('wrong_submissions')->default(0);
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->unsignedSmallInteger('score')->default(0);
            $table->unsignedSmallInteger('current_position')->default(1);
            $table->unsignedInteger('time_spent_seconds')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->string('device_type', 24)->nullable();
            $table->string('device_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'daily_quiz_id', 'cycle_number', 'attempt_number'],
                'uq_attempt_user_quiz_cycle_no',
            );
            $table->index(['user_id', 'daily_quiz_id', 'status'], 'ix_attempt_user_quiz_status');
            $table->index(['user_id', 'level_id', 'day_number'], 'ix_attempt_user_level_day');
            $table->index(['status', 'last_activity_at']);   // abandoned-attempt sweeper
            $table->index(['level_id', 'completed_at']);      // reporting
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
