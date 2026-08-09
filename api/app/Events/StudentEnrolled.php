<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LevelEnrollment;
use App\Models\ProgrammeEnrollment;
use Illuminate\Foundation\Events\Dispatchable;

final class StudentEnrolled
{
    use Dispatchable;

    public function __construct(
        public readonly ProgrammeEnrollment $programmeEnrolment,
        public readonly LevelEnrollment $levelEnrolment,
    ) {}
}
