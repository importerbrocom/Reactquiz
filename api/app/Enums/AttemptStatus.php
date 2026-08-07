<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AttemptStatus: string
{
    use EnumHelpers;

    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Abandoned = 'abandoned';
    case Expired = 'expired';
}
