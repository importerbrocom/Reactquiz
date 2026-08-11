<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Student\AnswerController;
use App\Http\Controllers\Api\V1\Student\CertificateController;
use App\Http\Controllers\Api\V1\Student\DashboardController;
use App\Http\Controllers\Api\V1\Student\LevelProgressionController;
use App\Http\Controllers\Api\V1\Student\LevelTestAnswerController;
use App\Http\Controllers\Api\V1\Student\LevelTestController;
use App\Http\Controllers\Api\V1\Student\MistakeController;
use App\Http\Controllers\Api\V1\Student\NotificationController;
use App\Http\Controllers\Api\V1\Student\OnboardingController;
use App\Http\Controllers\Api\V1\Student\ProgressController;
use App\Http\Controllers\Api\V1\Student\PushSubscriptionController;
use App\Http\Controllers\Api\V1\Student\QuizAttemptController;
use App\Http\Controllers\Api\V1\Student\QuizDayController;
use Illuminate\Support\Facades\Route;

/*
 * Student endpoints.
 *
 * Attempts are addressed by UUID, never by auto-increment id, and every lookup is
 * scoped to the authenticated user inside StudentContext. An id belonging to another
 * student therefore returns 404 rather than 403 — a 403 would confirm the row exists,
 * which is enough to enumerate other people's attempts.
 *
 * `idempotent` sits on the two write paths that a phone with a bad connection will
 * retry: answer submission and test-answer sync.
 */
Route::middleware(['auth:sanctum', 'account', 'role:student', 'track-active'])
    ->prefix('student')
    ->name('student.')
    ->group(function (): void {

        // --- onboarding ---------------------------------------------------- #
        Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('onboarding', [OnboardingController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('onboarding.store');

        // --- home ---------------------------------------------------------- #
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('progress', [ProgressController::class, 'show'])->name('progress');
        Route::get('mistakes', [MistakeController::class, 'index'])->name('mistakes.index');

        // --- daily quiz ---------------------------------------------------- #
        Route::get('days', [QuizDayController::class, 'index'])->name('days.index');
        Route::get('days/{day}', [QuizDayController::class, 'show'])
            ->whereNumber('day')
            ->name('days.show');

        // POST because it may create an attempt; repeating it resumes rather than forks.
        Route::post('days/{day}/attempt', [QuizAttemptController::class, 'store'])
            ->whereNumber('day')
            ->name('days.attempt');

        Route::get('attempts/{attempt}', [QuizAttemptController::class, 'show'])
            ->whereUuid('attempt')
            ->name('attempts.show');

        // `idempotent:optional`, not `idempotent`. The durable guarantee for an answer
        // is the unique constraint on (attempt, client_answer_uuid), which is required
        // in the request body and survives a cache flush. The Idempotency-Key header is
        // a faster short-circuit on top of that, so a client that sends it gets the
        // cached response, and one that does not is still protected. Demanding the
        // header as well would reject correct requests for no added safety.
        Route::post('attempts/{attempt}/answers', [AnswerController::class, 'store'])
            ->whereUuid('attempt')
            ->middleware(['idempotent:optional', 'throttle:quiz-write'])
            ->name('attempts.answers.store');

        Route::post('attempts/{attempt}/complete', [QuizAttemptController::class, 'complete'])
            ->whereUuid('attempt')
            ->name('attempts.complete');

        // --- month-end test ------------------------------------------------ #
        Route::get('level-test', [LevelTestController::class, 'show'])->name('level-test.show');
        Route::post('level-test/attempt', [LevelTestController::class, 'store'])->name('level-test.start');

        Route::prefix('level-test/attempts/{attempt}')
            ->whereUuid('attempt')
            ->name('level-test.')
            ->group(function (): void {
                Route::get('questions', [LevelTestController::class, 'questions'])->name('questions');
                Route::get('navigator', [LevelTestController::class, 'navigator'])->name('navigator');
                // Same reasoning: `client_batch_uuid` is required in the body, and the
                // sync is an upsert with recomputed counters, so replays are inert.
                Route::post('answers', [LevelTestAnswerController::class, 'store'])
                    ->middleware(['idempotent:optional', 'throttle:test-sync'])
                    ->name('answers');
                Route::post('submit', [LevelTestController::class, 'submit'])->name('submit');
                Route::get('result', [LevelTestController::class, 'result'])->name('result');
            });

        // --- progression --------------------------------------------------- #
        Route::get('levels/next', [LevelProgressionController::class, 'show'])->name('levels.next');
        Route::post('levels/next', [LevelProgressionController::class, 'store'])->name('levels.advance');

        // --- notifications (Phase 7) --------------------------------------- #
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        // --- push subscriptions (Phase 7) ---------------------------------- #
        Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push.store');
        Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
        Route::post('push-subscriptions/test', [PushSubscriptionController::class, 'test'])
            ->middleware('throttle:3,60')
            ->name('push.test');

        // --- certificates (Phase 8) ---------------------------------------- #
        Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
        Route::get('certificates/{uuid}/download', [CertificateController::class, 'download'])->name('certificates.download');
    });
