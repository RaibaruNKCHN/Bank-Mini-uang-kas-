<?php
//config db
session_start();

// db_info
$dbHost = '127.0.0.1';
$dbName = 'bmsmk';
$dbUser = 'root';
$dbPass = '';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];


define('ADMIN_CODE', 'adminjkt62');
define('GURU_CODE', 'gurujkt62');

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

// cek login
function is_logged_in() {
    return isset($_SESSION['userid']);
}

// CSRF token/validatornya
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
function csrf_token() {
    return $_SESSION['csrf_token'];
}
function verify_csrf($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}

// login
function login($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, password, role FROM user WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['userid'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}

// logout
function logout() {
    session_destroy();
}

// check admin
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// check user
function is_user() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user';
}

// check login
function check_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

// check admin login
function check_admin_login() {
    if (!is_logged_in() || !is_admin()) {
        header("Location: login.php");
        exit;
    }
}

// check user login
function check_user_login() {
    if (!is_logged_in() || !is_user()) {
        header("Location: login.php");
        exit;
    }
}

// check admin or user
function check_admin_or_user() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
    if ($_SESSION['role'] !== 'admin') {
        // redirect atau tampilkan pesan akses ditolak
    }
}

// check guru
function is_guru() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'guru';
}

// check guru login
function check_guru_login() {
    if (!is_logged_in() || !is_guru()) {
        header("Location: login.php");
        exit;
    }
}
?>