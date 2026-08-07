<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated rollups written by a queued nightly/hourly job. The admin
 * dashboard reads ONLY from here — it must never scan quiz_attempt_answers.
 * Upserted on (metric_date, level_id) so backfills are safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->foreignId('level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('exam_category_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unsignedInteger('active_students')->default(0);
            $table->unsignedInteger('new_students')->default(0);
            $table->unsignedInteger('quizzes_completed')->default(0);
            $table->unsignedInteger('tests_completed')->default(0);
            $table->unsignedInteger('answers_submitted')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->decimal('avg_score', 5, 2)->nullable();
            $table->decimal('avg_accuracy', 5, 2)->nullable();
            $table->unsignedBigInteger('study_seconds')->default(0);
            $table->unsignedInteger('notifications_sent')->default(0);
            $table->unsignedInteger('notifications_failed')->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'level_id'], 'uq_metrics_date_level');
            $table->index(['level_id', 'metric_date']);
            $table->index(['programme_id', 'metric_date']);
            $table->index(['exam_category_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_daily_metrics');
    }
};
