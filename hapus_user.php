<?php
require 'config.php';
if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id || !is_numeric($id)) {
    die('ID user/guru tidak valid.');
}

// Cegah admin menghapus dirinya sendiri
if ($id == $_SESSION['userid']) {
    die('Tidak bisa menghapus akun admin yang sedang login.');
}

// Hapus user/guru
$stmt = $pdo->prepare("DELETE FROM user WHERE id = ? AND role IN ('user','guru')");
$stmt->execute([$id]);

header('Location: dashboard.php');
exit;
?>