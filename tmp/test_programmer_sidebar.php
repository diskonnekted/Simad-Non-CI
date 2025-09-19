<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

// Simulasi login sebagai programmer
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'programmer';
$_SESSION['username'] = 'programmer_test';

// Ambil data user untuk simulasi
$user = [
    'nama_lengkap' => 'Test Programmer',
    'email' => 'programmer@test.com',
    'role' => 'programmer',
    'foto_profil' => ''
];

echo "<!DOCTYPE html>";
echo "<html lang='id'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Test Sidebar Programmer</title>";
echo "<script src='https://cdn.tailwindcss.com'></script>";
echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>";
echo "</head>";
echo "<body class='bg-gray-100'>";

echo "<div class='container mx-auto p-6'>";
echo "<h1 class='text-2xl font-bold mb-4'>Test Sidebar Menu Programmer</h1>";

echo "<div class='bg-white p-4 rounded-lg shadow mb-6'>";
echo "<h2 class='text-lg font-semibold mb-2'>Status Login:</h2>";
echo "<p><strong>Role:</strong> " . $_SESSION['role'] . "</p>";
echo "<p><strong>Username:</strong> " . $_SESSION['username'] . "</p>";
echo "</div>";

echo "<div class='bg-white p-4 rounded-lg shadow'>";
echo "<h2 class='text-lg font-semibold mb-4'>Sidebar Preview:</h2>";

// Include bagian sidebar dari header.php
ob_start();
include 'layouts/header.php';
$header_content = ob_get_clean();

// Extract hanya bagian sidebar
preg_match('/<aside id="sidebar".*?<\/aside>/s', $header_content, $matches);
if (!empty($matches[0])) {
    echo $matches[0];
} else {
    echo "<p class='text-red-500'>Sidebar tidak ditemukan dalam header.php</p>";
}

echo "</div>";

echo "<div class='mt-6'>";
echo "<a href='dashboard-programmer.php' class='bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600'>";
echo "Test Dashboard Programmer";
echo "</a>";
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
?>