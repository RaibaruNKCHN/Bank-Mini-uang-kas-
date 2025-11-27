<?php
require '../includes/config.php';
logout();
// Redirect to auth login page
header('Location: ' . BASE_URL . 'auth/login.php');
exit;
?>