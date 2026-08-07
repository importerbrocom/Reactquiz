<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UserStatus: string
{
    use EnumHelpers;

    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Suspended = 'suspended';
}
