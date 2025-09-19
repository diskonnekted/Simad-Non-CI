<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    
    echo "=== STRUKTUR TABEL PENERIMAAN_DETAIL ===\n";
    $structure = $db->select('DESCRIBE penerimaan_detail');
    foreach($structure as $column) {
        echo "- {$column['Field']}: {$column['Type']} ({$column['Null']}, {$column['Key']})\n";
    }
    
    echo "\n=== STRUKTUR TABEL PEMBELIAN_DETAIL ===\n";
    $structure2 = $db->select('DESCRIBE pembelian_detail');
    foreach($structure2 as $column) {
        echo "- {$column['Field']}: {$column['Type']} ({$column['Null']}, {$column['Key']})\n";
    }
    
    echo "\n=== STRUKTUR TABEL PRODUK ===\n";
    $structure3 = $db->select('DESCRIBE produk');
    foreach($structure3 as $column) {
        echo "- {$column['Field']}: {$column['Type']} ({$column['Null']}, {$column['Key']})\n";
    }
    
    echo "\n=== SAMPLE DATA PENERIMAAN_DETAIL ===\n";
    $sample = $db->select('SELECT * FROM penerimaan_detail LIMIT 3');
    foreach($sample as $row) {
        echo "ID: {$row['id']}\n";
        foreach($row as $key => $value) {
            echo "  $key: $value\n";
        }
        echo "\n";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>