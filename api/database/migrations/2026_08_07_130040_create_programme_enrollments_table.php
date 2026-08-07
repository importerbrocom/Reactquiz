<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The student's place in a 6-level, multi-cycle programme. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_enrollments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default(EnrollmentStatus::Active->value);
            // 1 while live, NULL once closed. Because unique indexes treat NULLs as
            // distinct, this enforces "one active enrolment per student per programme"
            // while still allowing a history of cancelled ones.
            $table->boolean('is_active')->nullable()->default(true);

            $table->unsignedSmallInteger('current_cycle')->default(1);
            $table->unsignedSmallInteger('current_level')->default(1);
            $table->unsignedSmallInteger('levels_completed_this_cycle')->default(0);
            $table->unsignedSmallInteger('total_levels_completed')->default(0);
            $table->unsignedInteger('total_questions_mastered')->default(0);
            $table->decimal('overall_progress_percent', 5, 2)->default(0);

            $table->timestamp('enrolled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'programme_id', 'is_active']);
            $table->index(['user_id', 'status']);
            $table->index(['programme_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_enrollments');
    }
};
