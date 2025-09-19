<?php
require_once 'config/database.php';

try {
    $db = getDatabase();
    
    echo "=== DAFTAR TABEL YANG ADA ===\n";
    $tables = $db->select('SHOW TABLES');
    
    $existing_tables = [];
    foreach($tables as $table) {
        $table_name = array_values($table)[0];
        $existing_tables[] = $table_name;
        echo "- $table_name\n";
    }
    
    echo "\n=== MEMERIKSA TABEL KATEGORI ===\n";
    
    if (!in_array('kategori', $existing_tables)) {
        echo "Tabel kategori TIDAK DITEMUKAN. Membuat tabel kategori...\n";
        
        $create_kategori = "
            CREATE TABLE kategori (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                deskripsi TEXT,
                status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        
        $db->execute($create_kategori);
        echo "✓ Tabel kategori berhasil dibuat\n";
        
        // Insert data kategori default
        $default_categories = [
            ['Elektronik', 'Produk elektronik dan gadget'],
            ['Pakaian', 'Pakaian dan fashion'],
            ['Makanan', 'Makanan dan minuman'],
            ['Kesehatan', 'Produk kesehatan dan kecantikan'],
            ['Olahraga', 'Peralatan olahraga dan fitness']
        ];
        
        foreach($default_categories as $cat) {
            $db->insert('kategori', [
                'nama' => $cat[0],
                'deskripsi' => $cat[1]
            ]);
        }
        
        echo "✓ Data kategori default berhasil ditambahkan\n";
        
    } else {
        echo "✓ Tabel kategori sudah ada\n";
        
        // Tampilkan data kategori
        $categories = $db->select('kategori');
        echo "\nData kategori yang ada:\n";
        foreach($categories as $cat) {
            echo "- ID: {$cat['id']}, Nama: {$cat['nama']}, Status: {$cat['status']}\n";
        }
    }
    
    echo "\n=== MEMERIKSA TABEL LAINNYA ===\n";
    
    $required_tables = ['produk', 'transaksi', 'detail_transaksi', 'supplier', 'purchase_order', 'penerimaan_barang'];
    
    foreach($required_tables as $table) {
        if (in_array($table, $existing_tables)) {
            echo "✓ Tabel $table ada\n";
        } else {
            echo "✗ Tabel $table TIDAK ADA\n";
        }
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>