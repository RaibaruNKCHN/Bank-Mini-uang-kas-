<?php
require '../../includes/config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Terjadi kesalahan tak dikenal.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf(filter_input(INPUT_POST, 'csrf', FILTER_SANITIZE_STRING))) {
        $response['message'] = 'Token CSRF tidak valid.';
        echo json_encode($response);
        exit;
    }

    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    if (empty($username) || empty($password)) {
        $response['message'] = 'Username dan password harus diisi.';
        echo json_encode($response);
        exit;
    }

    try {
        require_once __DIR__ . '/../../includes/db.php';
        
        $user = null;
        $is_admin = false;

        // 1. Check in 'admins' table first
        $admin_candidate = db_query_one("SELECT id, username, password FROM admins WHERE username = ?", [$username]);
        if ($admin_candidate && password_verify($password, $admin_candidate['password'])) {
            $user = $admin_candidate;
            $is_admin = true;
        } else {
            // 2. If not found, check in 'users' table
            $user_candidate = db_query_one("SELECT id, username, password, role FROM users WHERE username = ?", [$username]);
            if ($user_candidate && password_verify($password, $user_candidate['password'])) {
                $user = $user_candidate;
            }
        }

        if ($user) {
            session_regenerate_id(true);

            $_SESSION['userid'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $is_admin ? 'admin' : $user['role'];
            $_SESSION['is_admin'] = $is_admin; // Add a clear flag for session type
            $_SESSION['logged_in'] = true;

            $response['success'] = true;
            $response['redirect'] = 'dashboard.php';
        } else {
            $response['message'] = 'Username atau password salah.';
        }
    } catch (PDOException $e) {
        error_log("Login Error: " . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan database.';
    }
} else {
    $response['message'] = 'Metode request tidak diizinkan.';
}

echo json_encode($response);
?>