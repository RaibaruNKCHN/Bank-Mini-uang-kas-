<?php
require 'config.php';

try {
    // create tables
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        type ENUM('deposit','withdraw') NOT NULL,
        note VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // insert seed user (username: user, password: password)
    $username = 'user';
    $password = 'password';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // cek dulu apakah user sudah ada
    $stmt = $pdo->prepare("SELECT id FROM user WHERE username = ?");
    $stmt->execute([$username]);
    if (!$stmt->fetch()) {
        $insert = $pdo->prepare("INSERT INTO user (username, password) VALUES (?, ?)");
        $insert->execute([$username, $hash]);
        echo "Tabel dibuat dan user seed ditambahkan. Username: user, Password: password<br>";
    } else {
        echo "Tabel dibuat. User seed sudah ada.<br>";
    }

    echo "Selesai. Hapus atau non-akses file setup.php setelah penggunaan untuk keamanan.";
} catch (PDOException $e) {
    die("Setup gagal: " . htmlspecialchars($e->getMessage()));
}
