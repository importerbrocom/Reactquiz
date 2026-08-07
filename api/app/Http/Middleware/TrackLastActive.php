<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Maintains users.last_active_at for the "daily active students" metric, throttled
 * to at most one write per user per 5 minutes so it never becomes a write amplifier
 * on the hottest table.
 */
final class TrackLastActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user !== null) {
            $throttleKey = "last-active:{$user->getKey()}";

            if (Cache::add($throttleKey, true, now()->addMinutes(5))) {
                $user->forceFill(['last_active_at' => now()])->saveQuietly();
            }
        }

        return $response;
    }
}
