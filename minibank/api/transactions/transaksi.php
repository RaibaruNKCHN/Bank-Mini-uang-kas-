<?php
require '../../includes/config.php';
if (!is_logged_in()) {
    header('Location: auth/login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../auth/dashboard.php');
    exit;
}

if (!verify_csrf($_POST['csrf'] ?? '')) {
    die('CSRF token invalid.');
}


 $type = $_POST['type'] ?? '';
 $amount = $_POST['amount'] ?? '';
 $note = $_POST['note'] ?? '';
 $rekening_tujuan = $_POST['rekening_tujuan'] ?? ''; // Get rekening or id from searchable select

 // Find the target userid from the provided identifier (rekening preferred)
 require_once __DIR__ . '/../../includes/db.php';
 $userid = null;
 if (!empty($rekening_tujuan)) {
     $userid = resolve_userid_from_mixed($rekening_tujuan);
 }

 if (is_null($userid)) {
     die('Nomor rekening tujuan tidak valid atau tidak ditemukan.');
 }

// Batasi transaksi sesuai role
if (is_user()) {
    // User hanya boleh transaksi untuk dirinya sendiri
    if ((int)$userid !== (int)$_SESSION['userid']) {
        die('User tidak boleh melakukan transaksi untuk user lain.');
    }
} elseif (is_guru() && $type !== 'deposit') {
    die('Guru hanya boleh melakukan deposit.');
}
// Admin boleh deposit dan withdraw

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
require_once __DIR__ . '/../../includes/db.php';
$totals = db_query_one("SELECT 
    IFNULL(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END),0) AS total_deposit,
    IFNULL(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END),0) AS total_withdraw
    FROM transaksi WHERE target_user_id = ?", [$userid]);
$balance = (float)$totals['total_deposit'] - (float)$totals['total_withdraw'];

if ($type === 'withdraw' && $amount > $balance) {
    die('Saldo tidak cukup untuk melakukan penarikan.');
}

// masukkin saldo
// Determine creator columns depending on actor
$created_by_admin = null;
$created_by_user = null;
if (is_admin()) {
    $created_by_admin = $_SESSION['userid'];
} else {
    $created_by_user = $_SESSION['userid'];
}

// Include rekening_tujuan in transaksi for easy display/search
$rekening_for_insert = !empty($rekening_tujuan) ? $rekening_tujuan : null;
db_execute("INSERT INTO transaksi (target_user_id, rekening_tujuan, created_by_admin_id, created_by_user_id, amount, type, note) VALUES (?, ?, ?, ?, ?, ?, ?)", [$userid, $rekening_for_insert, $created_by_admin, $created_by_user, $amount, $type, $note]);

// Ambil username user yang ditransaksikan
$user_info = db_query_one("SELECT username FROM users WHERE id = ?", [$userid]);
$username_transacted = $user_info['username'] ?? 'Unknown';

// Hitung saldo baru setelah transaksi
$totals_new = db_query_one("SELECT
    IFNULL(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END),0) AS total_deposit,
    IFNULL(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END),0) AS total_withdraw
    FROM transaksi WHERE target_user_id = ?", [$userid]);
$new_balance = (float)$totals_new['total_deposit'] - (float)$totals_new['total_withdraw'];

if ($type === 'deposit') {
    $_SESSION['success_message'] = "Deposit untuk $username_transacted berhasil! Saldo saat ini: Rp " . number_format($new_balance, 2, ',', '.');
} elseif ($type === 'withdraw') {
    $_SESSION['success_message'] = "Withdraw untuk $username_transacted berhasil! Saldo saat ini: Rp " . number_format($new_balance, 2, ',', '.');
}

// Redirect to dashboard (use absolute path so browser resolves correctly)
header('Location: ' . BASE_URL . 'auth/dashboard.php');
exit;
