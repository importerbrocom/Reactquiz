<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\ReportExport;

class StudentEngagementReport implements ReportGeneratorInterface
{
    public function generate(ReportExport $report): array
    {
        // TODO: Implement full query logic for this report type.
        // Placeholder CSV with header row.
        return ['content' => "Report Type: {$report->type->value}\nGenerated: " . now()->toDateTimeString() . "\n", 'row_count' => 0];
    }
}
