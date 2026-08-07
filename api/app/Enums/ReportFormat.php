<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ReportFormat: string
{
    use EnumHelpers;

    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';
}
