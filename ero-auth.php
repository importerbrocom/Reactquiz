<?php
/**
 * ERO — shared auth guard for the standalone PHP helpers.
 *
 * codes.php and save-questions.php sit next to the app in the document root,
 * outside Laravel. They used to trust whatever the caller sent, which meant
 * anyone on the internet could read every activation code (together with the
 * email of the student who redeemed it), delete or replace the whole code list,
 * and overwrite the entire question bank.
 *
 * Rather than invent a second credential, these helpers now verify the caller's
 * existing Sanctum bearer token by asking the API who it belongs to. The API is
 * the only thing that can answer that, so there is no new secret to leak and no
 * second source of truth for roles.
 *
 * Because the check is an HTTP call back to this same site, the web server has to
 * be able to serve a second request while the first is still open. PHP-FPM and
 * mod_php do that as a matter of course; PHP's single-threaded built-in server
 * does not, so set PHP_CLI_SERVER_WORKERS=4 if you are testing with it.
 *
 * Optional environment override:
 *   ERO_API_BASE  full base URL of the v1 API, e.g. https://example.com/api/v1
 *                 Set this if the site cannot reach its own public hostname.
 */

declare(strict_types=1);

/** Emit JSON headers. CORS is same-origin only; these helpers are not a public API. */
function ero_json_headers(): void
{
    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin === ero_own_origin()) {
        header('Access-Control-Allow-Origin: '.$origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

function ero_scheme(): string
{
    // Behind Cloudflare the origin often sees plain http, so the forwarded
    // header decides. Getting this wrong would build an unreachable self-URL.
    $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    if (is_string($forwarded) && $forwarded !== '') {
        return str_contains($forwarded, 'https') ? 'https' : 'http';
    }

    $https = $_SERVER['HTTPS'] ?? '';

    return ($https !== '' && strtolower((string) $https) !== 'off') ? 'https' : 'http';
}

function ero_own_origin(): string
{
    return ero_scheme().'://'.($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function ero_api_base(): string
{
    $override = getenv('ERO_API_BASE');
    if (is_string($override) && trim($override) !== '') {
        return rtrim(trim($override), '/');
    }

    return ero_own_origin().'/api/v1';
}

/** The caller's bearer token, or null. */
function ero_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    // Some CGI/FastCGI setups strip Authorization from $_SERVER.
    if ($header === '') {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }
    if ($header === '' && function_exists('apache_request_headers')) {
        foreach ((array) apache_request_headers() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) {
                $header = (string) $value;
                break;
            }
        }
    }

    if (preg_match('/^Bearer\s+(\S+)$/i', trim((string) $header), $m) === 1) {
        return $m[1];
    }

    return null;
}

/**
 * Resolve the caller via GET /auth/me. Memoised: one round trip per request.
 *
 * @return array<string, mixed>|null
 */
function ero_current_user(): ?array
{
    static $resolved = false;
    static $user = null;

    if ($resolved) {
        return $user;
    }
    $resolved = true;

    $token = ero_bearer_token();
    if ($token === null || ! function_exists('curl_init')) {
        return $user = null;
    }

    $ch = curl_init(ero_api_base().'/auth/me');
    if ($ch === false) {
        return $user = null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer '.$token,
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200 || ! is_string($body)) {
        return $user = null;
    }

    $decoded = json_decode($body, true);
    $candidate = $decoded['data']['user'] ?? null;

    if (! is_array($candidate) || empty($candidate['email'])) {
        return $user = null;
    }

    return $user = $candidate;
}

function ero_is_admin(?array $user): bool
{
    $role = strtolower((string) ($user['role'] ?? ''));

    return $role === 'admin' || $role === 'super-admin';
}

/**
 * Any signed-in account.
 *
 * @return array<string, mixed>
 */
function ero_require_user(): array
{
    $user = ero_current_user();

    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

    return $user;
}

/**
 * Admins only.
 *
 * @return array<string, mixed>
 */
function ero_require_admin(): array
{
    $user = ero_require_user();

    if (! ero_is_admin($user)) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }

    return $user;
}

/** Answer a CORS preflight and stop. */
function ero_handle_preflight(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
