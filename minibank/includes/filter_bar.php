<?php
// Generic filter bar partial
// Usage:
//  $include_sort = true|false; // include amount sort select (dashboard)
//  $search = $search ?? '';
//  $filter_role = $filter_role ?? '';
//  $filter_date = $filter_date ?? '';

$include_sort = isset($include_sort) ? (bool)$include_sort : false;
$search = $_GET['search'] ?? ($search ?? '');
$filter_role = $_GET['filter_role'] ?? ($filter_role ?? '');
$filter_date = $_GET['filter_date'] ?? ($filter_date ?? '');

// Preserve other GET params (like view, view_as, view_mode) when rendering the form
$preserve_keys = ['search','filter_role','filter_date','sort_amount'];
?>
<form method="get" class="filter-form" id="globalFilterForm">
    <?php foreach($_GET as $k => $v): ?>
        <?php if (in_array($k, $preserve_keys, true)) continue; ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>">
    <?php endforeach; ?>

    <input type="hidden" name="view_mode" id="viewModeHidden" value="<?= htmlspecialchars($view_mode ?? ($_GET['view_mode'] ?? 'users')) ?>">

    <!-- Hidden authoritative fields that modal/dropdown copies into before submit -->
    <input type="hidden" name="search" id="global-search" value="<?= htmlspecialchars($search) ?>">
    <input type="hidden" name="filter_role" id="global-filter-role" value="<?= htmlspecialchars($filter_role) ?>">
    <input type="hidden" name="filter_date" id="global-filter-date" value="<?= htmlspecialchars($filter_date) ?>">
    <?php if ($include_sort): ?>
        <input type="hidden" name="sort_amount" id="global-sort-amount" value="<?= htmlspecialchars($_GET['sort_amount'] ?? '') ?>">
    <?php endif; ?>

    <!-- Backwards-compatible IDs used by existing JS (duplicated hidden fields) -->
    <input type="hidden" name="search" id="filter-search" value="<?= htmlspecialchars($search) ?>">
    <input type="hidden" name="filter_role" id="filter-role" value="<?= htmlspecialchars($filter_role) ?>">
    <input type="hidden" name="filter_date" id="filter-date" value="<?= htmlspecialchars($filter_date) ?>">
    <?php if ($include_sort): ?>
        <input type="hidden" name="sort_amount" id="sort-amount" value="<?= htmlspecialchars($_GET['sort_amount'] ?? '') ?>">
    <?php endif; ?>

</form>

<!-- Mobile compact bar: visible on small screens only -->
<div class="filter-mobile-bar">
    <input id="filter-search-mobile" type="search" class="form-control" placeholder="Cari Username..." value="<?= htmlspecialchars($search) ?>">
    <button id="filter-search-btn-mobile" class="btn" aria-label="Cari" title="Cari tanpa menerapkan filter">Cari</button>
    <button id="filter-open-modal" class="btn btn-secondary" aria-haspopup="true" aria-controls="filterModal">Filter</button>
</div>

<!-- Filter Modal (used across pages) -->
<div id="filterModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" id="filterModalClose" aria-label="Tutup">&times;</button>
        <h2 class="card-title">Sesuaikan Filter</h2>
        <form id="filterModalForm" class="modal-filter-form">
            <div class="form-group">
                <label for="modal-filter-role">Role</label>
                <select id="modal-filter-role" name="filter_role" class="form-control">
                    <option value="">-- Semua Role --</option>
                    <option value="user" <?= $filter_role === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="guru" <?= $filter_role === 'guru' ? 'selected' : '' ?>>Guru</option>
                    <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="modal-filter-date">Tanggal</label>
                <input id="modal-filter-date" type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <?php if ($include_sort): ?>
            <div class="form-group">
                <label for="modal-sort-amount">Urutkan Jumlah</label>
                <select id="modal-sort-amount" name="sort_amount" class="form-control">
                    <option value="">-- Default --</option>
                    <option value="asc" <?= (($_GET['sort_amount'] ?? '') === 'asc') ? 'selected' : '' ?>>Terendah</option>
                    <option value="desc" <?= (($_GET['sort_amount'] ?? '') === 'desc') ? 'selected' : '' ?>>Tertinggi</option>
                </select>
            </div>
            <?php endif; ?>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" id="filterModalCancel" class="btn btn-secondary" data-filter-cancel>Batal</button>
                <button type="button" id="filterModalApply" class="btn btn-primary" data-filter-apply>Terapkan</button>
            </div>
            </form>
        </div>
    </div>

<?php
// End partial
?>
