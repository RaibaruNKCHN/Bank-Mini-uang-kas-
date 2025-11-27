<?php
require '../../includes/config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Permintaan tidak valid.'];

if (!is_admin()) {
    $response['message'] = 'Akses ditolak.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $id = (int)$id;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $response['message'] = 'CSRF token tidak valid.';
    } elseif (empty($id) || empty($username) || empty($role)) {
        $response['message'] = 'Semua field wajib diisi.';
    } elseif (!in_array($role, ['admin', 'guru', 'user'], true)) {
        $response['message'] = 'Role tidak valid.';
    } else {
        // Check if username exists across admins and users
        require_once __DIR__ . '/../../includes/db.php';
        if ($role === 'admin') {
            $exists_admin = db_query_one("SELECT id FROM admins WHERE username = ? AND id != ?", [$username, $id]);
            $exists_user = db_query_one("SELECT id FROM users WHERE username = ?", [$username]);
            if ($exists_admin || $exists_user) {
                $response['message'] = 'Username sudah ada.';
            } else {
                $params = [$username];
                $sql = "UPDATE admins SET username = ?";
                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = $id;
                if (db_execute($sql, $params)) {
                    $response['success'] = true;
                    $response['message'] = 'Admin berhasil diupdate.';
                } else {
                    $response['message'] = 'Gagal mengupdate admin.';
                }
            }
        } else {
            $exists_user = db_query_one("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id]);
            $exists_admin = db_query_one("SELECT id FROM admins WHERE username = ?", [$username]);
            if ($exists_user || $exists_admin) {
                $response['message'] = 'Username sudah ada.';
            } else {
                $params = [$username, $role];
                $sql = "UPDATE users SET username = ?, role = ?";

                if (!empty($password)) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }

                $sql .= " WHERE id = ?";
                $params[] = $id;

                if (db_execute($sql, $params)) {
                    $response['success'] = true;
                    $response['message'] = 'User berhasil diupdate.';
                } else {
                    $response['message'] = 'Gagal mengupdate user.';
                }
            }
        }
    }
}

echo json_encode($response);
exit;
