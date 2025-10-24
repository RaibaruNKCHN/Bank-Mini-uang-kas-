<?php
require 'config.php';
try {
    $pdo->exec('ALTER TABLE user ADD COLUMN role ENUM("admin","guru","user") DEFAULT "user"');
    echo 'Role column added successfully.';
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
