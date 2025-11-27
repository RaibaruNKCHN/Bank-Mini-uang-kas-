<?php
require_once __DIR__ . '/../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
// CSRF check
if (!verify_csrf($_POST['csrf'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
    exit;
}

$settingsFile = __DIR__ . '/../../includes/settings.json';
$settings = json_decode(file_get_contents($settingsFile), true) ?? ['timeout_enabled' => true, 'timeout_seconds' => 300];

if ($action === 'toggle_timeout') {
    // Admin can toggle on/off
    $enable = isset($_POST['enable']) && ($_POST['enable'] === '1' || $_POST['enable'] === 'true');
    $settings['timeout_enabled'] = (bool)$enable;
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'message' => 'Timeout setting updated', 'settings' => $settings]);
    exit;
}

if ($action === 'set_timeout') {
    // Changing the numeric timeout requires superadmin code
    $code = $_POST['super_code'] ?? '';
    $newSeconds = intval($_POST['seconds'] ?? 0);
    if ($code !== ADMIN_CODE_ALL_TRANSACTIONS) {
        echo json_encode(['success' => false, 'message' => 'Kode superadmin salah']);
        exit;
    }
    if ($newSeconds <= 0) {
        echo json_encode(['success' => false, 'message' => 'Nilai waktu tidak valid']);
        exit;
    }
    $settings['timeout_seconds'] = $newSeconds;
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'message' => 'Timeout seconds updated', 'settings' => $settings]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
