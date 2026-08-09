<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Quiz\AdvanceLevelAction;
use App\Http\Controllers\Controller;
use App\Services\Quiz\LevelProgressionService;
use App\Services\Student\StudentContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Moving to next month.
 */
final class LevelProgressionController extends Controller
{
    public function __construct(
        private readonly StudentContext $context,
        private readonly LevelProgressionService $progression,
        private readonly AdvanceLevelAction $advance,
    ) {}

    /** Can I move on, and to what? */
    public function show(Request $request): JsonResponse
    {
        $enrolment = $this->context->currentLevelEnrolment($request->user());

        return ApiResponse::success($this->progression->decide($enrolment)->toArray());
    }

    /** Move on. Safe to repeat: a second call returns the same new enrolment. */
    public function store(Request $request): JsonResponse
    {
        $enrolment = $this->context->currentLevelEnrolment($request->user());
        $next = ($this->advance)($enrolment);

        return ApiResponse::success([
            'level_enrollment_uuid' => $next->uuid,
            'level_number' => $next->level->level_number,
            'cycle_number' => $next->cycle_number,
            'total_days' => $next->level->total_quiz_days,
            'questions_assigned' => $next->questionsAreAssigned(),
        ], 'Level '.$next->level->level_number.' is ready.');
    }
}
