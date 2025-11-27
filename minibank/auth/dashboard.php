<?php
// include config from parent when dashboard.php is placed in htdocs/
$cfg = __DIR__ . '/includes/config.php';
if (!file_exists($cfg)) $cfg = __DIR__ . '/../includes/config.php';
require $cfg;
// db helper location
$dbpath = __DIR__ . '/includes/db.php';
if (!file_exists($dbpath)) $dbpath = __DIR__ . '/../includes/db.php';
require_once $dbpath;
if (!is_logged_in()) {
    header('Location: auth/login.php');
    exit;
}

// Handle success notification
$success_message = $_SESSION['success_message'] ?? '';
if (isset($_SESSION['success_message'])) {
    unset($_SESSION['success_message']); // Hapus pesan setelah ditampilkan
}

$current_user_id = $_SESSION['userid'];
$username = $_SESSION['username'] ?? ''; // This is the logged-in user's username
$current_user_role = $_SESSION['role'] ?? '';

// Ambil semua user untuk dropdown transaksi (hanya jika admin)
$users_for_dropdown = [];
if (is_admin()) {
    $users_for_dropdown = db_query_all("SELECT id, username, role, rekening FROM users ORDER BY role, username");
}

// --- Handle Filters ---
$search = trim($_GET['search'] ?? '');
$filter_date = $_GET['filter_date'] ?? '';
$sort_amount = $_GET['sort_amount'] ?? '';
$filter_role = $_GET['filter_role'] ?? '';

$order_by_clause = 'ORDER BY t.createdat DESC';
if ($sort_amount === 'asc') {
    $order_by_clause = 'ORDER BY t.amount ASC';
} elseif ($sort_amount === 'desc') {
    $order_by_clause = 'ORDER BY t.amount DESC';
}

// --- Data Scoping and Filtering Logic ---
$hist_where_conditions = [];
$hist_params = [];
$transactions_title = 'Histori Transaksi Anda'; // Default title

// Perhitungan saldo selalu untuk user yang sedang login
$balance_query_userid = $current_user_id;

if (is_admin()) {
    // Admin's base scope is transactions they created
    $hist_where_conditions[] = 't.created_by_admin_id = ?';
    $hist_params[] = $current_user_id;
    $transactions_title = 'Histori Transaksi (Input Anda)';
    $balance = 0; // Admins do not have a balance

    // Adjust scope for special admin views
    if (isset($_GET['view']) && $_GET['view'] === 'all') {
        $hist_where_conditions = ['1=1']; // Reset to view all
        $hist_params = [];
        $transactions_title = 'Histori Transaksi (Semua)';
    } elseif (isset($_GET['view_as']) && is_numeric($_GET['view_as'])) {
        $target_admin_id = (int)$_GET['view_as'];
        $hist_where_conditions = ['t.created_by_admin_id = ?'];
        $hist_params = [$target_admin_id];
        $target_admin_info = db_query_one("SELECT username FROM admins WHERE id = ?", [$target_admin_id]);
        $transactions_title = 'Histori Transaksi (Oleh ' . htmlspecialchars($target_admin_info['username'] ?? 'Unknown') . ')';
    }
} else {
    // Non-admin users see their own transactions
    $hist_where_conditions[] = 't.target_user_id = ?';
    $hist_params[] = $current_user_id;
    $balance_query_userid = $current_user_id;

    // Calculate balance for user/guru
    $totals = db_query_one("SELECT 
        IFNULL(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END),0) AS total_deposit,
        IFNULL(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END),0) AS total_withdraw
        FROM transaksi WHERE target_user_id = ?", [$balance_query_userid]);
    $balance = (float)$totals['total_deposit'] - (float)$totals['total_withdraw'];
}

// Apply universal filters on top of the determined scope
if (!empty($filter_date)) {
    $hist_where_conditions[] = 'DATE(t.createdat) = ?';
    $hist_params[] = $filter_date;
}
if (!empty($filter_role)) {
    $hist_where_conditions[] = 'u_target.role = ?';
    $hist_params[] = $filter_role;
}

