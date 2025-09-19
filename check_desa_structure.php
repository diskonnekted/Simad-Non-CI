<?php
// Script untuk memeriksa struktur tabel desa dan mencari tabel perangkat

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'simadorbitdev_simad';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Koneksi database berhasil\n";
    
    echo "\nStruktur tabel desa:\n";
    $result = $pdo->query('DESCRIBE desa');
    while($row = $result->fetch()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    
    echo "\nMencari tabel yang mengandung kata 'perangkat':\n";
    $result = $pdo->query('SHOW TABLES');
    while($row = $result->fetch()) {
        if (strpos(strtolower($row[0]), 'perangkat') !== false) {
            echo "- " . $row[0] . "\n";
        }
    }
    
    echo "\nMemeriksa apakah ada kolom yang berkaitan dengan perangkat di tabel desa:\n";
    $result = $pdo->query('DESCRIBE desa');
    while($row = $result->fetch()) {
        if (strpos(strtolower($row['Field']), 'kepala') !== false || 
            strpos(strtolower($row['Field']), 'sekdes') !== false ||
            strpos(strtolower($row['Field']), 'sekretaris') !== false) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
    
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>