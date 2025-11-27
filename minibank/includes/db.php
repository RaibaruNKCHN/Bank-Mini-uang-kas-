<?php
// Simple DB helper wrapper around global $pdo for safer queries
if (!defined('BASE_URL')) {
    // ensure config loaded
    require_once __DIR__ . '/config.php';
}

function _db_safe_checks(string $sql, array $params = []) {
    // Block obvious multi-statement attempts
    if (strpos($sql, ';') !== false) {
        error_log("DB helper: multi-statement detected in SQL: " . $sql);
        return false;
    }

    // Check placeholder count vs params count for positional ? placeholders
    $placeholders = substr_count($sql, '?');
    if ($placeholders !== count($params)) {
        error_log("DB helper: placeholder count mismatch (placeholders={$placeholders}, params=" . count($params) . ") for SQL: " . $sql);
        return false;
    }

    return true;
}

function _db_bind_params(PDOStatement $stmt, array $params = []) {
    $i = 1;
    foreach ($params as $p) {
        if (is_int($p)) {
            $type = PDO::PARAM_INT;
        } elseif (is_bool($p)) {
            $type = PDO::PARAM_BOOL;
        } elseif (is_null($p)) {
            $type = PDO::PARAM_NULL;
        } else {
            $type = PDO::PARAM_STR;
        }
        $stmt->bindValue($i++, $p, $type);
    }
}

function db_query_all(string $sql, array $params = []) {
    global $pdo;
    try {
        if (!_db_safe_checks($sql, $params)) return [];
        $stmt = $pdo->prepare($sql);
        _db_bind_params($stmt, $params);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('db_query_all error: ' . $e->getMessage() . ' SQL: ' . $sql);
        return [];
    }
}

function db_query_one(string $sql, array $params = []) {
    global $pdo;
    try {
        if (!_db_safe_checks($sql, $params)) return false;
        $stmt = $pdo->prepare($sql);
        _db_bind_params($stmt, $params);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('db_query_one error: ' . $e->getMessage() . ' SQL: ' . $sql);
        return false;
    }
}

function db_execute(string $sql, array $params = []) {
    global $pdo;
    try {
        if (!_db_safe_checks($sql, $params)) return false;
        $stmt = $pdo->prepare($sql);
        _db_bind_params($stmt, $params);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log('db_execute error: ' . $e->getMessage() . ' SQL: ' . $sql);
        return false;
    }
}

/**
 * Resolve a user identifier that may be either a numeric internal id or a rekening string.
 * Returns integer user id on success, or null if not found/invalid.
 */
function resolve_userid_from_mixed($val) {
    // normalize
    $v = is_string($val) ? trim($val) : (string)$val;
    if ($v === '') return null;

    // Recognize rekening pattern: starts with 62 and at least 11 digits total (62 + year(4) + seq(5) = 11)
    if (preg_match('/^62\d{9,}$/', $v)) {
        // try to find user by rekening
        $row = db_query_one("SELECT id FROM users WHERE rekening = ?", [$v]);
        return $row['id'] ?? null;
    }

    // If purely numeric and reasonably small, treat as internal id
    if (ctype_digit($v)) {
        // log legacy usage for discovery
        error_log("resolve_userid_from_mixed: numeric identifier used directly: {$v}");
        return (int)$v;
    }

    return null;
}
