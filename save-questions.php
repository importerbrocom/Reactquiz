<?php
/**
 * ERO - Save questions to server
 * This file saves uploaded questions as a JSON file so all students can access them.
 * Place this file in the same directory as index.html on your server.
 *
 * Admin only. This endpoint overwrites the entire question bank, and it used to
 * accept any request at all — an unauthenticated POST could wipe or replace every
 * question on the site.
 */

require_once __DIR__.'/ero-auth.php';

ero_json_headers();
ero_handle_preflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

ero_require_admin();

// Read the JSON body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['questions']) || !is_array($data['questions'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data. Expected {"questions": [...]}']);
    exit;
}

$questions = $data['questions'];
$count = count($questions);

// Save questions to a JSON file. Written to a temp file and renamed so an
// interrupted upload cannot leave a truncated question bank behind.
$filePath = __DIR__ . '/questions.json';
$json = json_encode($questions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$tmpPath = $filePath . '.tmp' . getmypid();

if ($json === false
    || file_put_contents($tmpPath, $json, LOCK_EX) === false
    || !rename($tmpPath, $filePath)) {
    @unlink($tmpPath);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save questions file']);
    exit;
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => "Successfully saved {$count} questions",
    'count' => $count
]);
