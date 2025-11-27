<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

// Only allow logged-in admins to access this endpoint
if (!is_logged_in() || !is_admin()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$search_term = $_GET['q'] ?? '';

// Prevent search with less than 2 characters for performance
if (strlen($search_term) < 1 && !isset($_GET['init'])) {
    echo json_encode(['results' => []]);
    exit;
}

$params = [];
$sql = "SELECT id, rekening, username, role FROM users WHERE role IN ('user','guru')";

if (!empty($search_term)) {
    // Normalize search term (strip non-digits for numeric checks)
    $digitsOnly = preg_replace('/\D+/', '', $search_term);
    // Search by full rekening, last 5 digits of rekening, username, or user ID
    $sql .= " AND (rekening = ? OR rekening LIKE ? OR username LIKE ? OR id = ?)";
    $params[] = $digitsOnly !== '' ? $digitsOnly : $search_term; // allow typing full rekening digits
    $params[] = '%' . substr($digitsOnly !== '' ? $digitsOnly : $search_term, -5); // last 5 digits
    $params[] = '%' . $search_term . '%'; // username
    $params[] = is_numeric($search_term) ? $search_term : 0; // user id (numeric)
}

$sql .= " ORDER BY username ASC LIMIT 20";

try {
    $users = db_query_all($sql, $params);

    $results = [];
    foreach ($users as $user) {
        $results[] = [
            'id' => $user['rekening'], // Return 'rekening' as the ID for the select box
            'text' => htmlspecialchars($user['username'] . ' (' . $user['rekening'] . ')')
        ];
    }

    echo json_encode(['results' => $results]);

} catch (PDOException $e) {
    error_log('Account search API error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database query failed']);
}
?>