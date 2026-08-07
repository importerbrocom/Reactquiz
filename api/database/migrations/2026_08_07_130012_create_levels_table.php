<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\RetryMode;
use App\Enums\SelectionMode;
use App\Enums\UnlockMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One month inside a programme: 30 days x 10 questions + a month-end test on day 31.
 * (Renamed from "courses" — see docs/adr/002 section 1.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level_number');           // 1..6
            $table->string('title', 160);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->string('thumbnail_path')->nullable();

            // --- daily quiz configuration -------------------------------------
            $table->unsignedSmallInteger('total_quiz_days')->default(30);
            $table->unsignedSmallInteger('daily_question_count')->default(10);
            $table->string('selection_mode', 24)->default(SelectionMode::ExhaustiveShuffle->value);
            $table->boolean('spread_topics_across_days')->default(true);
            $table->string('unlock_mode', 24)->default(UnlockMode::Immediate->value);
            $table->string('retry_mode', 20)->default(RetryMode::RequeueAtEnd->value);
            $table->unsignedSmallInteger('recall_check_count')->default(0);
            $table->boolean('show_explanation_on_correct')->default(false);

            // --- month-end test configuration ---------------------------------
            $table->unsignedSmallInteger('test_day')->default(31);
            $table->unsignedSmallInteger('test_question_count')->default(300);
            $table->decimal('pass_percentage', 5, 2)->default(50.00);
            $table->unsignedSmallInteger('test_time_limit_minutes')->nullable(); // null = untimed
            $table->unsignedSmallInteger('test_attempt_limit')->default(0);      // 0 = unlimited
            $table->boolean('shuffle_test_questions')->default(true);
            $table->boolean('allow_test_pause')->default(false);
            $table->boolean('release_results_immediately')->default(true);
            $table->boolean('issue_certificate')->default(false);

            // --- reminders ----------------------------------------------------
            $table->boolean('reminders_enabled')->default(true);
            $table->time('default_reminder_time')->default('19:00:00');
            $table->unsignedSmallInteger('missed_quiz_reminder_hours')->default(24);

            $table->string('status', 16)->default(ContentStatus::Draft->value);
            // Bumped on any content change: appears in cache keys so stale question
            // payloads become unreachable rather than needing explicit deletion.
            $table->unsignedInteger('content_version')->default(1);
            $table->unsignedInteger('questions_count')->default(0);   // must reach 300 to publish

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['programme_id', 'level_number']);
            $table->unique(['programme_id', 'slug']);
            $table->index(['programme_id', 'status']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
    }
};
