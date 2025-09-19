<?php
require_once 'config/database.php';

/**
 * Script untuk memperbaiki tampilan kontak person di halaman desa.php dan desa-view.php
 * Berdasarkan analisis data Bandingan, kontak yang benar ada di field no_hp_kepala_desa
 */

echo "=== UPDATE TAMPILAN KONTAK PERSON ===\n";
echo "Tanggal: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Analisis field kontak yang tersedia di database
echo "1. ANALISIS FIELD KONTAK DI DATABASE\n";
echo "=====================================\n";

try {
    $db = getDatabase();
    
    // Cek struktur tabel desa
    $columns = $db->select("SHOW COLUMNS FROM desa");
    
    echo "Field yang tersedia di tabel desa:\n";
    $kontak_fields = [];
    foreach ($columns as $col) {
        if (stripos($col['Field'], 'hp') !== false || 
            stripos($col['Field'], 'telepon') !== false || 
            stripos($col['Field'], 'phone') !== false ||
            stripos($col['Field'], 'kontak') !== false) {
            $kontak_fields[] = $col['Field'];
            echo "- {$col['Field']} ({$col['Type']})\n";
        }
    }
    
    if (empty($kontak_fields)) {
        echo "Tidak ada field kontak ditemukan!\n";
    }
    
    echo "\n";
    
    // 2. Analisis data kontak yang ada
    echo "2. ANALISIS DATA KONTAK YANG ADA\n";
    echo "=================================\n";
    
    $sample_data = $db->select("
        SELECT id, nama_desa, kecamatan, 
               nama_kepala_desa, no_hp_kepala_desa,
               nama_sekdes, no_hp_sekdes
        FROM desa 
        WHERE status = 'aktif' 
        ORDER BY nama_desa 
        LIMIT 10
    ");
    
    echo "Sample data (10 desa pertama):\n";
    foreach ($sample_data as $desa) {
        echo "ID {$desa['id']}: {$desa['nama_desa']} - {$desa['kecamatan']}\n";
        echo "  Kepala Desa: {$desa['nama_kepala_desa']} | HP: {$desa['no_hp_kepala_desa']}\n";
        echo "  Sekdes: {$desa['nama_sekdes']} | HP: {$desa['no_hp_sekdes']}\n";
        echo "\n";
    }
    
    // 3. Statistik kelengkapan data kontak
    echo "3. STATISTIK KELENGKAPAN DATA KONTAK\n";
    echo "====================================\n";
    
    $stats = $db->select("
        SELECT 
            COUNT(*) as total_desa,
            COUNT(CASE WHEN no_hp_kepala_desa IS NOT NULL AND no_hp_kepala_desa != '' AND no_hp_kepala_desa != '0' THEN 1 END) as hp_kepala_ada,
            COUNT(CASE WHEN no_hp_sekdes IS NOT NULL AND no_hp_sekdes != '' AND no_hp_sekdes != '0' THEN 1 END) as hp_sekdes_ada,
            COUNT(CASE WHEN nama_kepala_desa IS NOT NULL AND nama_kepala_desa != '' THEN 1 END) as nama_kepala_ada,
            COUNT(CASE WHEN nama_sekdes IS NOT NULL AND nama_sekdes != '' THEN 1 END) as nama_sekdes_ada
        FROM desa 
        WHERE status = 'aktif'
    ")[0];
    
    echo "Total desa aktif: {$stats['total_desa']}\n";
    echo "HP Kepala Desa tersedia: {$stats['hp_kepala_ada']} (" . round($stats['hp_kepala_ada']/$stats['total_desa']*100, 1) . "%)\n";
    echo "HP Sekdes tersedia: {$stats['hp_sekdes_ada']} (" . round($stats['hp_sekdes_ada']/$stats['total_desa']*100, 1) . "%)\n";
    echo "Nama Kepala Desa tersedia: {$stats['nama_kepala_ada']} (" . round($stats['nama_kepala_ada']/$stats['total_desa']*100, 1) . "%)\n";
    echo "Nama Sekdes tersedia: {$stats['nama_sekdes_ada']} (" . round($stats['nama_sekdes_ada']/$stats['total_desa']*100, 1) . "%)\n";
    echo "\n";
    
    // 4. Rekomendasi perbaikan tampilan
    echo "4. REKOMENDASI PERBAIKAN TAMPILAN\n";
    echo "=================================\n";
    
    echo "Berdasarkan analisis data:\n";
    
    if ($stats['hp_kepala_ada'] > $stats['hp_sekdes_ada']) {
        echo "✓ Field no_hp_kepala_desa lebih lengkap ({$stats['hp_kepala_ada']} vs {$stats['hp_sekdes_ada']})\n";
        echo "✓ Gunakan no_hp_kepala_desa sebagai kontak utama\n";
        $primary_contact = 'no_hp_kepala_desa';
        $primary_name = 'nama_kepala_desa';
    } else {
        echo "✓ Field no_hp_sekdes lebih lengkap ({$stats['hp_sekdes_ada']} vs {$stats['hp_kepala_ada']})\n";
        echo "✓ Gunakan no_hp_sekdes sebagai kontak utama\n";
        $primary_contact = 'no_hp_sekdes';
        $primary_name = 'nama_sekdes';
    }
    
    echo "\n";
    
    // 5. Generate kode perbaikan untuk desa.php
    echo "5. KODE PERBAIKAN UNTUK DESA.PHP\n";
    echo "================================\n";
    
    echo "Ganti baris di desa.php (sekitar baris 242-243):\n";
    echo "DARI:\n";
    echo "<td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-900\"><?= htmlspecialchars(\$desa['nama_sekdes'] ?? \$desa['nama_kepala_desa'] ?? '-') ?></td>\n";
    echo "<td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-900\"><?= htmlspecialchars(\$desa['no_hp_sekdes'] ?? '-') ?></td>\n";
    echo "\n";
    echo "MENJADI:\n";
    echo "<td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-900\"><?= htmlspecialchars(\$desa['{$primary_name}'] ?? \$desa['nama_kepala_desa'] ?? '-') ?></td>\n";
    echo "<td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-900\"><?= htmlspecialchars(\$desa['{$primary_contact}'] ?? '-') ?></td>\n";
    echo "\n";
    
    // 6. Generate kode perbaikan untuk desa-view.php
    echo "6. KODE PERBAIKAN UNTUK DESA-VIEW.PHP\n";
    echo "====================================\n";
    
    echo "Ganti baris di desa-view.php (sekitar baris 400):\n";
    echo "DARI:\n";
    echo "<span class=\"text-gray-900\"><?= htmlspecialchars(\$desa['no_hp_kepala_desa'] ?? '') ?></span>\n";
    echo "\n";
    echo "MENJADI:\n";
    echo "<span class=\"text-gray-900\"><?= htmlspecialchars(\$desa['{$primary_contact}'] ?? \$desa['no_hp_kepala_desa'] ?? '') ?></span>\n";
    echo "\n";
    
    // 7. Desa dengan kontak kosong yang perlu diperbaiki
    echo "7. DESA DENGAN KONTAK KOSONG\n";
    echo "============================\n";
    
    $empty_contacts = $db->select("
        SELECT id, nama_desa, kecamatan, no_hp_kepala_desa, no_hp_sekdes
        FROM desa 
        WHERE status = 'aktif' 
        AND (no_hp_kepala_desa IS NULL OR no_hp_kepala_desa = '' OR no_hp_kepala_desa = '0')
        AND (no_hp_sekdes IS NULL OR no_hp_sekdes = '' OR no_hp_sekdes = '0')
        ORDER BY kecamatan, nama_desa
    ");
    
    if (empty($empty_contacts)) {
        echo "✓ Semua desa sudah memiliki kontak!\n";
    } else {
        echo "Desa yang perlu dilengkapi kontak (" . count($empty_contacts) . " desa):\n";
        $current_kec = '';
        foreach ($empty_contacts as $desa) {
            if ($current_kec != $desa['kecamatan']) {
                $current_kec = $desa['kecamatan'];
                echo "\nKecamatan {$current_kec}:\n";
            }
            echo "- ID {$desa['id']}: {$desa['nama_desa']}\n";
        }
    }
    
    echo "\n";
    echo "=== SELESAI ===\n";
    echo "Silakan terapkan perbaikan kode di atas untuk memperbaiki tampilan kontak.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>