<?php
require_once __DIR__ . '/../../includes/config.php';
header('Content-Type: application/json');

$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/error_reports.log';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept error reports and persist them for review
    if (!is_admin()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        exit;
    }

    $message = trim($_POST['message'] ?? '');
    $details = trim($_POST['details'] ?? '');
    if ($message === '') {
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        exit;
    }

    $entry = [
        'ts' => date('c'),
        'admin' => $_SESSION['username'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'message' => $message,
        'details' => $details
    ];

    file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    echo json_encode(['success' => true, 'message' => 'Reported']);
    exit;
}

// GET: return last 200 lines as JSON array for admins
if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$lines = [];
if (file_exists($logFile)) {
    $content = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($content !== false) {
        $last = array_slice($content, -200);
        foreach ($last as $l) {
            $decoded = json_decode($l, true);
            if ($decoded) $lines[] = $decoded;
        }
    }
}

echo json_encode(['success' => true, 'reports' => $lines]);
exit;
