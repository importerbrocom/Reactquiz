<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (student, level, cycle). This is what makes repeat cycles almost
 * free: cycle 2 is simply a new row with a new assignment_seed, so the same
 * assignment code deals the same questions into a different 30-day grouping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('programme_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('cycle_number')->default(1);

            $table->string('status', 16)->default(EnrollmentStatus::Active->value);
            $table->boolean('is_active')->nullable()->default(true);

            // Drives this student's question-to-day deal. Reproducible forever.
            $table->unsignedInteger('assignment_seed');
            $table->timestamp('questions_assigned_at')->nullable();

            $table->unsignedSmallInteger('current_day')->default(1);
            $table->unsignedSmallInteger('highest_unlocked_day')->default(1);
            $table->unsignedSmallInteger('completed_days')->default(0);
            $table->unsignedSmallInteger('last_completed_day')->nullable();
            $table->timestamp('last_completed_at')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->decimal('accuracy_percent', 5, 2)->nullable();
            $table->unsignedInteger('total_study_seconds')->default(0);

            $table->timestamp('test_unlocked_at')->nullable();
            $table->timestamp('test_passed_at')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'level_id', 'cycle_number', 'is_active'], 'uq_level_enrol_active');
            $table->index(['programme_enrollment_id', 'cycle_number', 'level_id'], 'ix_level_enrol_prog_cycle');
            $table->index(['user_id', 'status']);
            $table->index(['level_id', 'status']);
            $table->index('last_completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_enrollments');
    }
};
