<?php
require '../includes/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Permintaan tidak valid.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf'] ?? '')) {
        $response['message'] = 'CSRF token tidak valid.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (login($username, $password)) {
            session_regenerate_id(true);
            $response['success'] = true;
            $response['redirect'] = 'dashboard.php';
        } else {
            $response['message'] = 'Username atau password salah.';
        }
    }
}

echo json_encode($response);
exit;
