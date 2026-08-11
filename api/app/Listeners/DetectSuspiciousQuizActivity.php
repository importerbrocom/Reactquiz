<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DailyQuizCompleted;
use App\Models\QuizAttemptAnswer;
use App\Services\Support\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Detects anomalous quiz activity that may indicate automation or answer scraping.
 *
 * Rules (from docs/phase-1/08-security-architecture.md §2):
 * - > 25 submissions on one day's quiz
 * - < 800ms average time per submission
 *
 * On trigger: writes a security-severity activity_log. We flag, not block,
 * because false positives would punish genuine learners.
 */
class DetectSuspiciousQuizActivity implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(DailyQuizCompleted $event): void
    {
        $attempt = $event->attempt;
        $thresholdSubmissions = (int) config('quiz.suspicious_submissions_per_day', 25);
        $thresholdAvgMs = (int) config('quiz.suspicious_avg_ms', 800);

        // Count total submissions for this attempt (including retries)
        $totalSubmissions = QuizAttemptAnswer::where('quiz_attempt_id', $attempt->id)->count();

        // Average time per submission
        $avgMs = (int) QuizAttemptAnswer::where('quiz_attempt_id', $attempt->id)
            ->whereNotNull('time_spent_ms')
            ->avg('time_spent_ms');

        $suspicious = false;
        $reasons = [];

        if ($totalSubmissions > $thresholdSubmissions) {
            $suspicious = true;
            $reasons[] = "submissions_count={$totalSubmissions} (threshold={$thresholdSubmissions})";
        }

        if ($avgMs > 0 && $avgMs < $thresholdAvgMs) {
            $suspicious = true;
            $reasons[] = "avg_time_ms={$avgMs} (threshold={$thresholdAvgMs})";
        }

        if (! $suspicious) {
            return;
        }

        app(ActivityLogger::class)->log(
            event: 'suspicious_quiz_activity',
            subject: $attempt,
            severity: 'security',
            properties: [
                'user_id' => $attempt->user_id,
                'attempt_uuid' => $attempt->uuid,
                'total_submissions' => $totalSubmissions,
                'avg_time_ms' => $avgMs,
                'reasons' => $reasons,
            ],
        );
    }
}
