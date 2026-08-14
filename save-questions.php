<?php
/**
 * QuizPath - Save questions to server
 * This file saves uploaded questions as a JSON file so all students can access them.
 * Place this file in the same directory as admin-dashboard.html on your server.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

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

// Save questions to a JSON file
$filePath = __DIR__ . '/questions.json';
$result = file_put_contents($filePath, json_encode($questions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if ($result === false) {
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
