<?php
require '../../includes/config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Permintaan tidak valid.'];

if (!is_admin()) {
    $response['message'] = 'Hanya admin yang dapat melakukan ini.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        // Return a user-friendly server-side message that the UI expects
        $response['message'] = 'Admin token tidak valid.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'verify_global_access':
                $access_code = $_POST['access_code'] ?? '';
                if ($access_code === ADMIN_CODE_ALL_TRANSACTIONS) {
                    // Mark session as verified for superadmin actions
                    $_SESSION['superadmin_verified'] = true;
                    $response['success'] = true;
                    $response['message'] = 'Kode akses diterima.';
                } else {
                    $response['message'] = 'Kode akses salah.';
                }
                break;

            case 'verify_admin_view_password':
                $target_admin_id = $_POST['target_admin_id'] ?? '';
                $your_admin_password = $_POST['your_admin_password'] ?? '';
                $current_admin_id = $_SESSION['userid'];

                if (empty($target_admin_id) || empty($your_admin_password)) {
                    $response['message'] = 'Pilih admin target dan masukkan kata sandi Anda.';
                } else {
                    // Verifikasi kata sandi admin yang sedang login
                    require_once __DIR__ . '/../../includes/db.php';
                    $current_admin_id = (int) $current_admin_id;
                    $target_admin_id = (int) $target_admin_id;

                    $current_admin_info = db_query_one("SELECT password FROM admins WHERE id = ? LIMIT 1", [$current_admin_id]);

                    if ($current_admin_info && password_verify($your_admin_password, $current_admin_info['password'])) {
                        // Verifikasi bahwa target admin adalah admin
                        $target_info = db_query_one("SELECT id FROM admins WHERE id = ? LIMIT 1", [$target_admin_id]);
                        if ($target_info) {
                            $response['success'] = true;
                            $response['message'] = 'Verifikasi sukses.';
                        } else {
                            $response['message'] = 'Admin target tidak valid.';
                        }
                    } else {
                        $response['message'] = 'Kata sandi Anda salah.';
                    }
                }
                break;

            default:
                $response['message'] = 'Aksi tidak dikenal.';
                break;
        }
    }
}

echo json_encode($response);
exit;
