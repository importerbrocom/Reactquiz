<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CurrentUserController
{
    /** GET /auth/me — everything the client needs to bootstrap a session. */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('onboarding');

        $activeEnrolment = $user->programmeEnrollments()
            ->active()
            ->with('programme:id,title,slug,total_levels,total_cycles')
            ->first();

        return ApiResponse::success([
            'user' => (new UserResource($user))->toArray($request),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'onboarding' => $user->onboarding === null ? null : [
                'step' => $user->onboarding->step,
                'completed' => $user->onboarding->isComplete(),
                'selected_exam_category_id' => $user->onboarding->selected_exam_category_id,
                'selected_programme_id' => $user->onboarding->selected_programme_id,
            ],
            'active_enrolment' => $activeEnrolment === null ? null : [
                'id' => $activeEnrolment->uuid,
                'programme' => [
                    // Programmes are addressed by slug, not uuid — they are public
                    // catalogue records rather than per-student ones.
                    'slug' => $activeEnrolment->programme->slug,
                    'title' => $activeEnrolment->programme->title,
                    'total_levels' => $activeEnrolment->programme->total_levels,
                ],
                'current_cycle' => $activeEnrolment->current_cycle,
                'current_level' => $activeEnrolment->current_level,
            ],
        ], 'Authenticated user retrieved.');
    }

    /** PATCH /auth/me */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->fill($request->safe()->all())->save();

        return ApiResponse::success(
            (new UserResource($user->refresh()))->toArray($request),
            'Profile updated.',
        );
    }
}
