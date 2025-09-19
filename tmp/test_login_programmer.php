<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Script untuk test login sebagai programmer
if (isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    
    // Simulasi login
    $_SESSION['user_id'] = $user_id;
    
    // Get user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        
        echo "<h2>Login berhasil!</h2>";
        echo "<p>User: " . $user['nama_lengkap'] . " (" . $user['username'] . ")</p>";
        echo "<p>Role: " . $user['role'] . "</p>";
        echo "<p><a href='website-maintenance.php'>Lihat Website Maintenance</a></p>";
        echo "<p><a href='test_login_programmer.php'>Kembali ke halaman test</a></p>";
    } else {
        echo "<p>User tidak ditemukan!</p>";
    }
} else {
    // Tampilkan form untuk memilih user
    echo "<h2>Test Login Programmer</h2>";
    echo "<p>Pilih user untuk login:</p>";
    
    // Get all programmers
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'programmer' AND status = 'aktif'");
    $stmt->execute();
    $programmers = $stmt->fetchAll();
    
    echo "<h3>Programmer:</h3>";
    foreach ($programmers as $programmer) {
        echo "<p><a href='?user_id=" . $programmer['id'] . "'>Login sebagai " . $programmer['nama_lengkap'] . " (" . $programmer['username'] . ")</a></p>";
    }
    
    // Get admin users
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' AND status = 'aktif' LIMIT 2");
    $stmt->execute();
    $admins = $stmt->fetchAll();
    
    echo "<h3>Admin (untuk perbandingan):</h3>";
    foreach ($admins as $admin) {
        echo "<p><a href='?user_id=" . $admin['id'] . "'>Login sebagai " . $admin['nama_lengkap'] . " (" . $admin['username'] . ")</a></p>";
    }
    
    // Show current session if any
    if (isset($_SESSION['user_id'])) {
        $current_user = AuthStatic::getCurrentUser();
        if ($current_user) {
            echo "<hr><p><strong>Saat ini login sebagai:</strong> " . $current_user['nama_lengkap'] . " (" . $current_user['role'] . ")</p>";
            echo "<p><a href='website-maintenance.php'>Lihat Website Maintenance</a></p>";
        }
    }
}
?>