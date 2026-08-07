<?php

declare(strict_types=1);

use App\Support\CacheKeys;

it('scopes every user-specific key with the user id', function (): void {
    /*
     * The rule this guards: any cached value derived from the authenticated user
     * MUST carry u{id}, or one student's dashboard could be served to another.
     * Enumerated by reflection so a new user-scoped key cannot skip the check.
     */
    $userScoped = [
        'studentDashboard' => [42, 7],
        'studentProgressHeadline' => [42, 7],
        'studentMistakeCount' => [42, 7],
        'studentUnreadNotifications' => [42],
    ];

    $reflection = new ReflectionClass(CacheKeys::class);
    $declared = collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn (ReflectionMethod $m): string => $m->getName())
        ->filter(fn (string $name): bool => str_starts_with($name, 'student'))
        ->values()
        ->all();

    // Every student* method must be covered by this test.
    expect(array_keys($userScoped))->toEqualCanonicalizing($declared);

    foreach ($userScoped as $method => $args) {
        $key = CacheKeys::{$method}(...$args);

        // Note: toContain() treats extra arguments as additional needles, so the
        // explanatory message goes on a boolean assertion instead.
        expect(str_contains($key, 'u42'))
            ->toBeTrue("CacheKeys::{$method}() must include the user id, got: {$key}");
    }
});

it('produces different keys for different users', function (): void {
    expect(CacheKeys::studentDashboard(1, 7))
        ->not->toBe(CacheKeys::studentDashboard(2, 7));
});

it('versions question keys by content version so stale payloads are unreachable', function (): void {
    $v1 = CacheKeys::question(8421, 1);
    $v2 = CacheKeys::question(8421, 2);

    expect($v1)->not->toBe($v2)
        ->and($v1)->toBe('q:v1:q8421:cv1');
});

it('includes the schema version in every key', function (): void {
    $keys = [
        CacheKeys::activeCategories(),
        CacheKeys::categoryProgrammes('fmge'),
        CacheKeys::levelConfig(3),
        CacheKeys::adminCounters(),
        CacheKeys::publicSettings(),
        CacheKeys::pushTimezones(),
    ];

    foreach ($keys as $key) {
        expect($key)->toContain(':'.CacheKeys::VERSION.':');
    }
});

it('jitters ttls so thousands of keys do not expire in the same second', function (): void {
    $values = collect(range(1, 60))->map(fn (): int => CacheKeys::jitter(600));

    expect($values->unique()->count())->toBeGreaterThan(1)
        ->and($values->min())->toBeGreaterThanOrEqual(540)
        ->and($values->max())->toBeLessThanOrEqual(660);
});
