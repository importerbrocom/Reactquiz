<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureUrls();
        $this->configurePasswords();
        $this->configureRateLimiting();
        $this->configureNotificationUrls();
    }

    /**
     * Auth emails must land the user in the SPA, not on a JSON endpoint.
     *
     * The signed API path is generated relative and handed to the frontend, which
     * replays it against the API. Signing the relative path keeps the signature
     * valid regardless of which origin serves the app.
     */
    private function configureNotificationUrls(): void
    {
        VerifyEmail::createUrlUsing(function (User $user): string {
            $path = URL::temporarySignedRoute(
                'api.v1.auth.verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
                absolute: false,
            );

            return rtrim((string) config('app.frontend_url'), '/')
                .'/verify-email?path='.urlencode($path);
        });

        ResetPassword::createUrlUsing(fn (object $user, string $token): string => rtrim((string) config('app.frontend_url'), '/')
            .'/reset-password?token='.$token
            .'&email='.urlencode($user->getEmailForPasswordReset()));
    }

    /**
     * Strict mode outside production turns silent performance and correctness bugs
     * into loud test failures:
     *   - preventLazyLoading      => an N+1 fails the test suite instead of shipping
     *   - preventSilentlyDiscarding => a typo'd attribute throws instead of vanishing
     *   - preventAccessingMissing => reading an unloaded column throws
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
    }

    private function configureUrls(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    private function configurePasswords(): void
    {
        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()->uncompromised()
            : Password::min(8));
    }

    /**
     * Named limiters referenced by route middleware. Keeping them here rather than
     * inline on routes means the whole rate-limit policy is reviewable in one place.
     */
    private function configureRateLimiting(): void
    {
        // Login and password reset: per email + IP, so one attacker cannot lock out
        // an entire office NAT, and one IP cannot spray many accounts.
        RateLimiter::for('auth-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('auth-register', fn (Request $request): Limit => Limit::perHour(3)
            ->by($request->ip()));

        RateLimiter::for('auth-refresh', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // A human answers at most ~20 questions a minute; the headroom exists so an
        // offline outbox can drain a backlog without being throttled.
        RateLimiter::for('quiz-write', fn (Request $request): Limit => Limit::perMinute(
            (int) config('quiz.answer_rate_limit', 60),
        )->by($this->userKey($request)));

        RateLimiter::for('test-sync', fn (Request $request): Limit => Limit::perMinute(30)
            ->by($this->userKey($request)));

        RateLimiter::for('upload', fn (Request $request): Limit => Limit::perHour(10)
            ->by($this->userKey($request)));

        RateLimiter::for('reports', fn (Request $request): Limit => Limit::perHour(20)
            ->by($this->userKey($request)));

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(180)
            ->by($this->userKey($request)));

        RateLimiter::for('public', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->ip()));
    }

    private function userKey(Request $request): string
    {
        $user = $request->user();

        return $user instanceof User
            ? 'u'.$user->getKey()
            : (string) $request->ip();
    }
}
