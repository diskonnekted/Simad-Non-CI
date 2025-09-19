<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    echo "=== STRUKTUR TABEL PERANGKAT_DESA ===\n\n";
    
    // Cek struktur tabel
    $columns = $db->select('DESCRIBE perangkat_desa');
    
    echo "Kolom yang tersedia:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n=== SAMPLE DATA ===\n";
    
    // Ambil sample data
    $sample = $db->select('SELECT * FROM perangkat_desa LIMIT 5');
    
    if (!empty($sample)) {
        foreach ($sample as $row) {
            echo "ID: {$row['id']}\n";
            foreach ($row as $key => $value) {
                if ($key !== 'id') {
                    echo "  {$key}: {$value}\n";
                }
            }
            echo "\n";
        }
    } else {
        echo "Tidak ada data di tabel perangkat_desa\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>