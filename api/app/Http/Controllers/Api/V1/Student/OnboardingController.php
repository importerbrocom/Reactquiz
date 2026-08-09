<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Enrolment\EnrolInProgrammeAction;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CompleteOnboardingRequest;
use App\Models\ExamCategory;
use App\Models\OnboardingPreference;
use App\Models\Programme;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * First-run setup: which exam, which programme.
 *
 * The exam list is driven entirely by data, because the client asked to be able to add
 * exam types himself ("admin can able to add multiple exam types"). Nothing here knows
 * that FMGE or AMC exist.
 */
final class OnboardingController extends Controller
{
    public function __construct(
        private readonly EnrolInProgrammeAction $enrol,
    ) {}

    /** What the student can choose from, and where they left off. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $preference = $user->onboarding;

        $categories = ExamCategory::query()
            ->where('status', ContentStatus::Active)
            ->with(['programmes' => fn ($query) => $query
                ->where('status', ContentStatus::Active)
                ->orderBy('title')])
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        return ApiResponse::success([
            'completed' => $preference?->completed_at !== null,
            'step' => $preference?->step ?? 'welcome',
            'selected' => [
                'exam_category_id' => $preference?->selected_exam_category_id,
                'programme_id' => $preference?->selected_programme_id,
            ],
            'exam_categories' => $categories->map(fn (ExamCategory $category): array => [
                'id' => $category->getKey(),
                'title' => $category->title,
                'description' => $category->description,
                'programmes' => $category->programmes->map(fn (Programme $programme): array => [
                    'id' => $programme->getKey(),
                    'title' => $programme->title,
                    'description' => $programme->description,
                    'total_levels' => $programme->total_levels,
                    'total_cycles' => $programme->total_cycles,
                ])->all(),
            ])->all(),
        ]);
    }

    /**
     * Finish onboarding and enrol.
     *
     * Safe to repeat: the enrolment action is idempotent, so a retried request from a
     * flaky connection cannot enrol the student twice or re-deal their questions.
     */
    public function store(CompleteOnboardingRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        /** @var Programme $programme */
        $programme = Programme::query()->findOrFail($validated['programme_id']);

        // The student's timezone drives streaks and daily unlocks, so it is stored on the
        // user rather than only in the onboarding record.
        if (($validated['timezone'] ?? null) !== null) {
            $user->forceFill(['timezone' => $validated['timezone']])->save();
        }

        OnboardingPreference::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'step' => 'complete',
                'selected_exam_category_id' => $validated['exam_category_id'],
                'selected_programme_id' => $programme->getKey(),
                'reminder_time' => $validated['reminder_time'] ?? null,
                'notifications_opt_in' => $validated['notifications_opt_in'] ?? false,
                'language' => $validated['language'] ?? 'en',
                'timezone' => $validated['timezone'] ?? $user->timezone,
                'study_goal' => $validated['study_goal'] ?? null,
                'daily_goal_minutes' => $validated['daily_goal_minutes'] ?? null,
                'completed_at' => now(),
            ],
        );

        $enrolment = ($this->enrol)($user, $programme);

        return ApiResponse::created([
            'programme_enrollment_uuid' => $enrolment->uuid,
            'programme' => [
                'id' => $programme->getKey(),
                'title' => $programme->title,
                'total_levels' => $programme->total_levels,
            ],
            'current_level' => $enrolment->current_level,
            'current_cycle' => $enrolment->current_cycle,
        ], 'You are enrolled. Day 1 is ready.');
    }
}
