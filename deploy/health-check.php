<?php
/**
 * ERO Health Check - place at /home/uddjzwrz/mediprep.nokkoo.in/health.php
 * Access at: https://mediprep.nokkoo.in/health.php
 * Use with uptime monitoring (UptimeRobot, Better Stack, etc.)
 */

header('Content-Type: application/json');

$checks = [];
$healthy = true;

// 1. PHP version check
$checks['php'] = ['version' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '8.3.0', '>=')];

// 2. Database connection
try {
    $env = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_RAW);
    $pdo = new PDO(
        "mysql:host={$env['DB_HOST']};dbname={$env['DB_DATABASE']}",
        $env['DB_USERNAME'],
        $env['DB_PASSWORD'],
        [PDO::ATTR_TIMEOUT => 5]
    );
    $stmt = $pdo->query('SELECT COUNT(*) as c FROM users');
    $users = $stmt->fetch(PDO::FETCH_ASSOC)['c'];
    $checks['database'] = ['ok' => true, 'users' => (int)$users];
} catch (Exception $e) {
    $checks['database'] = ['ok' => false, 'error' => $e->getMessage()];
    $healthy = false;
}

// 3. Storage writable
$storageOk = is_writable(__DIR__ . '/storage/logs');
$checks['storage'] = ['ok' => $storageOk];
if (!$storageOk) $healthy = false;

// 4. Disk space
$free = disk_free_space('/home/uddjzwrz');
$checks['disk'] = ['ok' => $free > 1073741824, 'free_gb' => round($free / 1073741824, 1)]; // > 1GB

// 5. Questions loaded
try {
    if (isset($pdo)) {
        $stmt = $pdo->query('SELECT COUNT(*) as c FROM questions');
        $questions = $stmt->fetch(PDO::FETCH_ASSOC)['c'];
        $checks['questions'] = ['ok' => $questions > 0, 'count' => (int)$questions];
    } else {
        $checks['questions'] = ['ok' => false, 'error' => 'No DB connection'];
    }
} catch (Exception $e) {
    $checks['questions'] = ['ok' => false];
}

// 6. Cache directory
$cacheOk = is_dir(__DIR__ . '/storage/framework/cache/data');
$checks['cache'] = ['ok' => $cacheOk];

http_response_code($healthy ? 200 : 503);
echo json_encode([
    'status' => $healthy ? 'healthy' : 'unhealthy',
    'timestamp' => date('c'),
    'checks' => $checks
], JSON_PRETTY_PRINT);
