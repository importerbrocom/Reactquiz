<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\LevelTest\SyncLevelTestAnswersAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quiz\SyncTestAnswersRequest;
use App\Services\Student\StudentContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Batched answer sync during the month-end test.
 *
 * This endpoint records choices and returns progress. It cannot tell the student
 * whether they were right, because the code behind it never reads the answer key —
 * see SyncLevelTestAnswersAction.
 */
final class LevelTestAnswerController extends Controller
{
    public function __construct(
        private readonly StudentContext $context,
        private readonly SyncLevelTestAnswersAction $sync,
    ) {}

    public function store(SyncTestAnswersRequest $request, string $attempt): JsonResponse
    {
        $model = $this->context->testAttempt($request->user(), $attempt);

        $result = ($this->sync)(
            $model,
            $request->toData(),
            $request->validated('client_batch_uuid'),
        );

        return ApiResponse::success($result->toArray(), 'Answers saved.');
    }
}
