<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ActivitySeverity: string
{
    use EnumHelpers;

    case Info = 'info';
    case Warning = 'warning';
    case Security = 'security';
}
