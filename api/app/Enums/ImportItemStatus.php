<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ImportItemStatus: string
{
    use EnumHelpers;

    case Valid = 'valid';
    case Warning = 'warning';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
    case Imported = 'imported';
    case Skipped = 'skipped';
}
