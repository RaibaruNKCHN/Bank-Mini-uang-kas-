<?php
// Load config from repo includes; this file may be located either in project root
// or inside htdocs in different deployments. Try both locations.
$configPath = __DIR__ . '/minibank/includes/config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../minibank/includes/config.php';
}
require $configPath;

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'auth/dashboard.php');
} else {
    // auth lives inside minibank/ when hosted per deployment layout
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
