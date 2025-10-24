<?php
require 'config.php';
if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

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
                header('Location: dashboard.php');
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
<title>Buat User Baru</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
    <div class="login-card fade-in">
        <h2>Buat User Baru</h2>
        <?php if($err): ?><div class="login-error shake"><?=htmlspecialchars($err)?></div><?php endif; ?>
        <form method="post" class="login-form">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
            <label>Username<br><input name="username" class="login-input" required></label><br>
            <label>Password<br><input type="password" name="password" class="login-input" required></label><br>
            <label>Role<br>
                <select name="role" class="login-input" required style="color: #333; background: rgba(255,255,255,0.9);">
                    <option value="user">User</option>
                    <option value="guru">Guru</option>
                    <option value="admin">Admin</option>
                </select>
            </label><br>
            <div id="code-field" style="display: none;">
                <label>Kode (untuk admin/guru)<br><input name="code" class="login-input"></label><br>
            </div>
            <script>
            document.querySelector('select[name="role"]').addEventListener('change', function() {
                var codeField = document.getElementById('code-field');
                if (this.value === 'admin' || this.value === 'guru') {
                    codeField.style.display = 'block';
                } else {
                    codeField.style.display = 'none';
                }
            });
            </script>
            <button type="submit" class="login-btn">Buat User</button>
        </form>
        <a href="dashboard.php">Kembali</a>
    </div>
</div>
</body>
</html>
