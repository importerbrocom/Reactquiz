<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PushStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
