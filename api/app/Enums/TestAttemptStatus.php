<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TestAttemptStatus: string
{
    use EnumHelpers;

    case InProgress = 'in_progress';
    case Paused = 'paused';
    case Submitted = 'submitted';
    case Graded = 'graded';
    case Expired = 'expired';
}
