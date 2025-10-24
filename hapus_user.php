<?php
require 'config.php';
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id || !is_numeric($id)) {
    die('ID tidak valid.');
}

// Hanya bisa hapus akun sendiri, kecuali admin yang bisa hapus semua
if (!is_admin() && $id != $_SESSION['userid']) {
    die('Anda hanya bisa menghapus akun Anda sendiri.');
}

// Cegah admin menghapus dirinya sendiri
if (is_admin() && $id == $_SESSION['userid']) {
    die('Tidak bisa menghapus akun admin yang sedang login.');
}

// Hapus user/guru/admin
$stmt = $pdo->prepare("DELETE FROM user WHERE id = ?");
$stmt->execute([$id]);

// Jika hapus akun sendiri, logout
if ($id == $_SESSION['userid']) {
    logout();
    header('Location: index.php');
    exit;
}

header('Location: dashboard.php');
exit;
?>
