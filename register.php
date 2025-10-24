<?php
require 'config.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $err = 'CSRF token invalid.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $code = $_POST['code'] ?? '';

        if (empty($username) || empty($password) || empty($role)) {
            $err = 'Semua field harus diisi.';
        } elseif (!in_array($role, ['admin', 'guru', 'user'], true)) {
            $err = 'Role tidak valid.';
        } elseif (($role === 'admin' && $code !== ADMIN_CODE) || ($role === 'guru' && $code !== GURU_CODE)) {
            $err = 'Kode untuk role ini salah.';
        } elseif ($role === 'user' && !empty($code)) {
            $err = 'User tidak memerlukan kode.';
        } else {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM user WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $err = 'Username sudah ada.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, ?)");
                $ins->execute([$username, $hash, $role]);
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Register - Mini Bank</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
    <div class="login-card fade-in">
        <div class="login-icon">🏦</div>
        <h2 class="login-title">Daftar Akun Baru</h2>
        <p class="login-subtitle">Buat akun untuk mengakses Mini Bank</p>
        <?php if($err): ?><div class="login-error shake"><?=htmlspecialchars($err)?></div><?php endif; ?>
        <form method="post" class="login-form">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
            <label class="login-label">Username<br><input name="username" class="login-input" placeholder="Masukkan username" required></label><br>
            <label class="login-label">Password<br><input type="password" name="password" class="login-input" placeholder="Masukkan password" required></label><br>
            <label class="login-label">Role<br>
                <select name="role" class="login-input" required style="appearance: auto; color: #333; background: rgba(255,255,255,0.9);">
                    <option value="" disabled selected>Pilih Role</option>
                    <option value="user">User</option>
                    <option value="guru">Guru</option>
                    <option value="admin">Admin</option>
                </select>
            </label><br>
            <label class="login-label">Kode (untuk admin/guru)<br><input name="code" class="login-input" placeholder="Masukkan kode jika admin/guru"></label><br>
            <button type="submit" class="login-btn">Daftar</button>
        </form>
        <div class="login-footer">
            <p>Sudah punya akun? <a href="index.php">Login</a></p>
        </div>
    </div>
</div>
</body>
</html>
