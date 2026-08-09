<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LevelEnrollment;
use Illuminate\Foundation\Events\Dispatchable;

final class LevelAdvanced
{
    use Dispatchable;

    public function __construct(
        public readonly LevelEnrollment $from,
        public readonly LevelEnrollment $to,
    ) {}
}
