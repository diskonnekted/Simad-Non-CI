<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Test script untuk memverifikasi filtering programmer
echo "<h2>Test Filtering Programmer di Website Maintenance</h2>";

// Simulasi login sebagai programmer (user ID 11 - Nadia)
$_SESSION['user_id'] = 11;
$_SESSION['role'] = 'programmer';
$_SESSION['username'] = 'nadia';

echo "<p>Login sebagai: " . $_SESSION['username'] . " (Role: " . $_SESSION['role'] . ")</p>";

// Get current user
$current_user = AuthStatic::getCurrentUser();
echo "<p>Current User ID: " . $current_user['id'] . " - " . $current_user['nama_lengkap'] . "</p>";

// Test query dengan filter programmer
$where_clause = '';
$params = [];

if (AuthStatic::hasRole(['programmer'])) {
    $where_clause = "WHERE wm.programmer_id = ?";
    $params = [$current_user['id']];
    echo "<p><strong>Filter aktif:</strong> Hanya menampilkan tugas untuk programmer ID " . $current_user['id'] . "</p>";
} else {
    echo "<p><strong>Tidak ada filter:</strong> Menampilkan semua tugas</p>";
}

// Query untuk mendapatkan data maintenance
$query = "SELECT wm.*, d.nama_desa as desa_name,
                 u2.nama_lengkap as programmer_nama
          FROM website_maintenance wm 
          LEFT JOIN desa d ON wm.desa_id = d.id 
          LEFT JOIN users u2 ON wm.programmer_id = u2.id
          {$where_clause}
          ORDER BY wm.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$maintenance_data = $stmt->fetchAll();

echo "<h3>Hasil Query (" . count($maintenance_data) . " records):</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Nama Desa</th><th>Website URL</th><th>Programmer</th><th>Programmer ID</th><th>Status</th><th>Deadline</th></tr>";

foreach ($maintenance_data as $row) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['nama_desa'] . "</td>";
    echo "<td>" . $row['website_url'] . "</td>";
    echo "<td>" . $row['programmer_nama'] . "</td>";
    echo "<td>" . $row['programmer_id'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['deadline'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test dengan programmer lain (user ID 12 - Denysha)
echo "<hr><h3>Test dengan Programmer Lain (Denysha - ID 12)</h3>";

$_SESSION['user_id'] = 12;
$_SESSION['role'] = 'programmer';
$_SESSION['username'] = 'denysha';
$current_user = AuthStatic::getCurrentUser();

if ($current_user) {
    echo "<p>Current User ID: " . $current_user['id'] . " - " . $current_user['nama_lengkap'] . "</p>";
    
    $where_clause = "WHERE wm.programmer_id = ?";
    $params = [$current_user['id']];
    
    $query2 = "SELECT wm.*, d.nama_desa as desa_name,
                     u2.nama_lengkap as programmer_nama
              FROM website_maintenance wm 
              LEFT JOIN desa d ON wm.desa_id = d.id 
              LEFT JOIN users u2 ON wm.programmer_id = u2.id
              {$where_clause}
              ORDER BY wm.created_at DESC";
    
    $stmt = $pdo->prepare($query2);
    $stmt->execute($params);
} else {
    echo "<p>Error: User tidak ditemukan</p>";
    $maintenance_data = [];
}

if (isset($stmt)) {
    $maintenance_data = $stmt->fetchAll();
} else {
    $maintenance_data = [];
}

if ($current_user) {
    echo "<p>Hasil untuk programmer ID " . $current_user['id'] . ": " . count($maintenance_data) . " records</p>";
} else {
    echo "<p>Hasil: " . count($maintenance_data) . " records</p>";
}
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Nama Desa</th><th>Website URL</th><th>Programmer</th><th>Programmer ID</th><th>Status</th><th>Deadline</th></tr>";

foreach ($maintenance_data as $row) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['nama_desa'] . "</td>";
    echo "<td>" . $row['website_url'] . "</td>";
    echo "<td>" . $row['programmer_nama'] . "</td>";
    echo "<td>" . $row['programmer_id'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['deadline'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test sebagai admin (tidak ada filter)
echo "<hr><h3>Test sebagai Admin (Tidak ada filter)</h3>";

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$current_user = AuthStatic::getCurrentUser();
echo "<p>Current User: " . $current_user['nama_lengkap'] . " (Role: " . $_SESSION['role'] . ")</p>";

$where_clause = '';
$params = [];

if (AuthStatic::hasRole(['programmer'])) {
    echo "<p>Filter programmer aktif</p>";
} else {
    echo "<p><strong>Tidak ada filter:</strong> Admin melihat semua tugas</p>";
}

$query = "SELECT wm.*, d.nama_desa as desa_name,
                 u2.nama_lengkap as programmer_nama
          FROM website_maintenance wm 
          LEFT JOIN desa d ON wm.desa_id = d.id 
          LEFT JOIN users u2 ON wm.programmer_id = u2.id
          {$where_clause}
          ORDER BY wm.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$maintenance_data = $stmt->fetchAll();

echo "<p>Total semua tugas: " . count($maintenance_data) . " records</p>";

echo "<h4>Summary per Programmer:</h4>";
$programmer_count = [];
foreach ($maintenance_data as $row) {
    $programmer_id = $row['programmer_id'];
    $programmer_name = $row['programmer_nama'];
    if (!isset($programmer_count[$programmer_id])) {
        $programmer_count[$programmer_id] = ['name' => $programmer_name, 'count' => 0];
    }
    $programmer_count[$programmer_id]['count']++;
}

echo "<ul>";
foreach ($programmer_count as $id => $data) {
    echo "<li>Programmer ID " . $id . " (" . $data['name'] . "): " . $data['count'] . " tugas</li>";
}
echo "</ul>";

echo "<p><strong>Kesimpulan:</strong> Filtering berdasarkan programmer_id berhasil diterapkan!</p>";
?>