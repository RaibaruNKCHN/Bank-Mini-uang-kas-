<?php
require '../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
if (!is_admin()) {
    header('Location: auth/login');
    exit;
}

$current_user_id = $_SESSION['userid'];
$username = $_SESSION['username'] ?? '';

// --- View Mode Logic ---
$view_mode = $_GET['view_mode'] ?? 'users'; // default to 'users' view
$search = $_GET['search'] ?? '';
$filter_role = $_GET['filter_role'] ?? '';
$filter_date = $_GET['filter_date'] ?? '';
$params = [];
$users = [];

if ($view_mode === 'admins') {
    // Query for admins (no superadmin verification required to view admin list)
    $sql = "SELECT id, username, 'admin' as role, created_at, admin_uid, NULL as rekening,
            (SELECT MAX(createdat) FROM transaksi WHERE created_by_admin_id = admins.id) as last_transaction
            FROM admins
            WHERE 1=1";
    if (!empty($search)) {
        $sql .= " AND (username LIKE ? OR admin_uid LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
} else {
    // Query for users and gurus
    $sql = "SELECT id, username, role, created_at, rekening, NULL as admin_uid,
            (SELECT MAX(createdat) FROM transaksi WHERE target_user_id = users.id) as last_transaction
            FROM users
            WHERE role IN ('user', 'guru')";
    if (!empty($search)) {
        $sql .= " AND (username LIKE ? OR rekening LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if (!empty($filter_role)) {
        $sql .= " AND role = ?";
        $params[] = $filter_role;
    }
}

if (!empty($filter_date)) {
    $sql .= " AND DATE(created_at) = ?";
    $params[] = $filter_date;
}

$sql .= " ORDER BY username ASC";
$users = db_query_all($sql, $params);

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini Bank - Kelola Akun</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/images/logo.png" type="image/png">
</head>
<body>
    <div class="dashboard-container">
        <nav class="navbar">
            <div class="navbar-brand">🏦 Mini Bank</div>
            <div class="navbar-nav">
                <a href="../auth/dashboard.php" class="btn btn-primary">Dashboard</a>
                <a href="../auth/logout.php" class="btn btn-danger">Logout</a>
            </div>
        </nav>

        <div class="card">
            <h2 class="card-title">Kelola Akun</h2>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <button id="createUserBtn" class="btn">Buat User Baru</button>
            </div>
        </div>
        
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
                <h3 class="card-title" id="tableTitle">Kelola User & Guru</h3>
                <div class="view-switcher">
                                <label for="adminViewSwitch">Admin</label>
                            <label class="switch">
                                <input type="checkbox" id="adminViewSwitch">
                                <span class="slider"></span>
                            </label>
                </div>
            </div>

            <!-- Filter and Search Form (shared partial) -->
            <?php
                // render shared filter partial in popup/modal mode for this admin page
                $include_sort = false; // kelola_akun doesn't need amount sort
                $use_popup = true; // request modal popup rendering in the partial
                include __DIR__ . '/../includes/filter_bar.php';
            ?>
            
            <div class="table-responsive-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th id="rekeningHeader" style="display: <?= $view_mode === 'admins' ? 'none' : 'table-cell' ?>">No. Rekening</th>
                            <th id="adminUidHeader" style="display: <?= $view_mode === 'admins' ? 'table-cell' : 'none' ?>">Admin UID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Tgl Terdaftar</th>
                            <th>Trans. Terakhir</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7">Tidak ada data ditemukan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($users as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['id']) ?></td>
                                <td class="rekening-col" style="display: <?= $view_mode === 'admins' ? 'none' : 'table-cell' ?>;"><?= htmlspecialchars($u['rekening'] ?? 'N/A') ?></td>
                                <td class="admin-uid-col" style="display: <?= $view_mode === 'admins' ? 'table-cell' : 'none' ?>;"><?= htmlspecialchars($u['admin_uid'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['role']) ?></td>
                                <td><?= htmlspecialchars(date('Y-m-d', strtotime($u['created_at']))) ?></td>
                                <td><?= $u['last_transaction'] ? htmlspecialchars(date('Y-m-d H:i', strtotime($u['last_transaction']))) : 'N/A' ?></td>
                                <td class="action-buttons">
                                    <button class="btn btn-info btn-sm" data-action="detail" data-userid="<?= htmlspecialchars($u['id']) ?>">Detail</button>
                                    <button class="btn btn-sm" data-action="update" data-userid="<?= htmlspecialchars($u['id']) ?>">Update</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Superadmin Code Modal -->
    <div id="superadminModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2 class="card-title">Verifikasi Superadmin</h2>
            <p>Masukkan kode akses superadmin untuk melihat daftar admin.</p>
            <form id="superadminForm" class="login-form">
                <div class="form-group">
                    <label for="superadmin-code">Kode Akses</label>
                    <input type="password" id="superadmin-code" class="form-control" required>
                </div>
                <div id="superadmin-error" class="alert alert-danger admin-access-error" style="display:none;"></div>
                <button type="submit" class="btn btn-primary">Verifikasi</button>
            </form>
        </div>
    </div>

    <!-- Update User Modal -->
    <div id="updateUserModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2 class="card-title">Update User</h2>
            <form id="updateUserForm" class="login-form">
                <!-- Form fields will be populated by JavaScript -->
            </form>
        </div>
    </div>

    <!-- Delete functionality removed -->

    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2 class="card-title">Buat User Baru</h2>
            <form id="createUserForm" class="login-form">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                <div class="form-group">
                    <label for="create-username">Username</label>
                    <input type="text" id="create-username" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="create-password">Password</label>
                    <input type="password" id="create-password" name="password" class="form-control" placeholder="Minimal 8 karakter" required>
                </div>
                <div class="form-group">
                    <label for="create-role">Role</label>
                    <select id="create-role" name="role" class="form-control" required>
                        <option value="user">User</option>
                        <option value="guru">Guru</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group" id="create-code-field" style="display: none;">
                    <label for="create-code">Kode (untuk admin/guru)</label>
                    <input type="text" id="create-code" name="code" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Buat User</button>
            </form>
        </div>
    </div>

    <!-- User Detail Modal -->
    <div id="userDetailModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2 class="card-title">Detail Akun</h2>
            <div id="userDetailContent">
                <!-- Content will be loaded here by JavaScript -->
                <p>Memuat data...</p>
            </div>
        </div>
    </div>

    <!-- Error reports UI removed to avoid exposing internal errors in admin UI. -->

    <!-- App toast (shared) -->
    <div id="appToast" class="app-toast" style="display:none;"> <span id="appToastMessage"></span> </div>
    <div id="toastLive" class="sr-only" aria-live="polite" aria-atomic="true"></div>
    <script>
        // Expose base url for client-side AJAX calls
        const BASE_URL = '<?= BASE_URL ?>';
        // Expose CSRF token for client-side actions
        const CSRF_TOKEN = '<?= htmlspecialchars(csrf_token()) ?>';
        window.CSRF_TOKEN = CSRF_TOKEN;
        const CURRENT_USER_ID = '<?= htmlspecialchars((int)$current_user_id) ?>';
        window.CURRENT_USER_ID = CURRENT_USER_ID;
    </script>
    <script src="../assets/js/accounts.js"></script>
</body>
</html>
