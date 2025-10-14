<?php
require 'config.php';
if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id || !is_numeric($id)) {
    die('ID transaksi tidak valid.');
}

// Hapus transaksi
$stmt = $pdo->prepare("DELETE FROM transaksi WHERE id = ?");
$stmt->execute([$id]);

header('Location: dashboard.php');
exit;
?>
