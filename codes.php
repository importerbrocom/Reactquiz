<?php
/**
 * ERO - Activation Codes API
 *
 * Every action requires a valid Sanctum bearer token (see ero-auth.php).
 * Previously nothing was checked at all, so an unauthenticated GET returned the
 * whole code list along with the email of each student who had redeemed one, and
 * an unauthenticated POST could delete or replace every code.
 *
 *   admin only  : list, save, replace, delete
 *   signed in   : validate, status, use
 *
 * For student actions the account is taken from the token, never from the
 * request, so one student cannot check or burn another student's activation.
 */

declare(strict_types=1);

require_once __DIR__.'/ero-auth.php';

ero_json_headers();
ero_handle_preflight();

$codesFile = __DIR__.'/activation-codes.json';

/** @return array<int, array<string, mixed>> */
function loadCodes(): array
{
    global $codesFile;

    if (! file_exists($codesFile)) {
        return [];
    }

    $data = json_decode((string) file_get_contents($codesFile), true);

    return is_array($data) ? $data : [];
}

/** @param array<int, array<string, mixed>> $codes */
function saveCodes(array $codes): bool
{
    global $codesFile;

    // Write to a temp file and rename, so a crash mid-write cannot truncate the
    // list and lose every student's activation record.
    $tmp = $codesFile.'.tmp'.getmypid();
    $json = json_encode($codes, JSON_PRETTY_PRINT);

    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);

        return false;
    }

    if (! rename($tmp, $codesFile)) {
        @unlink($tmp);

        return false;
    }

    return true;
}

/**
 * Run a read-modify-write under an exclusive lock.
 *
 * Redeeming used to be a bare read-then-write, so two students submitting at the
 * same time could both be told they succeeded while only one was recorded.
 *
 * @param  callable(array<int, array<string, mixed>>): array{0: bool, 1: array<int, array<string, mixed>>, 2: mixed}  $mutator
 * @return array{0: bool, 1: mixed}
 */
function withCodesLocked(callable $mutator): array
{
    global $codesFile;

    $lock = @fopen($codesFile.'.lock', 'c');
    if ($lock !== false) {
        flock($lock, LOCK_EX);
    }

    try {
        [$changed, $codes, $result] = $mutator(loadCodes());

        if ($changed && ! saveCodes($codes)) {
            return [false, $result];
        }

        return [true, $result];
    } finally {
        if ($lock !== false) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

function failValidation(string $message): never
{
    http_response_code(400);
    echo json_encode(['error' => $message]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ----------------------------------------------------------------- GET ----
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'validate') {
        // Signed in only: otherwise codes can be brute forced anonymously.
        ero_require_user();

        $code = strtoupper(trim((string) ($_GET['code'] ?? '')));
        if ($code === '') {
            failValidation('Code is required');
        }

        $found = false;
        foreach (loadCodes() as $c) {
            if (strcasecmp((string) ($c['code'] ?? ''), $code) === 0 && empty($c['used'])) {
                $found = true;
                break;
            }
        }

        echo json_encode(['valid' => $found]);
        exit;
    }

    /*
     * Has this account already redeemed a code?
     *
     * The client used to record activation only in localStorage, so a reinstall,
     * a second device, or iOS clearing the PWA's storage made an activated
     * student look brand new and they were asked for a code again. Asking the
     * server instead makes activation follow the account.
     */
    if ($action === 'status') {
        $user = ero_require_user();

        // The email comes from the token. Admins may look up another account;
        // a student asking about someone else just gets their own answer.
        $email = (string) $user['email'];
        $requested = trim((string) ($_GET['email'] ?? ''));
        if ($requested !== '' && ero_is_admin($user)) {
            $email = $requested;
        }

        $activated = false;
        $activatedAt = null;
        foreach (loadCodes() as $c) {
            $usedBy = $c['usedBy'] ?? null;
            // Case-insensitive: stored emails are not normalised.
            if (! empty($c['used']) && is_string($usedBy) && strcasecmp($usedBy, $email) === 0) {
                $activated = true;
                $activatedAt = $c['usedAt'] ?? null;
                break;
            }
        }

        echo json_encode(['activated' => $activated, 'activatedAt' => $activatedAt]);
        exit;
    }

    if ($action === 'list') {
        ero_require_admin();
        echo json_encode(['codes' => loadCodes()]);
        exit;
    }

    // Anything unrecognised used to fall through to the full list. It must not.
    failValidation('Unknown action');
}

// ---------------------------------------------------------------- POST ----
if ($method === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $action = $input['action'] ?? null;

    if ($action === 'save') {
        ero_require_admin();

        $newCodes = $input['codes'] ?? [];
        if (! is_array($newCodes)) {
            failValidation('Invalid codes data');
        }

        [$okWrite, $count] = withCodesLocked(static function (array $codes) use ($newCodes): array {
            $merged = array_merge($codes, $newCodes);

            return [true, $merged, count($merged)];
        });

        if (! $okWrite) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save codes']);
            exit;
        }

        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }

    if ($action === 'use') {
        $user = ero_require_user();
        // Always the caller's own account, whatever the body claims.
        $email = (string) $user['email'];

        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if ($code === '') {
            failValidation('Code is required');
        }

        [$okWrite, $redeemed] = withCodesLocked(static function (array $codes) use ($code, $email): array {
            // Already activated: redeeming again would burn a second code.
            foreach ($codes as $c) {
                $usedBy = $c['usedBy'] ?? null;
                if (! empty($c['used']) && is_string($usedBy) && strcasecmp($usedBy, $email) === 0) {
                    return [false, $codes, true];
                }
            }

            foreach ($codes as $i => $c) {
                if (strcasecmp((string) ($c['code'] ?? ''), $code) === 0 && empty($c['used'])) {
                    $codes[$i]['used'] = true;
                    $codes[$i]['usedBy'] = $email;
                    $codes[$i]['usedAt'] = date('c');

                    return [true, $codes, true];
                }
            }

            return [false, $codes, false];
        });

        if (! $redeemed) {
            failValidation('Invalid or already used code');
        }

        if (! $okWrite) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to record activation']);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        ero_require_admin();

        $code = (string) ($input['code'] ?? '');
        if ($code === '') {
            failValidation('Code is required');
        }

        [$okWrite] = withCodesLocked(static function (array $codes) use ($code): array {
            $kept = array_values(array_filter(
                $codes,
                static fn (array $c): bool => strcasecmp((string) ($c['code'] ?? ''), $code) !== 0,
            ));

            return [true, $kept, null];
        });

        if (! $okWrite) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete code']);
            exit;
        }

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'replace') {
        ero_require_admin();

        $codes = $input['codes'] ?? null;
        if (! is_array($codes)) {
            failValidation('Invalid codes data');
        }

        if (! saveCodes($codes)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save codes']);
            exit;
        }

        echo json_encode(['success' => true, 'count' => count($codes)]);
        exit;
    }

    failValidation('Unknown action');
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
