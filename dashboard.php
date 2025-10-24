<?php
require 'config.php';
if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}

// Handle success notification
$success_message = '';
if (isset($_GET['success']) && isset($_GET['balance']) && isset($_GET['username'])) {
    $type = $_GET['success'];
    $balance = $_GET['balance'];
    $username = $_GET['username'];
    if ($type === 'deposit') {
        $success_message = "Deposit untuk $username berhasil! Saldo saat ini: Rp $balance";
    } elseif ($type === 'withdraw') {
        $success_message = "Withdraw untuk $username berhasil! Saldo saat ini: Rp $balance";
    }
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
<title>Mini Bank - Dashboard</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="dashboard-container">
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-left">
            <div class="navbar-brand">🏦 Mini Bank</div>
            <?php if (!is_admin()): ?>
            <a href="#dashboard" class="navbar-link">Dashboard</a>
            <?php endif; ?>
            <?php if (is_admin()): ?>
            <a href="#userguru" class="navbar-link">User & Guru</a>
            <a href="#admindeposit" class="navbar-link">Deposit User/Guru</a>
            <a href="#adminwithdraw" class="navbar-link">Withdraw User/Guru</a>
            <a href="#adminhistori" class="navbar-link">Histori User/Guru</a>
            <?php elseif (is_guru()): ?>
            <a href="#deposit" class="navbar-link">Deposit</a>
            <?php endif; ?>
            <a href="#histori" class="navbar-link">Histori</a>
        </div>
        <div class="navbar-right">
            <span class="user-info">👤 <?=$username?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
            <?php if (!is_admin()): ?>
            <a href="hapus_user.php?id=<?=htmlspecialchars($userid)?>" onclick="return confirm('Yakin hapus akun Anda sendiri?')" style="color: red; font-size: 12px;">Hapus Akun</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Success Notification -->
    <?php if (!empty($success_message)): ?>
    <div class="success-notification" style="background:#d4edda; color:#155724; padding:10px; margin-bottom:20px; border:1px solid #c3e6cb; border-radius:6px; font-weight:bold;">
        ✅ <?=$success_message?>
    </div>
    <?php endif; ?>

    <!-- Dashboard Section -->
    <?php if (!is_admin()): ?>
    <section class="saldo-card" id="section-dashboard">
        <div class="saldo-label">Saldo saat ini</div>
        <div class="saldo-value">💰 Rp <?=number_format($balance,2,',','.')?></div>
    </section>
    <?php endif; ?>

    <?php if (is_admin()): ?>
    <section class="admin-section" id="section-userguru" style="display:none;">
        <h3 class="section-title">Kelola User & Guru</h3>
        <a href="create_user.php" class="btn">Buat User Baru</a>
        <?php
        $users = $pdo->query("SELECT id, username, role FROM user WHERE role IN ('user','guru','admin') ORDER BY role, username")->fetchAll();
        if ($users): ?>
        <table class="user-table">
            <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach($users as $u): ?>
                <tr>
                    <td><?=htmlspecialchars($u['id'])?></td>
                    <td><?=htmlspecialchars($u['username'])?></td>
                    <td><?=htmlspecialchars($u['role'])?></td>
                    <td>
                        <a href="update_user.php?id=<?=htmlspecialchars($u['id'])?>">Update</a> |
                        <a href="hapus_user.php?id=<?=htmlspecialchars($u['id'])?>" onclick="return confirm('Yakin hapus user/guru ini?')">Hapus</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>Tidak ada user/guru.</p>
        <?php endif; ?>
    </section>

    <section class="admin-section" id="section-adminhistori" style="display:none;">
        <h3 class="section-title">Histori Transaksi User/Guru</h3>
        <form method="get" class="form-card">
            <label>Pilih User/Guru
                <select name="userid" required>
                    <option value="">-- Pilih --</option>
                    <?php
                    $allusers = $pdo->query("SELECT id, username, role FROM user WHERE role IN ('user','guru') ORDER BY role, username")->fetchAll();
                    foreach($allusers as $u): ?>
                    <option value="<?=htmlspecialchars($u['id'])?>"><?=htmlspecialchars($u['role'])?> - <?=htmlspecialchars($u['username'])?></option>
                    <?php endforeach; ?>
                </select>
            </label><br>
            <button type="submit">Lihat Histori</button>
        </form>
        <?php if(isset($_GET['userid']) && is_numeric($_GET['userid'])): ?>
        <?php
        $selected_userid = (int)$_GET['userid'];
        $user_info_stmt = $pdo->prepare("SELECT username, role FROM user WHERE id = ?");
        $user_info_stmt->execute([$selected_userid]);
        $user_info = $user_info_stmt->fetch();
        $hist_admin = $pdo->prepare("SELECT * FROM transaksi WHERE userid = ? ORDER BY createdat DESC LIMIT 100");
        $hist_admin->execute([$selected_userid]);
        $admin_transactions = $hist_admin->fetchAll();
        ?>
        <h4>Histori untuk: <?=htmlspecialchars($user_info['role'])?> - <?=htmlspecialchars($user_info['username'])?></h4>
        <?php if(empty($admin_transactions)): ?>
            <p>Tidak ada transaksi.</p>
        <?php else: ?>
        <table class="histori-table">
            <thead><tr><th>#</th><th>Tanggal</th><th>Jenis</th><th>Jumlah</th><th>Catatan</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach($admin_transactions as $t): ?>
                <tr>
                    <td><?=htmlspecialchars($t['id'])?></td>
                    <td><?=htmlspecialchars($t['createdat'])?></td>
                    <td><?=htmlspecialchars($t['type'])?></td>
                    <td style="text-align:right"><?=number_format($t['amount'],2,',','.')?></td>
                    <td><?=htmlspecialchars($t['note'])?></td>
                    <td><a href="hapus_histori.php?id=<?=htmlspecialchars($t['id'])?>" onclick="return confirm('Yakin hapus histori ini?')">Hapus</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php endif; ?>
    </section>
    <section class="admin-section" id="section-admindeposit" style="display:none;">
        <h3 class="section-title">Deposit untuk User/Guru</h3>
        <form action="transaksi.php" method="post" class="form-card">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
            <input type="hidden" name="type" value="deposit">
            <label>Pilih User/Guru
                <select name="userid" id="deposit-userid" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach($allusers as $u): ?>
                    <option value="<?=htmlspecialchars($u['id'])?>" data-balance="<?php
                        $stmt_balance = $pdo->prepare("SELECT
                            IFNULL(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END),0) AS total_deposit,
                            IFNULL(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END),0) AS total_withdraw
                            FROM transaksi WHERE userid = ?");
                        $stmt_balance->execute([$u['id']]);
                        $totals_balance = $stmt_balance->fetch();
                        $balance = (float)$totals_balance['total_deposit'] - (float)$totals_balance['total_withdraw'];
                        echo htmlspecialchars($balance);
                    ?>"><?=htmlspecialchars($u['role'])?> - <?=htmlspecialchars($u['username'])?></option>
                    <?php endforeach; ?>
                </select>
            </label><br>
            <div id="deposit-balance-display" style="display:none; margin-bottom:10px; padding:10px; background:#e9ecef; border-radius:6px; color:#333; font-weight:bold;">Saldo saat ini: Rp <span id="deposit-current-balance">0</span></div>
            <label>Jumlah (contoh: 15000.50)<br><input name="amount" type="number" step="0.01" required></label><br>
            <label>Catatan (opsional)<br><input name="note"></label><br>
            <button type="submit">Lakukan Deposit</button>
        </form>
        <script>
        document.getElementById('deposit-userid').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var balance = selectedOption.getAttribute('data-balance');
            if (balance !== null) {
                document.getElementById('deposit-current-balance').textContent = parseFloat(balance).toLocaleString('id-ID', {minimumFractionDigits: 2});
                document.getElementById('deposit-balance-display').style.display = 'block';
            } else {
                document.getElementById('deposit-balance-display').style.display = 'none';
            }
        });
        </script>
    </section>
    <section class="admin-section" id="section-adminwithdraw" style="display:none;">
        <h3 class="section-title">Withdraw untuk User/Guru</h3>
        <form action="transaksi.php" method="post" class="form-card">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
            <input type="hidden" name="type" value="withdraw">
            <label>Pilih User/Guru
                <select name="userid" id="withdraw-userid" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach($allusers as $u): ?>
                    <option value="<?=htmlspecialchars($u['id'])?>" data-balance="<?php
                        $stmt_balance = $pdo->prepare("SELECT 
                            IFNULL(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END),0) AS total_deposit,
                            IFNULL(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END),0) AS total_withdraw
                            FROM transaksi WHERE userid = ?");
                        $stmt_balance->execute([$u['id']]);
                        $totals_balance = $stmt_balance->fetch();
                        $balance = (float)$totals_balance['total_deposit'] - (float)$totals_balance['total_withdraw'];
                        echo htmlspecialchars($balance);
                    ?>"><?=htmlspecialchars($u['role'])?> - <?=htmlspecialchars($u['username'])?></option>
                    <?php endforeach; ?>
                </select>
            </label><br>
            <div id="balance-display" style="display:none; margin-bottom:10px; padding:10px; background:#e9ecef; border-radius:6px; color:#333; font-weight:bold;">Saldo saat ini: Rp <span id="current-balance">0</span></div>
            <label>Jumlah (contoh: 15000)<br><input name="amount" required></label><br>
            <label>Catatan (opsional)<br><input name="note"></label><br>
            <button type="submit">Lakukan Withdraw</button>
        </form>
        <script>
        document.getElementById('withdraw-userid').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var balance = selectedOption.getAttribute('data-balance');
            if (balance !== null) {
                document.getElementById('current-balance').textContent = parseFloat(balance).toLocaleString('id-ID', {minimumFractionDigits: 2});
                document.getElementById('balance-display').style.display = 'block';
            } else {
                document.getElementById('balance-display').style.display = 'none';
            }
        });
        </script>
    </section>
    <?php elseif (is_guru()): ?>
    <section class="guru-section" id="section-deposit" style="display:none;">
        <div class="info-guru">
            <strong>Info Guru:</strong> Anda hanya dapat melakukan <b>deposit</b>, melihat saldo, dan melihat histori transaksi Anda.<br>
            Fitur withdraw, hapus histori, dan kelola user/guru hanya tersedia untuk admin.
        </div>
        <h3 class="section-title">Tambah Deposit</h3>
        <form action="transaksi.php" method="post" class="form-card">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
            <input type="hidden" name="type" value="deposit">
            <label>Pilih User untuk Deposit
                <select name="userid" required>
                    <option value="">-- Pilih User --</option>
                    <?php
                    $allusers = $pdo->query("SELECT id, username, role FROM user WHERE role IN ('user','guru') ORDER BY role, username")->fetchAll();
                    foreach($allusers as $u): ?>
                    <option value="<?=htmlspecialchars($u['id'])?>"><?=htmlspecialchars($u['role'])?> - <?=htmlspecialchars($u['username'])?></option>
                    <?php endforeach; ?>
                </select>
            </label><br>
            <label>Jumlah (contoh: 15000)<br><input name="amount" type="number" step="0.01" required></label><br>
            <label>Catatan (opsional)<br><input name="note"></label><br>
            <button type="submit">Simpan Deposit</button>
        </form>
    </section>
    <?php elseif (is_user()): ?>
    <section class="user-section" id="section-dashboard">
        <div class="info-user">
        </div>
    </section>
    <?php endif; ?>

    <section class="histori-section" id="section-histori" style="display:none;">
    <h3 class="section-title">Histori Transaksi</h3>
    <?php if(empty($transactions)): ?>
        <p>Tidak ada transaksi.</p>
    <?php else: ?>
    <table class="histori-table">
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
    </section>

    <script>
    // Navbar navigation
    document.querySelectorAll('.navbar-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var target = link.getAttribute('href').replace('#','section-');
            document.querySelectorAll('section').forEach(function(sec){
                sec.style.display = 'none';
            });
            var showSec = document.getElementById(target);
            if(showSec) showSec.style.display = '';
        });
    });
    // Show dashboard by default, or adminhistori if userid is set, or userguru for admin
    document.querySelectorAll('section').forEach(function(sec){
        sec.style.display = 'none';
    });
    <?php if(isset($_GET['userid']) && is_admin()): ?>
    var defaultSec = document.getElementById('section-adminhistori');
    <?php elseif(is_admin()): ?>
    var defaultSec = document.getElementById('section-userguru');
    <?php else: ?>
    var defaultSec = document.getElementById('section-dashboard');
    <?php endif; ?>
    if(defaultSec) defaultSec.style.display = '';
    </script>
</div>
</body>
</html>
