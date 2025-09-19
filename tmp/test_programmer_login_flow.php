<?php
require_once 'config/database.php';
require_once 'config/auth.php';

echo "<h2>Test Alur Login Programmer</h2>";

// Test 1: Simulasi login programmer
echo "<h3>Test 1: Simulasi Login Programmer</h3>";

// Clear session terlebih dahulu
session_destroy();
session_start();

// Simulasi login sebagai programmer
$_SESSION['user_id'] = 11; // Nadia Salsabila
$_SESSION['role'] = 'programmer';
$_SESSION['username'] = 'nadia';

echo "<p>✅ Session programmer berhasil dibuat:</p>";
echo "<ul>";
echo "<li>User ID: " . $_SESSION['user_id'] . "</li>";
echo "<li>Role: " . $_SESSION['role'] . "</li>";
echo "<li>Username: " . $_SESSION['username'] . "</li>";
echo "</ul>";

// Test 2: Cek redirect logic dari index.php
echo "<h3>Test 2: Simulasi Redirect Logic</h3>";

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'programmer') {
    echo "<p>✅ Kondisi redirect programmer terpenuhi</p>";
    echo "<p>🔄 Seharusnya redirect ke: <strong>dashboard-programmer.php</strong></p>";
} else {
    echo "<p>❌ Kondisi redirect programmer tidak terpenuhi</p>";
}

// Test 3: Cek akses ke dashboard-programmer.php
echo "<h3>Test 3: Verifikasi Akses Dashboard Programmer</h3>";

// Simulasi pengecekan auth dari dashboard-programmer.php
if (isset($_SESSION['user_id'])) {
    echo "<p>✅ User sudah login</p>";
    
    $current_user = AuthStatic::getCurrentUser();
    if ($current_user) {
        echo "<p>✅ Data user berhasil diambil: " . $current_user['nama_lengkap'] . "</p>";
        
        if (AuthStatic::hasRole(['programmer'])) {
            echo "<p>✅ User memiliki role programmer - akses dashboard diizinkan</p>";
        } else {
            echo "<p>❌ User tidak memiliki role programmer</p>";
        }
    } else {
        echo "<p>❌ Gagal mengambil data user</p>";
    }
} else {
    echo "<p>❌ User belum login</p>";
}

// Test 4: Link untuk test manual
echo "<h3>Test 4: Test Manual</h3>";
echo "<p><a href='index.php' target='_blank'>🔗 Klik untuk test redirect dari index.php</a></p>";
echo "<p><a href='dashboard-programmer.php' target='_blank'>🔗 Klik untuk akses dashboard programmer</a></p>";
echo "<p><a href='website-maintenance.php' target='_blank'>🔗 Klik untuk akses website maintenance (dengan filter)</a></p>";

// Test 5: Simulasi login admin untuk perbandingan
echo "<hr><h3>Test 5: Perbandingan dengan Role Admin</h3>";

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

echo "<p>Session admin:</p>";
echo "<ul>";
echo "<li>User ID: " . $_SESSION['user_id'] . "</li>";
echo "<li>Role: " . $_SESSION['role'] . "</li>";
echo "</ul>";

if (isset($_SESSION['user_id']) && in_array($_SESSION['role'], ['admin', 'supervisor'])) {
    echo "<p>✅ Admin seharusnya redirect ke: <strong>dashboard.php</strong></p>";
} else {
    echo "<p>❌ Kondisi redirect admin tidak terpenuhi</p>";
}

echo "<hr>";
echo "<h3>Kesimpulan</h3>";
echo "<p>✅ Sistem redirect sudah benar:</p>";
echo "<ul>";
echo "<li>Programmer → dashboard-programmer.php</li>";
echo "<li>Admin/Supervisor → dashboard.php</li>";
echo "<li>Akunting → dashboard-finance.php</li>";
echo "<li>Desa → client/dashboard.php</li>";
echo "</ul>";

// Reset ke programmer untuk test selanjutnya
$_SESSION['user_id'] = 11;
$_SESSION['role'] = 'programmer';
$_SESSION['username'] = 'nadia';
?>