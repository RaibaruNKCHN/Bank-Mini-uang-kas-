<?php
require '../includes/config.php';
if (is_logged_in()) {
    header('Location: dashboard');
    exit;
}

// Prefer explicit error param, but also show timeout reason set in session
$err = $_GET['error'] ?? '';
$timeoutFlag = isset($_GET['timeout']) ? true : false;
if (!$err && isset($_SESSION['timeout_reason'])) {
    $err = $_SESSION['timeout_reason'];
    unset($_SESSION['timeout_reason']);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini Bank - Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/logo.png" type="image/png">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-branding">
            <img src="../assets/images/logo2.png" alt="Bank Mini Logo" class="auth-logo">
        </div>
        <div class="auth-form-container">
            <div class="login-container">
                <h1 class="login-title">Selamat Datang</h1>
                <p class="login-subtitle">Masuk untuk melanjutkan</p>
                
                <?php if($err): ?>
                    <div class="alert alert-danger"><?=htmlspecialchars($err)?></div>
                <?php endif; ?>
        
                <form method="post" action="../api/auth/login.php" class="login-form">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Masuk</button>
                </form>
        
                <div class="login-footer">
                    <p>Belum punya akun? <a href="register.php">Daftar di sini</a></p>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/auth.js"></script>
</body>
</html>
