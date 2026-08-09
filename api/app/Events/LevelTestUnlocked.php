<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LevelEnrollment;
use Illuminate\Foundation\Events\Dispatchable;

final class LevelTestUnlocked
{
    use Dispatchable;

    public function __construct(public readonly LevelEnrollment $enrolment) {}
}
