<?php
require '../includes/config.php';

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
        } elseif (strlen($password) < 8) {
            $err = 'Password minimal harus 8 karakter.';
        } elseif (!in_array($role, ['admin', 'guru', 'user'], true)) {
            $err = 'Role tidak valid.';
        } elseif (($role === 'admin' && $code !== ADMIN_CODE) || ($role === 'guru' && $code !== GURU_CODE)) {
            $err = 'Kode untuk role ini salah.';
        } elseif ($role === 'user' && !empty($code)) {
            $err = 'User tidak memerlukan kode.';
        } else {
            // Check if username exists in admins or users
            require_once __DIR__ . '/../includes/db.php';
            $exists = db_query_one("SELECT id FROM admins WHERE username = ?", [$username]);
            if (!$exists) $exists = db_query_one("SELECT id FROM users WHERE username = ?", [$username]);
            if ($exists) {
                $err = 'Username sudah ada.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                // Delegate actual insert to API which handles admins/users creation
                // Fallback: direct insert into users table when not using API
                db_execute("INSERT INTO users (username, password, role) VALUES (?, ?, ?)", [$username, $hash, $role]);
                header('Location: login');
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Mini Bank</title>
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
                <h1 class="login-title">Buat Akun Baru</h1>
                <p class="login-subtitle">Silakan isi form di bawah ini</p>
        
                <?php if($err): ?>
                    <div class="alert alert-danger"><?=htmlspecialchars($err)?></div>
                <?php endif; ?>
        
                <form method="post" action="../api/user/create_user.php" class="login-form">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 8 karakter" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" class="form-control" required>
                            <option value="" disabled selected>Pilih Role</option>
                            <option value="user">User</option>
                            <option value="guru">Guru</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group" id="code-field" style="display: none;">
                        <label for="code">Kode (untuk admin/guru)</label>
                        <input type="text" id="code" name="code" class="form-control" placeholder="Masukkan kode khusus">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Daftar</button>
                </form>
        
                <div class="login-footer">
                    <p>Sudah punya akun? <a href="login.php">Login di sini</a></p>
                </div>
            </div>
        </div>
    </div>
            <script>
            document.getElementById('role').addEventListener('change', function() {
                var codeField = document.getElementById('code-field');
                if (this.value === 'admin' || this.value === 'guru') {
                    codeField.style.display = 'block';
                } else {
                    codeField.style.display = 'none';
                }
            });
            </script>
            <script src="../assets/js/auth.js"></script>
            <!-- Registration success modal (used by auth.js) -->
            <div id="registerSuccessModal" class="modal" style="display:none;">
                <div class="modal-content">
                    <span class="modal-close" id="registerSuccessClose">&times;</span>
                    <h2 class="card-title">Sukses!</h2>
                    <p id="register-success-message">Akun berhasil dibuat.</p>
                    <div style="text-align: right; margin-top: 1rem;">
                        <button id="register-success-ok" class="btn btn-primary">Oke</button>
                    </div>
                </div>
            </div>
</body>
</html>

