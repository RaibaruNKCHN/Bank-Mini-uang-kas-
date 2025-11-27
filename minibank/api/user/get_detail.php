<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Accept either numeric id or a rekening string
$user_input = $_GET['id'] ?? '';
if (empty($user_input)) {
    echo json_encode(['success' => false, 'message' => 'User ID is required.']);
    exit;
}

// Resolve to internal user id (or admin id if numeric)
$user_id = resolve_userid_from_mixed($user_input);
if (is_null($user_id)) {
    echo json_encode(['success' => false, 'message' => 'User not found or invalid identifier.']);
    exit;
}

try {
    // 1. Get user details - check both tables
    $user = db_query_one("SELECT id, username, role, rekening, created_at, NULL as admin_uid FROM users WHERE id = ?", [$user_id]);
    $is_admin = false;
    if (!$user) {
        $user = db_query_one("SELECT id, username, 'admin' as role, NULL as rekening, created_at, admin_uid FROM admins WHERE id = ?", [$user_id]);
        $is_admin = true;
    }
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    // 2. Calculate balance (only for non-admins) and get transactions
    $balance = 0;
    $transactions = [];
    if (!$is_admin) {
        $totals = db_query_one("SELECT 
            IFNULL(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END),0) AS total_deposit,
            IFNULL(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END),0) AS total_withdraw
            FROM transaksi WHERE target_user_id = ?", [$user_id]);
        $balance = (float)$totals['total_deposit'] - (float)$totals['total_withdraw'];

        $transactions_query = "
            SELECT t.*, COALESCE(a.username, u.username) as actor_username 
            FROM transaksi t
            LEFT JOIN admins a ON t.created_by_admin_id = a.id
            LEFT JOIN users u ON t.created_by_user_id = u.id
            WHERE t.target_user_id = ? 
            ORDER BY t.createdat DESC 
            LIMIT 5";
        $transactions = db_query_all($transactions_query, [$user_id]);
    } else {
        // For admins, show transactions they CREATED
        $transactions_query = "
            SELECT t.*, COALESCE(a.username, u.username) as actor_username 
            FROM transaksi t
            LEFT JOIN admins a ON t.created_by_admin_id = a.id
            LEFT JOIN users u ON t.created_by_user_id = u.id
            WHERE t.created_by_admin_id = ? 
            ORDER BY t.createdat DESC 
            LIMIT 5";
        $transactions = db_query_all($transactions_query, [$user_id]);
    }

    $response = [
        'success' => true,
        'details' => [
            'user' => $user,
            'balance' => $balance,
            'transactions' => $transactions
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Error fetching user detail: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred.']);
}
?>