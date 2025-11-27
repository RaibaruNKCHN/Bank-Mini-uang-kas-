<?php
require '../../includes/config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Permintaan tidak valid.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $response['message'] = 'CSRF token tidak valid.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $code = $_POST['code'] ?? '';

        if (empty($username) || empty($password) || empty($role)) {
            $response['message'] = 'Semua field harus diisi.';
        } elseif (strlen($password) < 8) {
            $response['message'] = 'Password minimal harus 8 karakter.';
        } elseif (!in_array($role, ['admin', 'guru', 'user'], true)) {
            $response['message'] = 'Role tidak valid.';
        } elseif (($role === 'admin' && $code !== ADMIN_CODE) || ($role === 'guru' && $code !== GURU_CODE)) {
            $response['message'] = 'Kode untuk role ini salah.';
        } elseif ($role === 'user' && !empty($code)) {
            $response['message'] = 'User tidak memerlukan kode.';
        } else {
            try {
                require_once __DIR__ . '/../../includes/db.php';

                $exists_admin = db_query_one("SELECT id FROM admins WHERE username = ?", [$username]);
                $exists_user = db_query_one("SELECT id FROM users WHERE username = ?", [$username]);

                if ($exists_admin || $exists_user) {
                    $response['message'] = 'Username sudah ada.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $success = false;

                    if ($role === 'admin') {
                        $year = date('Y');
                        $month = date('m');
                        $last_uid_row = db_query_one("SELECT MAX(CAST(SUBSTRING(admin_uid, 7, 4) AS UNSIGNED)) as last_seq FROM admins WHERE SUBSTRING(admin_uid, 1, 6) = ?", [$year . $month]);
                        $next_seq = ($last_uid_row && $last_uid_row['last_seq']) ? $last_uid_row['last_seq'] + 1 : 1;
                        $admin_uid = (int)($year . $month . str_pad($next_seq, 4, '0', STR_PAD_LEFT));
                        
                        $success = db_execute("INSERT INTO admins (admin_uid, username, password) VALUES (?, ?, ?)", [$admin_uid, $username, $hash]);
                    } else { // 'user' or 'guru'
                        $current_year = date('Y');
                        $last_rekening_sql = "SELECT MAX(CAST(SUBSTRING(rekening, 7, 5) AS UNSIGNED)) as last_seq FROM users WHERE SUBSTRING(rekening, 3, 4) = ?";
                        $last_seq_row = db_query_one($last_rekening_sql, [$current_year]);
                        $next_seq = ($last_seq_row && $last_seq_row['last_seq']) ? $last_seq_row['last_seq'] + 1 : 1;
                        $new_rekening = (int)('62' . $current_year . str_pad($next_seq, 5, '0', STR_PAD_LEFT));

                        $success = db_execute("INSERT INTO users (rekening, username, password, role) VALUES (?, ?, ?, ?)", [$new_rekening, $username, $hash, $role]);
                    }

                    if ($success) {
                        $response['success'] = true;
                        $response['message'] = 'User berhasil dibuat.';
                        $response['redirect'] = '../auth/login.php';
                        $response['delay'] = 2000;
                    } else {
                        $response['message'] = 'Gagal menyimpan user ke database.';
                    }
                }
            } catch (Exception $e) {
                error_log('create_user exception: ' . $e->getMessage());
                $response['message'] = 'Terjadi kesalahan server. Silakan coba lagi.';
            }
        }
    }

    // end POST handler
}

echo json_encode($response);
exit;
