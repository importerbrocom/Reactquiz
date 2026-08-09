<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Quiz\SubmitAnswerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quiz\SubmitAnswerRequest;
use App\Services\Student\StudentContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * The hottest endpoint in the product: one answer, one verdict.
 *
 * The response is marked no-store. A cached verdict would let the service worker — or
 * any proxy in between — serve a student the correct answer for a question they are
 * about to be asked again.
 */
final class AnswerController extends Controller
{
    public function __construct(
        private readonly StudentContext $context,
        private readonly SubmitAnswerAction $submit,
    ) {}

    public function store(SubmitAnswerRequest $request, string $attempt): JsonResponse
    {
        $model = $this->context->quizAttempt($request->user(), $attempt);
        $result = ($this->submit)($model, $request->toData());

        return ApiResponse::sensitive(
            $result->toArray(),
            $result->isCorrect ? 'Correct.' : 'Not quite — try again.',
        );
    }
}
