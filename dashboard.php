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
    <div style="margin-bottom:10px;">
        <strong>Role:</strong> <span style="color:#007bff;font-weight:bold; font-size:1.1em; padding:2px 8px; border-radius:4px; background:#f0f8ff;"><?=htmlspecialchars($_SESSION['role'] ?? '-')?></span>
    </div>
    <p><strong>Saldo saat ini:</strong> Rp <?=number_format($balance,2,',','.')?></p>


    <?php if (is_admin()): ?>
        <!-- Admin: semua fitur -->
        <!-- Admin: semua fitur -->
        <h3>Kelola User & Guru</h3>
        <?php
        $users = $pdo->query("SELECT id, username, role FROM user WHERE role IN ('user','guru') ORDER BY role, username")->fetchAll();
        if ($users): ?>
        <table class="tbl" style="margin-bottom:20px;">
            <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach($users as $u): ?>
                <tr>
                    <td><?=htmlspecialchars($u['id'])?></td>
                    <td><?=htmlspecialchars($u['username'])?></td>
                    <td><?=htmlspecialchars($u['role'])?></td>
                    <td><a href="hapus_user.php?id=<?=htmlspecialchars($u['id'])?>" onclick="return confirm('Yakin hapus user/guru ini?')">Hapus</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>Tidak ada user/guru.</p>
        <?php endif; ?>
        <h3>Tambah Transaksi</h3>
        <form action="transaksi.php" method="post" style="margin-bottom:20px;">
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

    <?php elseif (is_guru()): ?>
        <!-- Guru: hanya deposit, lihat saldo & histori -->
        <!-- Guru: hanya deposit, lihat saldo & histori -->
        <div class="info-guru" style="background:#e7f3fe;padding:10px;border-radius:5px;margin-bottom:10px;">
        </div>
        <h3>Tambah Deposit</h3>
        <form action="transaksi.php" method="post" style="margin-bottom:20px;">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
            <input type="hidden" name="type" value="deposit">
            <label>Jumlah (contoh: 15000.50)<br><input name="amount" required></label><br>
            <label>Catatan (opsional)<br><input name="note"></label><br>
            <button type="submit">Simpan Deposit</button>
        </form>

    <?php elseif (is_user()): ?>
        <!-- User: hanya lihat saldo & histori -->
        <!-- User: hanya lihat saldo & histori -->
        <div class="info-user" style="background:#f9f9f9;padding:10px;border-radius:5px;margin-bottom:10px;">
        </div>
    <?php endif; ?>




    <h3>Histori Transaksi</h3>
    <?php if(empty($transactions)): ?>
        <p>Tidak ada transaksi.</p>
    <?php else: ?>
    <table class="tbl">
        <thead><tr><th>#</th><th>Tanggal</th><th>Jenis</th><th>Jumlah</th><th>Catatan</th>
        <?php if(is_admin()): ?><th>Aksi</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach($transactions as $t): ?>
            <tr>
                <td><?=htmlspecialchars($t['id'])?></td>
                <td><?=htmlspecialchars($t['createdat'])?></td>
                <td><?=htmlspecialchars($t['type'])?></td>
                <td style="text-align:right"><?=number_format($t['amount'],2,',','.')?></td>
                <td><?=htmlspecialchars($t['note'])?></td>
                <?php if(is_admin()): ?>
                <td><a href="hapus_histori.php?id=<?=htmlspecialchars($t['id'])?>" onclick="return confirm('Yakin hapus histori?')">Hapus</a></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <p><a href="logout.php">Logout</a></p>
</div>
</body>
</html>
