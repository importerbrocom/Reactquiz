<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Concerns\HandlesRefreshTokens;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\OnboardingPreference;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Support\ActivityLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class RegisterController
{
    use HandlesRefreshTokens;

    public function __construct(
        private readonly TokenService $tokens,
        private readonly ActivityLogger $activity,
    ) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            // Defaults are supplied explicitly rather than left to the database, so
            // the in-memory model is complete straight after create(). Relying on DB
            // defaults leaves attributes unhydrated, which then either reads as null
            // (and violates a NOT NULL column downstream) or throws under
            // Model::preventAccessingMissingAttributes.
            $user = User::query()->create([
                ...$request->safe()->only(['name', 'email', 'password']),
                'timezone' => $request->validated('timezone', config('quiz.default_timezone', 'Asia/Kolkata')),
                'locale' => $request->validated('locale', config('app.locale', 'en')),
            ]);

            // Role is assigned server-side and is never accepted from the request.
            $user->forceFill([
                'role' => UserRole::Student,
                'status' => UserStatus::PendingVerification,
            ])->save();

            $user->assignRole(UserRole::Student->value);

            OnboardingPreference::query()->create([
                'user_id' => $user->getKey(),
                'step' => 'welcome',
                'timezone' => $user->timezone,
                'language' => $user->locale,
            ]);

            return $user;
        });

        event(new Registered($user));

        $this->activity->log('auth.registered', $user, subject: $user);

        return $this->respondWithTokens(
            $request,
            [...$this->tokens->issue($user, $request), 'user' => $user],
            'Account created. Please verify your email address.',
            201,
        );
    }
}
