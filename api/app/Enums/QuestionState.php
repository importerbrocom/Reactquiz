<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum QuestionState: string
{
    use EnumHelpers;

    case Unanswered = 'unanswered';
    case RetryRequired = 'retry_required';
    case Mastered = 'mastered';
}
