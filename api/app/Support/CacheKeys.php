<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Every cache key in the application is built here.
 *
 * Two rules this class exists to enforce:
 *   1. CacheInvalidationService can never miss a key that a controller invented inline.
 *   2. Any key derived from the authenticated user MUST contain u{id}, so one
 *      student's data can never be served to another. Asserted by a unit test.
 */
final class CacheKeys
{
    public const VERSION = 'v1';

    // ------------------------------------------------------------- catalogue --

    public static function activeCategories(): string
    {
        return self::key('cat', 'active');
    }

    public static function categoryProgrammes(string $categorySlug): string
    {
        return self::key('cat', $categorySlug, 'programmes');
    }

    public static function programmeConfig(int $programmeId): string
    {
        return self::key('prog', "p{$programmeId}", 'config');
    }

    public static function levelConfig(int $levelId): string
    {
        return self::key('lvl', "l{$levelId}", 'config');
    }

    public static function levelDays(int $levelId, int $contentVersion): string
    {
        return self::key('lvl', "l{$levelId}", "cv{$contentVersion}", 'days');
    }

    // --------------------------------------------------------------- content --

    /**
     * Answer-free question body, cached per QUESTION rather than per student-day.
     * Shared by every student who is served it: less memory, far higher hit ratio.
     * The content version in the key makes stale payloads unreachable without an
     * explicit delete.
     */
    public static function question(int $questionId, int $contentVersion): string
    {
        return self::key('q', "q{$questionId}", "cv{$contentVersion}");
    }

    public static function publicSettings(): string
    {
        return self::key('settings', 'public');
    }

    // ------------------------------------------------- user-scoped (u{id}!) --

    public static function studentDashboard(int $userId, int $programmeId): string
    {
        return self::key('dash', "u{$userId}", "p{$programmeId}");
    }

    public static function studentProgressHeadline(int $userId, int $levelId): string
    {
        return self::key('prog', "u{$userId}", "l{$levelId}", 'headline');
    }

    public static function studentMistakeCount(int $userId, int $levelId): string
    {
        return self::key('mist', "u{$userId}", "l{$levelId}", 'count');
    }

    public static function studentUnreadNotifications(int $userId): string
    {
        return self::key('notif', "u{$userId}", 'unread');
    }

    // ------------------------------------------------------------ admin/ops --

    public static function adminCounters(): string
    {
        return self::key('admin', 'counters');
    }

    public static function adminDashboard(string $fingerprint): string
    {
        return self::key('admin', 'dash', $fingerprint);
    }

    public static function pushTimezones(): string
    {
        return self::key('push', 'timezones');
    }

    // ----------------------------------------------------- locks & idempotency --

    public static function quizCompletionLock(int $attemptId): string
    {
        return self::key('lock', 'quiz-complete', "a{$attemptId}");
    }

    public static function idempotency(int $userId, string $route, string $key): string
    {
        return self::key('idem', "u{$userId}", $route, $key);
    }

    public static function notificationDedupe(string $type, int $userId, string $date): string
    {
        return self::key('notifdedupe', $type, "u{$userId}", $date);
    }

    /** Slight jitter so thousands of per-user keys never expire in the same second. */
    public static function jitter(int $seconds, int $percent = 10): int
    {
        $delta = (int) max(1, $seconds * $percent / 100);

        return $seconds + random_int(-$delta, $delta);
    }

    private static function key(string ...$parts): string
    {
        return implode(':', [$parts[0], self::VERSION, ...array_slice($parts, 1)]);
    }
}
