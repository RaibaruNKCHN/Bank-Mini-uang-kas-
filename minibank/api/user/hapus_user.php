<?php
// minibank/api/user/hapus_user.php
// Single clean implementation: POST-only, CSRF-protected, admin-only delete.
require __DIR__ . '/../../includes/config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Permintaan tidak valid.'];

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Metode tidak diizinkan. Gunakan POST.';
    echo json_encode($response);
    exit;
}

// Must be admin
if (!is_admin()) {
    http_response_code(403);
    $response['message'] = 'Akses ditolak.';
    echo json_encode($response);
    exit;
}

// CSRF validation
$csrf = $_POST['csrf'] ?? '';
if (!function_exists('verify_csrf') || !verify_csrf($csrf)) {
    http_response_code(400);
    $response['message'] = 'Token CSRF tidak valid.';
    echo json_encode($response);
    exit;
}

// Parse and validate id
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $response['message'] = 'ID tidak valid.';
    echo json_encode($response);
    exit;
}

// Prevent self-deletion
if ($id == ($_SESSION['userid'] ?? 0)) {
    $response['message'] = 'Tidak bisa menghapus akun yang sedang login.';
    echo json_encode($response);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';

$deleted = false;
$deleted_from = null;
try {
    // Prefer deleting admins if the id exists there
    $exists_admin = db_query_one("SELECT id, username FROM admins WHERE id = ?", [$id]);
    if ($exists_admin) {
        $deleted = db_execute("DELETE FROM admins WHERE id = ?", [$id]);
        $deleted_from = 'admins';
    } else {
        $exists_user = db_query_one("SELECT id, username FROM users WHERE id = ?", [$id]);
        if ($exists_user) {
            $deleted = db_execute("DELETE FROM users WHERE id = ?", [$id]);
            $deleted_from = 'users';
        } else {
            $response['message'] = 'User tidak ditemukan.';
            echo json_encode($response);
            exit;
        }
    }
} catch (Exception $e) {
    error_log('hapus_user exception: ' . $e->getMessage());
    $response['message'] = 'Terjadi kesalahan server.';
    echo json_encode($response);
    exit;
}

if ($deleted) {
    $response['success'] = true;
    $response['message'] = ($deleted_from === 'admins') ? 'Admin berhasil dihapus.' : 'User berhasil dihapus.';

    // Audit log: append a single-line JSON record for easy parsing
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logFile = $logDir . '/admin_actions.log';
    $logEntry = [
        'ts' => date('c'),
        'admin_id' => $_SESSION['userid'] ?? null,
        'admin_username' => $_SESSION['username'] ?? null,
        'action' => 'delete_user',
        'target_id' => $id,
        'target_table' => $deleted_from,
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ];
    @file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

    echo json_encode($response);
    exit;
} else {
    $response['message'] = 'Gagal menghapus dari database.';
    echo json_encode($response);
    exit;
}

?>
 