// Free-text search: allow searching by username or rekening (works with or without other filters)
if (!empty($search)) {
    $hist_where_conditions[] = '(u_target.username LIKE ? OR u_target.rekening LIKE ?)';
    $hist_params[] = '%' . $search . '%';
    $hist_params[] = '%' . $search . '%';
}

// Build the final history query with polymorphic joins
$hist_query_base = "
    SELECT 
        t.*, 
        u_target.username AS target_username,
        COALESCE(a_actor.username, u_actor.username) AS actor_username
    FROM transaksi t
    INNER JOIN users u_target ON t.target_user_id = u_target.id
    LEFT JOIN admins a_actor ON t.created_by_admin_id = a_actor.id
    LEFT JOIN users u_actor ON t.created_by_user_id = u_actor.id
";

$hist_query_full = $hist_query_base . " WHERE " . implode(' AND ', $hist_where_conditions) . " {$order_by_clause} LIMIT 100";
$transactions = db_query_all($hist_query_full, $hist_params);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini Bank - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="icon" href="<?= BASE_URL ?>assets/images/logo2.png" type="image/png">
</head>
<body>
    <div class="dashboard-container">
        <nav class="navbar">
            <div class="navbar-brand">
                <img src="<?= BASE_URL ?>assets/images/logo2.png" alt="Mini Bank Logo" class="navbar-logo">
                Mini Bank
            </div>
            <div class="navbar-nav">
                <?php if (is_admin()): ?>
                    <button id="mainMenuBtn" class="btn btn-primary">Menu</button>
                    <!-- Removed redundant gear button; settings available in Menu -->
                <?php endif; ?>
                <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-danger">Logout</a>
            </div>
        </nav>

        <!-- Main modal used for Menu and Settings panels -->
        <?php if (is_admin()): ?>
        <div id="mainModal" class="modal" aria-hidden="true">
            <div class="modal-content modal-menu" role="dialog" aria-modal="true" aria-labelledby="mainModalTitle">
                <div class="panel-header" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding-bottom:8px;border-bottom:1px solid var(--border-color);">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <button id="panelBackBtn" class="btn-icon btn-slim" style="display:none;" aria-label="Kembali"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                        <h3 id="mainModalTitle" style="margin:0; font-size:1.05rem;">Menu</h3>
                    </div>
                    <button class="modal-close" id="mainModalClose" aria-label="Tutup"><i class="fa fa-times" aria-hidden="true"></i></button>
                </div>
                <div class="modal-body" style="padding-top:10px;">
                    <div class="modal-panel" data-panel="menuPanel" aria-hidden="false">
                        <div style="display:flex;flex-direction:column;gap:8px;">
                            <button id="manageAccountsBtn" class="btn btn-block">Kelola Akun</button>
                            <button id="viewAllTransactionsBtn" class="btn btn-block">Lihat Semua Transaksi</button>
                            <button id="viewAsAnotherAdminBtn" class="btn btn-block">Lihat Transaksi Akun Lain</button>
                            <button id="openSettingsFromMenu" class="btn btn-block">Pengaturan</button>
                        </div>
                    </div>

                    <div class="modal-panel" data-panel="settingsPanel" aria-hidden="true" style="display:none;">
                        <h4 style="margin-top:0;">Pengaturan Sesi</h4>
                        <div style="padding:0.5rem 0;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <span>Aktifkan sesi timeout</span>
                                </div>
                                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                                    <label class="switch" style="margin:0;">
                                        <input type="checkbox" id="toggleTimeoutCheckbox">
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="session-time-row" style="margin-top:0.75rem;">
                                <div style="display:flex;justify-content:space-between;align-items:center;width:100%;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <span class="session-time-label">Waktu</span>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div id="timeoutMinutesDisplay" class="session-time-value">--</div>
                                        <button id="changeTimeoutBtn" class="btn btn-primary btn-sm session-time-change">Ubah</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <header class="welcome-header">
            <h2>Selamat Datang, <?=htmlspecialchars($username)?>!</h2>
        </header>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><?=htmlspecialchars($success_message)?></div>
        <?php endif; ?>

        <?php if (is_admin()): ?>
            <!-- Admin Dashboard -->
            <div class="card">
                <h3 class="card-title">Transaksi</h3>
                
                <h4>Deposit</h4>
                <form action="<?= BASE_URL ?>api/transactions/transaksi.php" method="post">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <input type="hidden" name="type" value="deposit">
                    <div class="form-group-inline">
                        <label for="deposit-userid">Pilih User/Guru</label>
                        <select id="deposit-userid" class="form-control input-slim account-search" style="width:100%;"></select>
                        <input type="hidden" name="rekening_tujuan" id="deposit-rekening-hidden">
                    </div>
                    <div class="form-group-inline">
                        <label for="deposit-amount-display">Jumlah</label>
                        <div class="currency-input" style="width:100%;">
                            <span class="currency-prefix">Rp</span>
                            <input type="text" id="deposit-amount-display" class="form-control input-slim currency-display" required>
                            <div class="currency-controls">
                                <button type="button" class="btn-step" data-target="#deposit-amount-display" data-step="2000">+</button>
                                <button type="button" class="btn-step" data-target="#deposit-amount-display" data-step="-2000">−</button>
                            </div>
                            <input type="hidden" name="amount" id="deposit-amount" value="">
                        </div>
                    </div>
                    <div class="form-group-inline">
                        <label for="deposit-note">Catatan</label>
                        <input type="text" name="note" id="deposit-note" class="form-control input-slim">
                    </div>
                    <button type="submit" class="btn">Lakukan Deposit</button>
                </form>

                <hr style="margin: 2rem 0;">

                <h4>Withdraw</h4>
                <form action="<?= BASE_URL ?>api/transactions/transaksi.php" method="post">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <input type="hidden" name="type" value="withdraw">
                    <div class="form-group-inline">
                        <label for="withdraw-userid">Pilih User/Guru</label>
                        <select id="withdraw-userid" class="form-control input-slim account-search" style="width:100%;"></select>
                        <input type="hidden" name="rekening_tujuan" id="withdraw-rekening-hidden">
                    </div>
                    <div class="form-group-inline">
                        <label for="withdraw-amount-display">Jumlah</label>
                        <div class="currency-input" style="width:100%;">
                            <span class="currency-prefix">Rp</span>
                            <input type="text" id="withdraw-amount-display" class="form-control input-slim currency-display" required>
                            <div class="currency-controls">
                                <button type="button" class="btn-step" data-target="#withdraw-amount-display" data-step="2000">+</button>
                                <button type="button" class="btn-step" data-target="#withdraw-amount-display" data-step="-2000">−</button>
                            </div>
                            <input type="hidden" name="amount" id="withdraw-amount" value="">
                        </div>
                    </div>
                    <div class="form-group-inline">
                        <label for="withdraw-note">Catatan</label>
                        <input type="text" name="note" id="withdraw-note" class="form-control input-slim">
                    </div>
                    <button type="submit" class="btn">Lakukan Withdraw</button>
                </form>
            </div>

        <?php else: ?>
            <!-- User/Guru Dashboard -->
            <div class="card saldo-card">
                <h2 class="card-title">Saldo Anda</h2>
                <div class="saldo-value">Rp <?=number_format($balance, 2, ',', '.')?></div>
                <p style="text-align: center; margin-top: 1rem;">Ini adalah ringkasan saldo Anda saat ini.</p>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2 class="card-title"><?= htmlspecialchars($transactions_title) ?></h2>
            
            <!-- Filter and Search Form (shared partial) -->
            <?php
                // dashboard needs the amount sort control
                $include_sort = true;
                // Render the shared filter as a popup/modal on dashboard as well
                $use_popup = true;
                include __DIR__ . '/../includes/filter_bar.php';
            ?>

            <div class="table-responsive-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Jumlah</th>
                            <th>Catatan</th>
                            <th>Target Akun</th>
                            <th>Rekening Tujuan</th>
                            <th>Dilakukan Oleh</th>
                            <?php if(is_admin()): ?><th>Aksi</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="<?= is_admin() ? '7' : '6' ?>">Tidak ada transaksi ditemukan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($transactions as $t): ?>
                                <tr>
                                    <td><?=htmlspecialchars($t['id'])?></td>
                                    <td><?=htmlspecialchars($t['createdat'])?></td>
                                    <td><?=htmlspecialchars(ucfirst($t['type']))?></td>
                                    <td>Rp <?=number_format($t['amount'], 2, ',', '.')?></td>
                                    <td><?=htmlspecialchars($t['note'])?></td>
                                    <td><?=htmlspecialchars($t['target_username'])?></td>
                                    <td><?=htmlspecialchars($t['rekening_tujuan'])?></td>
                                    <td><?=htmlspecialchars($t['actor_username'])?></td>
                                    <?php if(is_admin()): ?>
                                        <td><button class="btn btn-danger btn-sm delete-history-btn" data-transactionid="<?=htmlspecialchars($t['id'])?>">Hapus</button></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>

    <!-- Success Notification Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <span class="modal-close success-close">&times;</span>
            <h2 class="card-title">Sukses!</h2>
            <p id="success-message"></p>
            <div style="text-align: right; margin-top: 2rem;">
                <button class="btn btn-primary success-close">Oke</button>
            </div>
        </div>
    </div>

    <?php if (is_admin()): ?>
        <!-- Global Access Code Modal -->
        <div id="globalAccessModal" class="modal">
            <div class="modal-content">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <button id="globalAccessBack" class="btn-icon btn-slim" aria-label="Kembali"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                    <span style="flex:1"></span>
                    <button class="modal-close" aria-label="Tutup"><i class="fa fa-times" aria-hidden="true"></i></button>
                </div>
                <h2 class="card-title">Akses Semua Transaksi</h2>
                <p>Masukkan kode akses super admin untuk melihat semua histori transaksi.</p>
                <form id="globalAccessForm" class="login-form">
                    <input type="hidden" name="action" value="verify_global_access">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <div class="form-group">
                        <label for="global-access-code">Kode Akses</label>
                        <input type="password" id="global-access-code" name="access_code" class="form-control" required>
                    </div>
                    <div id="global-access-error" class="alert alert-danger" style="display:none; margin-top: 1rem;"></div>
                    <button type="submit" class="btn btn-primary btn-block">Verifikasi</button>
                </form>
            </div>
        </div>

        <!-- View As Another Admin Modal -->
        <div id="viewAsAnotherAdminModal" class="modal">
            <div class="modal-content">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <button id="viewAsBack" class="btn-icon btn-slim" aria-label="Kembali"><i class="fa fa-chevron-left" aria-hidden="true"></i></button>
                    <span style="flex:1"></span>
                    <button class="modal-close" aria-label="Tutup"><i class="fa fa-times" aria-hidden="true"></i></button>
                </div>
                <h2 class="card-title">Lihat sebagai Admin Lain</h2>
                <p>Pilih admin dan masukkan kata sandi Anda untuk melihat histori inputnya.</p>
                <form id="viewAsAnotherAdminForm" class="login-form">
                    <input type="hidden" name="action" value="verify_admin_view_password">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <div class="form-group">
                        <label for="target-admin-id">Pilih Admin</label>
                        <select id="target-admin-id" name="target_admin_id" class="form-control" required>
                            <option value="">-- Pilih Admin --</option>
                            <?php
                            $all_admins = db_query_all("SELECT id, username FROM admins ORDER BY username");
                            foreach($all_admins as $admin_user): ?>
                                <option value="<?=htmlspecialchars($admin_user['id'])?>"><?=htmlspecialchars($admin_user['username'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="your-admin-password">Kata Sandi Anda</label>
                        <input type="password" id="your-admin-password" name="your_admin_password" class="form-control" required>
                    </div>
                    <div id="view-as-error" class="alert alert-danger" style="display:none; margin-top: 1rem;"></div>
                    <button type="submit" class="btn btn-primary btn-block">Lihat Histori</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Change Timeout Modal (superadmin verification) -->
    <?php if (is_admin()): ?>
    <div id="changeTimeoutModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="changeTimeoutTitle">
        <div class="modal-content">
            <button class="modal-close" id="changeTimeoutClose" aria-label="Tutup">&times;</button>
            <h2 id="changeTimeoutTitle" class="modal-title card-title">Ubah Waktu Timeout</h2>
            <div class="modal-body">
                <p>Masukkan kode akses superadmin dan waktu baru.</p>
                <form id="changeTimeoutForm" class="login-form">
                    <input type="hidden" name="action" value="set_timeout">
                    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
                    <div class="form-group">
                        <label for="superadmin-code">Kode Akses Superadmin</label>
                        <input type="password" id="superadmin-code" name="super_code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="timeout-minutes">Waktu (menit)</label>
                        <input type="number" id="timeout-minutes" name="minutes" class="form-control" min="1" required>
                    </div>
                    <div id="change-time-error" class="alert alert-danger" style="display:none; margin-top: 1rem;"></div>
                    <div class="modal-footer">
                        <button type="button" id="changeTimeoutCancel" class="btn btn-secondary">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Toast notification -->
    <div id="appToast" class="app-toast" style="display:none;"> <span id="appToastMessage"></span> </div>
    <!-- ARIA live region for toast announcements (screen readers) -->
    <div id="toastLive" class="sr-only" aria-live="polite" aria-atomic="true"></div>

    <!-- Delete History Modal (used by admin to confirm deletion of a transaction) -->
    <?php if (is_admin()): ?>
    <div id="deleteHistoryModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2 class="card-title">Konfirmasi Hapus Transaksi</h2>
            <p>Apakah Anda yakin ingin menghapus transaksi dengan ID <strong id="delete-transactionid"></strong> ?</p>
            <div style="text-align: right; margin-top: 2rem;">
                <button id="cancelDeleteHistoryBtn" class="btn btn-secondary">Batal</button>
                <button id="confirmDeleteHistoryBtn" class="btn btn-danger">Hapus</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Mobile settings moved into main modal; mobile-specific modal removed -->

    <script>
        // Pass Base URL to JavaScript
        const BASE_URL = '<?= BASE_URL ?>';

        // Pass CSRF token and global settings to JavaScript for admin UI
        const CSRF_TOKEN = '<?= htmlspecialchars(csrf_token()) ?>';
        window.CSRF_TOKEN = CSRF_TOKEN;
        window.GLOBAL_SETTINGS = <?= json_encode($GLOBAL_SETTINGS) ?>;

        <?php if (!empty($success_message)): ?>
            window.PHP_SUCCESS_MESSAGE = "<?= htmlspecialchars($success_message) ?>";
        <?php endif; ?>
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/dashboard.js?v=<?= time() ?>"></script>
    <script>
    // Initialize Select2 for account search
    $(document).ready(function() {
        $('.account-search').each(function() {
            const $select = $(this);
            const isDeposit = $select.attr('id').includes('deposit');
            const $hiddenInput = isDeposit ? $('#deposit-rekening-hidden') : $('#withdraw-rekening-hidden');

            $select.select2({
                placeholder: '-- Cari Nama atau Rekening --',
                minimumInputLength: 1,
                ajax: {
                    url: BASE_URL + 'api/user/search_accounts.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term // search term
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results
                        };
                    },
                    cache: true
                }
            })
            // When select2 opens, focus the search input for immediate typing
            .on('select2:open', function () {
                setTimeout(function () {
                    $('.select2-container--open .select2-search__field').focus();
                }, 30);
            })
            .on('select2:select', function (e) {
                const data = e.params.data;
                if (data && data.id) {
                    $hiddenInput.val(data.id); // Set the hidden input with the selected 'rekening'
                }
            }).on('select2:unselect', function (e) {
                $hiddenInput.val(''); // Clear the hidden input
            });

            // Open the Select2 instance when the native select receives focus or when the user starts typing
            // This enables immediate typing/search without auto-opening on page refresh.
            $select.on('focus', function () {
                try { $select.select2('open'); } catch (e) { /* ignore */ }
            });

            // If user types while the select has focus, open it so characters go to the search field
            $select.on('keydown', function (e) {
                // Open on any printable key or backspace so typing immediately triggers search
                const printable = e.key && e.key.length === 1 || e.key === 'Backspace' || e.key === 'Delete';
                if (printable) {
                    try { $select.select2('open'); } catch (err) { /* ignore */ }
                }
            });
        });
    });
    </script>
</body>
</html>
