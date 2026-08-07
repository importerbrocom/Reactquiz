<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReportType: string
{
    use EnumHelpers;

    case StudentProgress = 'student_progress';
    case LevelCompletion = 'level_completion';
    case QuestionDifficulty = 'question_difficulty';
    case StudentEngagement = 'student_engagement';
    case TestResults = 'test_results';
    case NotificationDelivery = 'notification_delivery';
}
