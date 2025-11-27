<?php
header('Content-Type: application/json; charset=utf-8');
// Returns JSON: { html: '<tr>...</tr>', total: N }
$cfg = __DIR__ . '/../../includes/config.php';
if (!file_exists($cfg)) $cfg = __DIR__ . '/../../../includes/config.php';
require_once $cfg;
$dbpath = __DIR__ . '/../../includes/db.php';
if (!file_exists($dbpath)) $dbpath = __DIR__ . '/../../../includes/db.php';
require_once $dbpath;

// Get params
$search = trim($_GET['search'] ?? '');
$filter_date = $_GET['filter_date'] ?? '';
$filter_role = $_GET['filter_role'] ?? '';
$sort_amount = $_GET['sort_amount'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(1, min(100, (int)($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $per_page;

$current_user_id = $_SESSION['userid'] ?? null;
$is_admin = function_exists('is_admin') && is_admin();

$hist_where_conditions = [];
$hist_params = [];

if ($is_admin) {
    $hist_where_conditions[] = 't.created_by_admin_id = ?';
    $hist_params[] = $current_user_id;
    if (isset($_GET['view']) && $_GET['view'] === 'all') {
        $hist_where_conditions = ['1=1'];
        $hist_params = [];
    } elseif (isset($_GET['view_as']) && is_numeric($_GET['view_as'])) {
        $target_admin_id = (int)$_GET['view_as'];
        $hist_where_conditions = ['t.created_by_admin_id = ?'];
        $hist_params = [$target_admin_id];
    }
} else {
    $hist_where_conditions[] = 't.target_user_id = ?';
    $hist_params[] = $current_user_id;
}

if (!empty($filter_date)) {
    $hist_where_conditions[] = 'DATE(t.createdat) = ?';
    $hist_params[] = $filter_date;
}
if (!empty($filter_role)) {
    $hist_where_conditions[] = 'u_target.role = ?';
    $hist_params[] = $filter_role;
}
if (!empty($search)) {
    $hist_where_conditions[] = '(u_target.username LIKE ? OR u_target.rekening LIKE ?)';
    $hist_params[] = '%' . $search . '%';
    $hist_params[] = '%' . $search . '%';
}

$order_by_clause = 'ORDER BY t.createdat DESC';
if ($sort_amount === 'asc') $order_by_clause = 'ORDER BY t.amount ASC';
if ($sort_amount === 'desc') $order_by_clause = 'ORDER BY t.amount DESC';

$base = "
    FROM transaksi t
    INNER JOIN users u_target ON t.target_user_id = u_target.id
    LEFT JOIN admins a_actor ON t.created_by_admin_id = a_actor.id
    LEFT JOIN users u_actor ON t.created_by_user_id = u_actor.id
";
$where = count($hist_where_conditions) ? (' WHERE ' . implode(' AND ', $hist_where_conditions)) : '';

// total count
$totalSql = "SELECT COUNT(1) as c " . $base . $where;
$totalRow = db_query_one($totalSql, $hist_params);
$total = (int)($totalRow['c'] ?? 0);

// fetch rows
$sql = "SELECT t.*, u_target.username AS target_username, COALESCE(a_actor.username, u_actor.username) AS actor_username " . $base . $where . " {$order_by_clause} LIMIT ? OFFSET ?";
$params = $hist_params;
$params[] = $per_page;
$params[] = $offset;
$rows = db_query_all($sql, $params);

// render html rows
$html = '';
if (empty($rows)) {
    $colspan = (is_admin() ? 8 : 7);
    $html = '<tr><td colspan="' . $colspan . '">Tidak ada transaksi ditemukan.</td></tr>';
} else {
    foreach ($rows as $t) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($t['id']) . '</td>';
        $html .= '<td>' . htmlspecialchars($t['createdat']) . '</td>';
        $html .= '<td>' . htmlspecialchars(ucfirst($t['type'])) . '</td>';
        $html .= '<td>Rp ' . number_format($t['amount'], 2, ',', '.') . '</td>';
        $html .= '<td>' . htmlspecialchars($t['note']) . '</td>';
        $html .= '<td>' . htmlspecialchars($t['target_username']) . '</td>';
        $html .= '<td>' . htmlspecialchars($t['rekening_tujuan']) . '</td>';
        $html .= '<td>' . htmlspecialchars($t['actor_username']) . '</td>';
        if (is_admin()) {
            $html .= '<td class="action-buttons">';
            $html .= '<button class="action-btn danger" data-transactionid="' . htmlspecialchars($t['id']) . '" data-action="delete-transaction" title="Hapus" aria-label="Hapus">';
            $html .= '<svg class="action-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#fff" d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
            $html .= '<span class="action-text">Hapus</span>';
            $html .= '</button>';
            $html .= '</td>';
        }
        $html .= '</tr>';
    }
}

echo json_encode(['html' => $html, 'total' => $total, 'page' => $page, 'per_page' => $per_page]);

?>