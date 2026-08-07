<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum QueueName: string
{
    use EnumHelpers;

    case Critical = 'critical';
    case Default = 'default';
    case Notifications = 'notifications';
    case Mail = 'mail';
    case Imports = 'imports';
    case Reports = 'reports';
}
