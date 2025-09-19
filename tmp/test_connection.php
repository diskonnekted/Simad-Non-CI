<?php
echo "=== Test Koneksi Database SMD ===\n";
try {
    $pdo = new PDO("mysql:host=localhost;dbname=smd", "root", "");
    echo "SUCCESS: Connected to database smd\n";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . count($tables) . "\n";
    
    foreach($tables as $table) {
        echo "- $table\n";
    }
    
    // Test tabel yang dibutuhkan SMD
    $required_tables = ["users", "vendors", "products", "transactions", "categories", "promos"];
    echo "\nChecking required SMD tables:\n";
    
    foreach($required_tables as $table) {
        if (in_array($table, $tables)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "✓ $table: EXISTS ($count records)\n";
        } else {
            echo "✗ $table: MISSING\n";
        }
    }
    
} catch(Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
?>
