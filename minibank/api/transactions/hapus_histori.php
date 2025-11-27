<?php
require '../../includes/config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Permintaan tidak valid.'];

if (!is_admin()) {
    $response['message'] = 'Akses ditolak.';
    echo json_encode($response);
    exit;
}

$id = $_GET['id'] ?? '';

 $id = $_GET['id'] ?? '';

// Ensure integer id
 $id = (int)$id;
 if ($id <= 0) {
    $response['message'] = 'ID transaksi tidak valid.';
} else {
    // Cek dulu apakah transaksi ada
    require_once __DIR__ . '/../../includes/db.php';
    $exists = db_query_one("SELECT id FROM transaksi WHERE id = ?", [$id]);
    if (!$exists) {
        $response['message'] = 'Transaksi tidak ditemukan.';
    } else {
        if (db_execute("DELETE FROM transaksi WHERE id = ?", [$id])) {
            $response['success'] = true;
            $response['message'] = 'Transaksi berhasil dihapus.';
        } else {
            $response['message'] = 'Gagal menghapus transaksi dari database.';
        }
    }
}

echo json_encode($response);
exit;
?>
