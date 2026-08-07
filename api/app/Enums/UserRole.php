<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UserRole: string
{
    use EnumHelpers;

    case Admin = 'admin';
    case Student = 'student';
}
