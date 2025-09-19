<?php
require_once 'config/database.php';

echo "=== STRUKTUR TABEL PEMBELIAN ===\n\n";

$db = getDatabase();

try {
    $result = $db->select('DESCRIBE pembelian');
    
    echo "Field\t\t\tType\t\t\tNull\tKey\tDefault\tExtra\n";
    echo "=================================================================\n";
    
    foreach($result as $row) {
        echo sprintf("%-20s\t%-20s\t%s\t%s\t%s\t%s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'], 
            $row['Default'] ?? 'NULL', 
            $row['Extra']
        );
    }
    
    echo "\n=== CEK BANK YANG ADA ===\n";
    $banks = $db->select('SELECT id, nama_bank FROM bank LIMIT 5');
    
    if (empty($banks)) {
        echo "❌ Tidak ada data bank\n";
        echo "\nMembuat bank default...\n";
        $db->execute("
            INSERT INTO bank (nama_bank, nomor_rekening, atas_nama, saldo, status)
            VALUES ('Bank Default', '1234567890', 'PT SMD', 0, 'aktif')
        ");
        $bank_id = $db->lastInsertId();
        echo "✅ Bank default berhasil dibuat dengan ID: {$bank_id}\n";
    } else {
        echo "✅ Bank yang tersedia:\n";
        foreach ($banks as $bank) {
            echo "   ID: {$bank['id']} - {$bank['nama_bank']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>