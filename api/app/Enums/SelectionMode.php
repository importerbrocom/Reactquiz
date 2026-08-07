<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum SelectionMode: string
{
    use EnumHelpers;

    case ExhaustiveShuffle = 'exhaustive_shuffle';
    case FixedShared = 'fixed_shared';
}
