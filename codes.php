<?php
/**
 * ERO - Activation Codes API
 * Handles saving, reading, and validating activation codes on the server.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$codesFile = __DIR__ . '/activation-codes.json';

// Load existing codes
function loadCodes() {
    global $codesFile;
    if (!file_exists($codesFile)) return [];
    $data = json_decode(file_get_contents($codesFile), true);
    return is_array($data) ? $data : [];
}

// Save codes
function saveCodes($codes) {
    global $codesFile;
    file_put_contents($codesFile, json_encode($codes, JSON_PRETTY_PRINT));
}

// GET - return all codes (for admin)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'validate') {
        $code = $_GET['code'] ?? '';
        $codes = loadCodes();
        $found = false;
        foreach ($codes as $c) {
            if ($c['code'] === $code && !$c['used']) {
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
     * student look brand new and they were asked for a code again. This lets the
     * client ask the server instead, so activation follows the account.
     */
    if ($action === 'status') {
        $email = trim($_GET['email'] ?? '');
        if ($email === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Email is required']);
            exit;
        }
        $activated = false;
        $activatedAt = null;
        foreach (loadCodes() as $c) {
            $usedBy = $c['usedBy'] ?? null;
            // Case-insensitive: emails are not stored normalised.
            if (!empty($c['used']) && is_string($usedBy) && strcasecmp($usedBy, $email) === 0) {
                $activated = true;
                $activatedAt = $c['usedAt'] ?? null;
                break;
            }
        }
        echo json_encode(['activated' => $activated, 'activatedAt' => $activatedAt]);
        exit;
    }
    
    echo json_encode(['codes' => loadCodes()]);
    exit;
}

// POST - save codes or mark as used
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'save';

    if ($action === 'save') {
        // Save new codes from admin
        $newCodes = $input['codes'] ?? [];
        if (!is_array($newCodes)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid codes data']);
            exit;
        }
        $existing = loadCodes();
        $merged = array_merge($existing, $newCodes);
        saveCodes($merged);
        echo json_encode(['success' => true, 'count' => count($merged)]);
        exit;
    }

    if ($action === 'use') {
        // Mark a code as used
        $code = $input['code'] ?? '';
        $email = $input['email'] ?? '';
        $codes = loadCodes();
        $valid = false;
        foreach ($codes as &$c) {
            if ($c['code'] === $code && !$c['used']) {
                $c['used'] = true;
                $c['usedBy'] = $email;
                $c['usedAt'] = date('c');
                $valid = true;
                break;
            }
        }
        if ($valid) {
            saveCodes($codes);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or already used code']);
        }
        exit;
    }

    if ($action === 'delete') {
        $code = $input['code'] ?? '';
        $codes = loadCodes();
        $codes = array_values(array_filter($codes, function($c) use ($code) {
            return $c['code'] !== $code;
        }));
        saveCodes($codes);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'replace') {
        // Replace all codes (full sync from admin)
        $codes = $input['codes'] ?? [];
        saveCodes($codes);
        echo json_encode(['success' => true, 'count' => count($codes)]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
