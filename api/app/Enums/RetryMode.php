<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RetryMode: string
{
    use EnumHelpers;

    case RequeueAtEnd = 'requeue_at_end';
    case Immediate = 'immediate';
}
