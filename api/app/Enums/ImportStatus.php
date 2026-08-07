<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ImportStatus: string
{
    use EnumHelpers;

    case Uploaded = 'uploaded';
    case Validating = 'validating';
    case NeedsReview = 'needs_review';
    case Importing = 'importing';
    case Completed = 'completed';
    case PartiallyCompleted = 'partially_completed';
    case Failed = 'failed';
}
