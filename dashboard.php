<?php
require 'config.php';
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}
$userid = $_SESSION['userid'];
$username = $_SESSION['username'] ?? '';

// Hitung saldo: jumlah deposit - jumlah withdraw
$stmt = $pdo->prepare("SELECT 
    IFNULL(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END),0) AS total_deposit,
    IFNULL(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END),0) AS total_withdraw
    FROM transaksi WHERE userid = ?");
$stmt->execute([$userid]);
$totals = $stmt->fetch();
$balance = (float)$totals['total_deposit'] - (float)$totals['total_withdraw'];

// Ambil histori (terbaru dulu)
$hist = $pdo->prepare("SELECT * FROM transaksi WHERE userid = ? ORDER BY createdat DESC LIMIT 100");
$hist->execute([$userid]);
$transactions = $hist->fetchAll();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Dashboard — Mini Bank</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="box">
    <h2>Halo, <?=htmlspecialchars($username)?> — Dashboard</h2>
    <p><strong>Saldo saat ini:</strong> Rp <?=number_format($balance,2,',','.')?></p>

    <h3>Tambah Transaksi</h3>
    <form action="transaksi.php" method="post">
        <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
        <label>Jenis
            <select name="type">
                <option value="deposit">Deposit (Masuk)</option>
                <option value="withdraw">Withdraw (Keluar)</option>
            </select>
        </label><br>
        <label>Jumlah (contoh: 15000.50)<br><input name="amount" required></label><br>
        <label>Catatan (opsional)<br><input name="note"></label><br>
        <button type="submit">Simpan</button>
    </form>

    <h3>Histori Transaksi</h3>
    <?php if(empty($transactions)): ?>
        <p>Tidak ada transaksi.</p>
    <?php else: ?>
    <table class="tbl">
        <thead><tr><th>#</th><th>Tanggal</th><th>Jenis</th><th>Jumlah</th><th>Catatan</th></tr></thead>
        <tbody>
        <?php foreach($transactions as $t): ?>
            <tr>
                <td><?=htmlspecialchars($t['id'])?></td>
                <td><?=htmlspecialchars($t['createdat'])?></td>
                <td><?=htmlspecialchars($t['type'])?></td>
                <td style="text-align:right"><?=number_format($t['amount'],2,',','.')?></td>
                <td><?=htmlspecialchars($t['note'])?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <p><a href="logout.php">Logout</a></p>
</div>
</body>
</html>
