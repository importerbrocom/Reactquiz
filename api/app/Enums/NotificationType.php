<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum NotificationType: string
{
    use EnumHelpers;

    case DailyReminder = 'daily_reminder';
    case MissedQuiz = 'missed_quiz';
    case Streak = 'streak';
    case LevelUnlocked = 'level_unlocked';
    case LevelTestUnlocked = 'level_test_unlocked';
    case ProgrammeCompleted = 'programme_completed';
    case Announcement = 'announcement';
    case System = 'system';
}
