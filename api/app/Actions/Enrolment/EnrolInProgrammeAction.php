<?php

declare(strict_types=1);

namespace App\Actions\Enrolment;

use App\Actions\Quiz\AssignLevelQuestionsAction;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Events\StudentEnrolled;
use App\Exceptions\EnrolmentInactiveException;
use App\Models\Level;
use App\Models\LevelEnrollment;
use App\Models\Programme;
use App\Models\ProgrammeEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Puts a student into a programme and opens level 1 for them.
 *
 * Idempotent by way of the unique index on (user_id, programme_id, is_active): a student
 * who taps "Start" twice, or whose onboarding request is retried, ends up with one
 * enrolment and one question deal. Enrolling twice would give them two different
 * assignments of the same 300 questions, which from their side would look like the app
 * shuffling their whole month at random.
 */
final readonly class EnrolInProgrammeAction
{
    public function __construct(
        private AssignLevelQuestionsAction $assign,
    ) {}

    public function __invoke(User $user, Programme $programme): ProgrammeEnrollment
    {
        if ($programme->status !== ContentStatus::Active) {
            throw new EnrolmentInactiveException('That programme is not open for enrolment.');
        }

        [$enrolment, $levelEnrolment, $isNew] = DB::transaction(function () use ($user, $programme): array {
            $existing = ProgrammeEnrollment::query()
                ->where('user_id', $user->getKey())
                ->where('programme_id', $programme->getKey())
                ->where('status', EnrollmentStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return [$existing, $this->levelOneEnrolment($existing), false];
            }

            $programmeEnrolment = new ProgrammeEnrollment;
            $programmeEnrolment->forceFill([
                'user_id' => $user->getKey(),
                'programme_id' => $programme->getKey(),
                'status' => EnrollmentStatus::Active,
                'is_active' => true,
                'current_cycle' => 1,
                'current_level' => 1,
                'enrolled_at' => now(),
                'started_at' => now(),
            ])->save();

            return [
                $programmeEnrolment->refresh(),
                $this->createLevelOneEnrolment($programmeEnrolment, $programme),
                true,
            ];
        });

        // Deal the questions outside the transaction: it is a 300-row bulk insert, and
        // holding the enrolment lock across it for no reason would serialise every
        // student who signs up in the same minute.
        if (! $levelEnrolment->questionsAreAssigned()) {
            ($this->assign)($levelEnrolment);
        }

        if ($isNew) {
            StudentEnrolled::dispatch($enrolment->refresh(), $levelEnrolment->refresh());
        }

        return $enrolment->refresh();
    }

    private function createLevelOneEnrolment(
        ProgrammeEnrollment $programmeEnrolment,
        Programme $programme,
    ): LevelEnrollment {
        /** @var Level $level */
        $level = Level::query()
            ->where('programme_id', $programme->getKey())
            ->where('level_number', 1)
            ->where('status', ContentStatus::Active)
            ->firstOr(function (): never {
                throw new EnrolmentInactiveException(
                    'This programme has no published first level yet.',
                );
            });

        $enrolment = new LevelEnrollment;
        $enrolment->forceFill([
            'programme_enrollment_id' => $programmeEnrolment->getKey(),
            'user_id' => $programmeEnrolment->user_id,
            'level_id' => $level->getKey(),
            'cycle_number' => 1,
            'status' => EnrollmentStatus::Active,
            'is_active' => true,
            'assignment_seed' => random_int(1, 2_000_000_000),
            'started_at' => now(),
        ])->save();

        return $enrolment->refresh();
    }

    private function levelOneEnrolment(ProgrammeEnrollment $programmeEnrolment): LevelEnrollment
    {
        /** @var LevelEnrollment */
        return LevelEnrollment::query()
            ->where('programme_enrollment_id', $programmeEnrolment->getKey())
            ->where('cycle_number', $programmeEnrolment->current_cycle)
            ->orderBy('id')
            ->firstOrFail();
    }
}
