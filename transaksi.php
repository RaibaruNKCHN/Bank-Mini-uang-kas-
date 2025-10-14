<?php
require 'config.php';
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!verify_csrf($_POST['csrf'] ?? '')) {
    die('CSRF token invalid.');
}

$type = $_POST['type'] ?? '';
$amount = $_POST['amount'] ?? '';
$note = $_POST['note'] ?? '';
$userid = $_SESSION['userid'];

// validasi
$allowed = ['deposit','withdraw'];
if (!in_array($type, $allowed, true)) {
    die('Tipe transaksi tidak valid.');
}

$amount = str_replace(',', '.', $amount);
if (!is_numeric($amount) || (float)$amount <= 0) {
    die('Jumlah harus angka lebih dari 0.');
}
$amount = round((float)$amount, 2);

// jika withdraw, pastikan saldo cukup
$stmt = $pdo->prepare("SELECT 
    IFNULL(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END),0) AS total_deposit,
    IFNULL(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END),0) AS total_withdraw
    FROM transaksi WHERE userid = ?");
$stmt->execute([$userid]);
$totals = $stmt->fetch();
$balance = (float)$totals['total_deposit'] - (float)$totals['total_withdraw'];

if ($type === 'withdraw' && $amount > $balance) {
    die('Saldo tidak cukup untuk melakukan penarikan.');
}

// masukkin saldo
$ins = $pdo->prepare("INSERT INTO transaksi (userid, amount, type, note) VALUES (?, ?, ?, ?)");
$ins->execute([$userid, $amount, $type, $note]);

header('Location: dashboard.php');
exit;
