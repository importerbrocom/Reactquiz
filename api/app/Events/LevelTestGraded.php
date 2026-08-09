<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LevelTestAttempt;
use Illuminate\Foundation\Events\Dispatchable;

final class LevelTestGraded
{
    use Dispatchable;

    public function __construct(public readonly LevelTestAttempt $attempt) {}
}
