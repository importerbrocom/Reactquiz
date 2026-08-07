<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Http\Middleware\ApiContractVersion;
use App\Http\Middleware\EnsureAccountIsUsable;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\IdempotencyKey;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackLastActive;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Applied to every API request, in order.
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
            ApiContractVersion::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'account' => EnsureAccountIsUsable::class,
            'idempotent' => IdempotencyKey::class,
            'track-active' => TrackLastActive::class,
            // Sanctum ships these but does not alias them in Laravel 11+.
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // Sanctum's stateful middleware is deliberately NOT enabled: this API is
        // token-authenticated so that the same endpoints serve the web PWA and the
        // React Native app. See docs/phase-1/08-security-architecture.md 3.1.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Every API failure returns the same envelope with a machine-readable code.
         * No stack traces, SQL, class names or file paths ever reach a client in
         * production — only a Sentry reference for support to correlate.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ApiException => $e->render(),

                $e instanceof ValidationException => ApiResponse::validationError(
                    $e->errors(),
                    'The given data was invalid.',
                ),

                $e instanceof AuthenticationException => ApiResponse::error(
                    'Unauthenticated.',
                    401,
                    'UNAUTHENTICATED',
                ),

                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => ApiResponse::error(
                    'You do not have permission to perform this action.',
                    403,
                    'FORBIDDEN',
                ),

                // 404 for both "missing" and "not yours", so ids cannot be probed
                // for existence.
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error(
                    'The requested resource was not found.',
                    404,
                    'NOT_FOUND',
                ),

                $e instanceof MethodNotAllowedHttpException => ApiResponse::error(
                    'This method is not supported for this endpoint.',
                    405,
                    'METHOD_NOT_ALLOWED',
                ),

                $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                    'Too many requests. Please slow down.',
                    429,
                    'RATE_LIMITED',
                    meta: ['retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 60)],
                ),

                default => app()->hasDebugModeEnabled()
                    ? null                       // let Laravel's debug renderer run locally
                    : ApiResponse::error(
                        'Something went wrong. Please try again.',
                        500,
                        'SERVER_ERROR',
                        // Correlation id only — support can find the full trace in
                        // the logs without anything internal reaching the client.
                        meta: ['reference' => $request->header('X-Request-Id')],
                    ),
            };
        });

        // Never log the contents of a request body that may hold credentials.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'token',
            'refresh_token',
        ]);
    })
    ->create();
