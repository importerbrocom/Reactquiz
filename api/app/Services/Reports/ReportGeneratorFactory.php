<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ReportType;

/**
 * Factory that returns the correct report generator for a given type.
 */
class ReportGeneratorFactory
{
    public static function make(ReportType $type): ReportGeneratorInterface
    {
        return match ($type) {
            ReportType::StudentProgress => new StudentProgressReport,
            ReportType::LevelCompletion => new LevelCompletionReport,
            ReportType::QuestionDifficulty => new QuestionDifficultyReport,
            ReportType::StudentEngagement => new StudentEngagementReport,
            ReportType::TestResults => new TestResultsReport,
            ReportType::NotificationDelivery => new NotificationDeliveryReport,
        };
    }
}
