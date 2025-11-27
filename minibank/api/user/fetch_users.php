<?php
header('Content-Type: application/json; charset=utf-8');
$cfg = __DIR__ . '/../../includes/config.php';
if (!file_exists($cfg)) $cfg = __DIR__ . '/../../../includes/config.php';
require_once $cfg;
$dbpath = __DIR__ . '/../../includes/db.php';
if (!file_exists($dbpath)) $dbpath = __DIR__ . '/../../../includes/db.php';
require_once $dbpath;

$search = trim($_GET['search'] ?? '');
$filter_role = $_GET['filter_role'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';
$view_mode = $_GET['view_mode'] ?? 'users';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = max(1, min(100, (int)($_GET['per_page'] ?? 10)));
$offset = ($page - 1) * $per_page;

$params = array();
$where = array();

if ($view_mode === 'admins') {
    $sqlBase = "FROM admins WHERE 1=1";
    if ($search !== '') {
        $where[] = "(username LIKE ? OR admin_uid LIKE ? )";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($filter_date) {
        $where[] = 'DATE(created_at) = ?';
        $params[] = $filter_date;
    }
    $whereSql = count($where) ? (' AND ' . implode(' AND ', $where)) : '';
    $totalRow = db_query_one("SELECT COUNT(1) as c " . $sqlBase . $whereSql, $params);
    $total = (int)($totalRow['c'] ?? 0);
        $rows = db_query_all("SELECT id, username, 'admin' as role, created_at, admin_uid, NULL as rekening,
            (SELECT MAX(createdat) FROM transaksi WHERE created_by_admin_id = admins.admin_uid) as last_transaction " . $sqlBase . $whereSql . " ORDER BY username ASC LIMIT ? OFFSET ?", array_merge($params, array($per_page, $offset)));
} else {
    $sqlBase = "FROM users WHERE role IN ('user','guru')";
    if ($search !== '') {
        $where[] = "(username LIKE ? OR rekening LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($filter_role) {
        $where[] = 'role = ?';
        $params[] = $filter_role;
    }
    if ($filter_date) {
        $where[] = 'DATE(created_at) = ?';
        $params[] = $filter_date;
    }
    $whereSql = count($where) ? (' AND ' . implode(' AND ', $where)) : '';
    $totalRow = db_query_one("SELECT COUNT(1) as c " . $sqlBase . $whereSql, $params);
    $total = (int)($totalRow['c'] ?? 0);
        $rows = db_query_all("SELECT id, username, role, created_at, rekening, NULL as admin_uid,
            (SELECT MAX(createdat) FROM transaksi WHERE target_user_id = users.id) as last_transaction " . $sqlBase . $whereSql . " ORDER BY username ASC LIMIT ? OFFSET ?", array_merge($params, array($per_page, $offset)));
}

$html = '';
if (empty($rows)) {
    $html = '<tr><td colspan="7">Tidak ada data ditemukan.</td></tr>';
} else {
    foreach ($rows as $u) {
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($u['id']) . '</td>';
        $html .= '<td class="rekening-col"' . ($view_mode === 'admins' ? ' style="display:none"' : '') . '>' . htmlspecialchars($u['rekening'] ?? 'N/A') . '</td>';
        $html .= '<td class="admin-uid-col"' . ($view_mode === 'admins' ? '' : ' style="display:none"') . '>' . htmlspecialchars($u['admin_uid'] ?? 'N/A') . '</td>';
        $html .= '<td>' . htmlspecialchars($u['username']) . '</td>';
        $html .= '<td>' . htmlspecialchars($u['role']) . '</td>';
        $html .= '<td>' . htmlspecialchars(date('Y-m-d', strtotime($u['created_at']))) . '</td>';
        $html .= '<td>' . ($u['last_transaction'] ? htmlspecialchars(date('Y-m-d H:i', strtotime($u['last_transaction']))) : 'N/A') . '</td>';
        $html .= '<td class="action-buttons">';
        // Detail button (icon + text)
        $html .= '<button class="action-btn info" data-userid="' . htmlspecialchars($u['id']) . '" data-action="detail" title="Detail" aria-label="Detail">';
        $html .= '<svg class="action-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#fff" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm1 9h-2v-6h2v6z"/></svg>';
        $html .= '<span class="action-text">Detail</span>';
        $html .= '</button>';
        // Update button
        $html .= '<button class="action-btn update" data-userid="' . htmlspecialchars($u['id']) . '" data-action="update" title="Update" aria-label="Update">';
        $html .= '<svg class="action-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path fill="#fff" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34a.9959.9959 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>';
        $html .= '<span class="action-text">Update</span>';
        $html .= '</button>';
        $html .= '</td>';
        $html .= '</tr>';
    }
}

echo json_encode(array('html' => $html, 'total' => $total, 'page' => $page, 'per_page' => $per_page));

?>