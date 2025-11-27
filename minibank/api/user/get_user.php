<?php
require '../../includes/config.php';

header('Content-Type: application/json');

if (!is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

 $id = $_GET['id'] ?? null;
 if (!$id || !is_numeric($id)) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
    exit;
}
$id = (int)$id;
require_once __DIR__ . '/../../includes/db.php';
// Try admins first
$user = db_query_one("SELECT id, username FROM admins WHERE id = ?", [$id]);
if ($user) {
    $user['role'] = 'admin';
    echo json_encode([
        'success' => true,
        'user' => $user,
        'csrf_token' => csrf_token()
    ]);
    exit;
}

// Fallback to users
$user = db_query_one("SELECT id, username, role, rekening FROM users WHERE id = ?", [$id]);
if ($user) {
    echo json_encode([
        'success' => true,
        'user' => $user,
        'csrf_token' => csrf_token()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
}
