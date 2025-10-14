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
<div class="box">
    <h2>Login — Mini Bank</h2>
    <?php if($err): ?><div class="error"><?=htmlspecialchars($err)?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
        <label>Username<br><input name="username" required></label><br>
        <label>Password<br><input type="password" name="password" required></label><br>
        <button type="submit">Login</button>
    </form>
    <p>Untuk demo: <strong>user</strong> / <strong>password</strong></p>
</div>
</body>
</html>
