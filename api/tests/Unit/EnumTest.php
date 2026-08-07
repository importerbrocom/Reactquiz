<?php

declare(strict_types=1);

use App\Enums\AttemptStatus;
use App\Enums\OptionKey;
use App\Enums\QuestionState;
use App\Enums\QueueName;
use App\Enums\RetryMode;
use App\Enums\UserRole;

it('exposes values usable directly in validation rules', function (): void {
    expect(UserRole::values())->toBe(['admin', 'student'])
        ->and(OptionKey::values())->toBe(['a', 'b', 'c', 'd', 'e'])
        ->and(QuestionState::values())->toBe(['unanswered', 'retry_required', 'mastered']);
});

it('builds select options with human labels', function (): void {
    expect(QuestionState::options())->toBe([
        'unanswered' => 'Unanswered',
        'retry_required' => 'Retry Required',
        'mastered' => 'Mastered',
    ]);
});

it('supports comparison helpers', function (): void {
    expect(AttemptStatus::InProgress->is(AttemptStatus::InProgress))->toBeTrue()
        ->and(AttemptStatus::InProgress->is(AttemptStatus::Completed))->toBeFalse()
        ->and(AttemptStatus::Completed->in([AttemptStatus::Completed, AttemptStatus::Expired]))->toBeTrue();
});

it('defaults retry mode to requeue so mastery means recall, not an echo', function (): void {
    // The default matters: immediate retry lets a student click the answer they were
    // just shown. Requeue puts other questions in between. See docs/adr/003.
    expect(RetryMode::RequeueAtEnd->value)->toBe('requeue_at_end')
        ->and(config('quiz.defaults.daily_question_count'))->toBe(10);
});

it('keeps quiz work on queues that never share a worker pool with heavy jobs', function (): void {
    expect(QueueName::values())
        ->toContain('critical')
        ->toContain('imports')
        ->toContain('reports')
        ->toContain('notifications');
});

it('mirrors every enum as a backed string enum for a stable API contract', function (): void {
    $enums = collect(glob(app_path('Enums/*.php')))
        ->map(fn (string $f): string => 'App\\Enums\\'.basename($f, '.php'));

    expect($enums)->toHaveCount(23);

    foreach ($enums as $enum) {
        expect(enum_exists($enum))->toBeTrue($enum.' should be an enum');

        $reflection = new ReflectionEnum($enum);
        expect((string) $reflection->getBackingType())->toBe('string', $enum.' must be string-backed');
    }
});
