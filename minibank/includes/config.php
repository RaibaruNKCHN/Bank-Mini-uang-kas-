<?php
//config db
session_start();

// --- Load global settings from includes/settings.json (create defaults if missing) ---
function load_global_settings() {
    $file = __DIR__ . '/settings.json';
    if (!file_exists($file)) {
        $defaults = [
            'timeout_enabled' => true,
            'timeout_seconds' => 300
        ];
        file_put_contents($file, json_encode($defaults, JSON_PRETTY_PRINT));
        return $defaults;
    }
    $json = @file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        // reset to defaults if corrupted
        $data = [
            'timeout_enabled' => true,
            'timeout_seconds' => 300
        ];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }
    return $data;
}

function save_global_settings($arr) {
    $file = __DIR__ . '/settings.json';
    file_put_contents($file, json_encode($arr, JSON_PRETTY_PRINT));
}

$GLOBAL_SETTINGS = load_global_settings();
// --- Dynamic Base URL ---
// This ensures all paths are relative to the project root, making it portable.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
// Determine the subdirectory path automatically
$script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
// Remove subdirectories of the project from the path if config is included from a nested file
$project_sub_dirs = ['/includes', '/api/auth', '/api/user', '/api/transactions', '/api/admin', '/auth', '/admin'];
$base_path = str_replace($project_sub_dirs, '', $script_name);
// Ensure base_path ends with a single slash, or is empty for root installations
$base_path = rtrim($base_path, '/') . '/';
define('BASE_URL', $protocol . $host . $base_path);

// Register application-wide error handler (redirects to friendly error page)
// Set second param to true during local debugging to expose traces.
require_once __DIR__ . '/error_handler.php';
register_error_handlers(false);

// Enforce session timeout for 'user' accounts when enabled globally
function enforce_session_timeout() {
    global $GLOBAL_SETTINGS;
    if (!isset($_SESSION['userid'])) return; // not logged in

    $role = $_SESSION['role'] ?? '';
    $last = $_SESSION['last_activity'] ?? time();

    // For regular 'user' accounts: enforce a dedicated, fixed 5-minute timeout (300s)
    if ($role === 'user') {
        $timeout = 300; // fixed 5 minutes for users
        if ((time() - $last) > $timeout) {
            // expired: perform secure logout
            $_SESSION['timeout_reason'] = 'Session expired due to inactivity.';
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'], $params['secure'], $params['httponly']
                );
            }
            session_destroy();
            header('Location: ' . BASE_URL . 'auth/login.php?timeout=1');
            exit;
        }
        // refresh last activity for user
        $_SESSION['last_activity'] = time();
        return;
    }

    // For admin accounts: respect the global settings (toggle + seconds)
    if ($role === 'admin') {
        if (empty($GLOBAL_SETTINGS['timeout_enabled'])) {
            $_SESSION['last_activity'] = time();
            return;
        }
        $timeout = intval($GLOBAL_SETTINGS['timeout_seconds'] ?? 300);
        if ((time() - $last) > $timeout) {
            // expired: perform secure logout
            $_SESSION['timeout_reason'] = 'Session expired due to inactivity.';
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'], $params['secure'], $params['httponly']
                );
            }
            session_destroy();
            header('Location: ' . BASE_URL . 'auth/login.php?timeout=1');
            exit;
        }
        // refresh last activity for admin
        $_SESSION['last_activity'] = time();
        return;
    }

    // Update last_activity for other roles; do not auto-logout
    $_SESSION['last_activity'] = time();
}

// Call enforcement on every page load after session start
enforce_session_timeout();
// --- End Dynamic Base URL ---

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
define('ADMIN_CODE_ALL_TRANSACTIONS', 'superadminjkt62'); // Kode khusus untuk melihat semua transaksi

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Instead of dying, re-throw the exception so calling scripts can handle it.
    // In an AJAX context, the script should return a JSON error.
    throw $e;
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
    // First try admins
    $stmt = $pdo->prepare("SELECT id, password FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['userid'] = $admin['id'];
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'admin';
        $_SESSION['is_admin'] = true;
        $_SESSION['last_activity'] = time();
        return true;
    }

    // Fall back to users table
    $stmt = $pdo->prepare("SELECT id, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['userid'] = $user['id'];
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $user['role'] ?? 'user';
        // ensure admin flag is not set for normal users
        unset($_SESSION['is_admin']);
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

// logout
function logout() {
    // clear session and cookie securely
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// check admin
function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// check user
function is_user() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'user' && empty($_SESSION['is_admin']);
}

// check login
function check_login() {
    if (!is_logged_in()) {
        header("Location: index.php");
        exit;
    }
}

// check admin login
function check_admin_login() {
    if (!is_logged_in() || !is_admin()) {
        header("Location: index.php");
        exit;
    }
}

// check user login
function check_user_login() {
    if (!is_logged_in() || !is_user()) {
        header("Location: index.php");
        exit;
    }
}

// check admin or user
function check_admin_or_user() {
    if (!is_logged_in()) {
        header("Location: auth/login.php");
        exit;
    }
    if ($_SESSION['role'] !== 'admin') {
        // redirect atau tampilkan pesan akses ditolak
    }
}

// check guru
function is_guru() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'guru' && empty($_SESSION['is_admin']);
}

// check guru login
function check_guru_login() {
    if (!is_logged_in() || !is_guru()) {
        header("Location: auth/login");
        exit;
    }
}