<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum PushTransport: string
{
    use EnumHelpers;

    case WebPush = 'webpush';
    case Expo = 'expo';
    case Apns = 'apns';
    case Fcm = 'fcm';
}
