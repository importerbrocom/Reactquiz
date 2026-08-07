<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UnlockMode: string
{
    use EnumHelpers;

    case Immediate = 'immediate';
    case NextCalendarDay = 'next_calendar_day';
    case Scheduled = 'scheduled';
}
