<?php
require 'config.php';
if (!is_admin()) {
    header('Location: dashboard.php');
    exit;
}

$id = $_GET['id'] ?? '';
if (!$id || !is_numeric($id)) {
    die('ID tidak valid.');
}

$stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) {
    die('User tidak ditemukan.');
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

        if (empty($username) || empty($role)) {
            $err = 'Username dan role harus diisi.';
        } elseif (!in_array($role, ['admin', 'guru', 'user'], true)) {
            $err = 'Role tidak valid.';
        } elseif (($role === 'admin' && $code !== ADMIN_CODE) || ($role === 'guru' && $code !== GURU_CODE)) {
            $err = 'Kode untuk role ini salah.';
        } elseif ($role === 'user' && !empty($code)) {
            $err = 'User tidak memerlukan kode.';
        } else {
            // Check if username exists (except current)
            $stmt_check = $pdo->prepare("SELECT id FROM user WHERE username = ? AND id != ?");
            $stmt_check->execute([$username, $id]);
            if ($stmt_check->fetch()) {
                $err = 'Username sudah ada.';
            } else {
                $update_fields = ['username' => $username, 'role' => $role];
                if (!empty($password)) {
                    $update_fields['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $set_parts = [];
                $params = [];
                foreach ($update_fields as $field => $value) {
                    $set_parts[] = "$field = ?";
                    $params[] = $value;
                }
                $params[] = $id;
                $upd = $pdo->prepare("UPDATE user SET " . implode(', ', $set_parts) . " WHERE id = ?");
                $upd->execute($params);
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
<title>Update User</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-container">
    <div class="login-card fade-in">
        <h2>Update User</h2>
        <?php if($err): ?><div class="login-error shake"><?=htmlspecialchars($err)?></div><?php endif; ?>
        <form method="post" class="login-form">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
            <label>Username<br><input name="username" class="login-input" value="<?=htmlspecialchars($user['username'])?>" required></label><br>
            <label>Password (kosongkan jika tidak ingin ganti)<br><input type="password" name="password" class="login-input"></label><br>
            <label>Role<br>
                <select name="role" class="login-input" required style="color: #333; background: rgba(255,255,255,0.9);">
                    <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="guru" <?= $user['role'] === 'guru' ? 'selected' : '' ?>>Guru</option>
                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </label><br>
            <div id="code-field" style="display: <?= ($user['role'] === 'admin' || $user['role'] === 'guru') ? 'block' : 'none' ?>;">
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
            <button type="submit" class="login-btn">Update User</button>
        </form>
        <a href="dashboard.php">Kembali</a>
    </div>
</div>
</body>
</html>
