<?php
require 'config.php';
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $err = 'CSRF token invalid.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT id, password, role FROM user WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && $password === $user['password']) {
            // login successful
            session_regenerate_id(true);
            $_SESSION['userid'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $user['role'];
            header('Location: dashboard.php');
            exit;
        } else {
            $err = 'Username atau password salah.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Mini Bank - Login</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
    <div class="login-card fade-in">
        <div class="login-icon">🏦</div>
        <h2 class="login-title">Selamat Datang di Mini Bank</h2>
        <p class="login-subtitle">Masuk untuk mengelola keuangan Anda</p>
        <?php if($err): ?><div class="login-error shake"><?=htmlspecialchars($err)?></div><?php endif; ?>
        <form method="post" class="login-form">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
            <label class="login-label">Username<br><input name="username" class="login-input" placeholder="Masukkan username" required></label><br>
            <label class="login-label">Password<br><input type="password" name="password" class="login-input" placeholder="Masukkan password" required></label><br>
            <button type="submit" class="login-btn">Masuk</button>
        </form>
        <div class="login-footer">
            <p>Bank Mini - Aman & Terpercaya</p>
        </div>
    </div>
</div>
</body>
</html>
