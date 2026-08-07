<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CycleReshuffleScope: string
{
    use EnumHelpers;

    case WithinLevel = 'within_level';
    case AcrossProgramme = 'across_programme';
}
