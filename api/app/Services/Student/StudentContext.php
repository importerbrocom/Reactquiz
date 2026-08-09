<?php

declare(strict_types=1);

namespace App\Services\Student;

use App\Enums\EnrollmentStatus;
use App\Exceptions\EnrolmentInactiveException;
use App\Exceptions\OnboardingRequiredException;
use App\Models\LevelEnrollment;
use App\Models\LevelTestAttempt;
use App\Models\ProgrammeEnrollment;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves "which student, which level, which attempt" for every student endpoint.
 *
 * Every lookup starts from the authenticated user, so an attempt belonging to someone
 * else is not merely forbidden — it is invisible, and comes back as a 404. That is
 * deliberate: a 403 would confirm the id exists, which is enough to enumerate other
 * students' attempts. No controller queries QuizAttempt or LevelTestAttempt directly.
 */
final class StudentContext
{
    public function programmeEnrolment(User $user): ProgrammeEnrollment
    {
        $enrolment = $user->programmeEnrollments()
            ->where('status', EnrollmentStatus::Active->value)
            ->with('programme')
            ->latest('id')
            ->first();

        if ($enrolment === null) {
            throw new OnboardingRequiredException(
                'You are not enrolled in a programme yet. Choose your exam to get started.',
                ['onboarding_required' => true],
            );
        }

        return $enrolment;
    }

    /**
     * The level the student is currently working on.
     *
     * Derived from the programme enrolment's pointer rather than "the newest level
     * enrolment", so an older level opened for revision does not silently become the
     * student's current month.
     */
    public function currentLevelEnrolment(User $user): LevelEnrollment
    {
        $programmeEnrolment = $this->programmeEnrolment($user);

        $enrolment = $user->levelEnrollments()
            ->where('programme_enrollment_id', $programmeEnrolment->getKey())
            ->where('cycle_number', $programmeEnrolment->current_cycle)
            ->whereHas('level', fn ($query) => $query
                ->where('level_number', $programmeEnrolment->current_level))
            ->with(['level', 'programmeEnrollment.programme'])
            ->first();

        if ($enrolment === null) {
            throw new EnrolmentInactiveException(
                'Your current level could not be found. Please contact support.',
                [
                    'current_level' => $programmeEnrolment->current_level,
                    'current_cycle' => $programmeEnrolment->current_cycle,
                ],
            );
        }

        if ($enrolment->status !== EnrollmentStatus::Active) {
            throw new EnrolmentInactiveException;
        }

        return $enrolment;
    }

    /**
     * A level enrolment the student may open, by level number.
     *
     * Used for revisiting a finished month: allowed, because retries are unlimited.
     */
    public function levelEnrolment(User $user, int $levelNumber): LevelEnrollment
    {
        $programmeEnrolment = $this->programmeEnrolment($user);

        /** @var LevelEnrollment */
        return $user->levelEnrollments()
            ->where('programme_enrollment_id', $programmeEnrolment->getKey())
            ->where('cycle_number', $programmeEnrolment->current_cycle)
            ->whereHas('level', fn ($query) => $query->where('level_number', $levelNumber))
            ->with(['level', 'programmeEnrollment.programme'])
            ->firstOrFail();
    }

    /** @throws ModelNotFoundException when the attempt is not this student's */
    public function quizAttempt(User $user, string $uuid): QuizAttempt
    {
        /** @var QuizAttempt */
        return $user->quizAttempts()
            ->where('uuid', $uuid)
            ->with(['level', 'levelEnrollment.programmeEnrollment'])
            ->firstOrFail();
    }

    /** @throws ModelNotFoundException when the attempt is not this student's */
    public function testAttempt(User $user, string $uuid): LevelTestAttempt
    {
        /** @var LevelTestAttempt */
        return $user->levelTestAttempts()
            ->where('uuid', $uuid)
            ->with(['levelTest', 'levelEnrollment.level'])
            ->firstOrFail();
    }
}
