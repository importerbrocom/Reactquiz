<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Advertises the API contract version on every response.
 *
 * The web bundle knows the contract it was built against; a mismatch tells it to
 * update the service worker and reload, which is what stops a stale cached bundle
 * from talking to a newer API.
 */
final class ApiContractVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Api-Contract', (string) config('security.api_contract_version'));

        return $response;
    }
}
