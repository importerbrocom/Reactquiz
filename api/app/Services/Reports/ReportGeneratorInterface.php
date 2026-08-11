<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\ReportExport;

interface ReportGeneratorInterface
{
    /**
     * Generate report content.
     *
     * @return array{content: string, row_count: int}
     */
    public function generate(ReportExport $report): array;
}
