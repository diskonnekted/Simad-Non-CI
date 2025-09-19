<?php
/**
 * Script Sinkronisasi Relasi Desa dengan Website Desa
 * 
 * Fungsi:
 * 1. Mencocokkan desa yang belum memiliki website
 * 2. Mencocokkan website yang belum memiliki relasi desa
 * 3. Membuat relasi otomatis berdasarkan nama desa
 * 4. Memberikan laporan hasil sinkronisasi
 * 
 * Created: 2025
 */

require_once 'config/database.php';

// Inisialisasi koneksi database
try {
    $db = getDatabase();
    echo "[INFO] Koneksi database berhasil\n";
} catch (Exception $e) {
    die("[ERROR] Koneksi database gagal: " . $e->getMessage() . "\n");
}

echo "\n=== SCRIPT SINKRONISASI RELASI DESA DENGAN WEBSITE DESA ===\n";
echo "Waktu mulai: " . date('Y-m-d H:i:s') . "\n\n";

// Fungsi untuk membersihkan nama desa untuk pencocokan
function cleanDesaName($name) {
    // Hapus kata-kata umum dan normalisasi
    $name = strtolower(trim($name));
    $name = str_replace(['desa ', ' desa', 'kelurahan ', ' kelurahan'], '', $name);
    $name = preg_replace('/[^a-z0-9]/', '', $name);
    return $name;
}

// Fungsi untuk ekstrak nama desa dari URL
function extractDesaFromUrl($url) {
    // Ekstrak dari subdomain atau path
    $parsed = parse_url($url);
    $host = $parsed['host'] ?? '';
    
    // Coba dari subdomain
    if (preg_match('/^([^.]+)\.(.*\.)?desa\.id$/', $host, $matches)) {
        return cleanDesaName($matches[1]);
    }
    
    // Coba dari subdomain dengan format lain
    if (preg_match('/^([^.]+)-([^.]+)\.(.*\.)?desa\.id$/', $host, $matches)) {
        return cleanDesaName($matches[1]);
    }
    
    // Coba dari path
    $path = $parsed['path'] ?? '';
    if (preg_match('/\/([^\/-]+)/', $path, $matches)) {
        return cleanDesaName($matches[1]);
    }
    
    return cleanDesaName(explode('.', $host)[0]);
}

// 1. Analisis kondisi saat ini
echo "[STEP 1] Menganalisis kondisi database...\n";

$stats = [];

// Total desa aktif
$result = $db->select("SELECT COUNT(*) as count FROM desa WHERE status = 'aktif'");
$stats['total_desa'] = $result[0]['count'];

// Total website
$result = $db->select("SELECT COUNT(*) as count FROM website_desa");
$stats['total_website'] = $result[0]['count'];

// Website dengan relasi desa
$result = $db->select("SELECT COUNT(*) as count FROM website_desa WHERE desa_id IS NOT NULL");
$stats['website_with_desa'] = $result[0]['count'];

// Desa tanpa website
$result = $db->select("
    SELECT COUNT(*) as count 
    FROM desa d 
    LEFT JOIN website_desa wd ON d.id = wd.desa_id 
    WHERE d.status = 'aktif' AND wd.id IS NULL
");
$stats['desa_without_website'] = $result[0]['count'];

// Website tanpa relasi desa
$result = $db->select("SELECT COUNT(*) as count FROM website_desa WHERE desa_id IS NULL");
$stats['website_without_desa'] = $result[0]['count'];

echo "Statistik saat ini:\n";
echo "- Total desa aktif: {$stats['total_desa']}\n";
echo "- Total website: {$stats['total_website']}\n";
echo "- Website dengan relasi desa: {$stats['website_with_desa']}\n";
echo "- Desa tanpa website: {$stats['desa_without_website']}\n";
echo "- Website tanpa relasi desa: {$stats['website_without_desa']}\n\n";

// 2. Sinkronisasi website yang belum memiliki relasi desa
echo "[STEP 2] Sinkronisasi website tanpa relasi desa...\n";

$websites_without_desa = $db->select("
    SELECT id, website_url 
    FROM website_desa 
    WHERE desa_id IS NULL
    ORDER BY id
");

$matched_websites = 0;
$unmatched_websites = [];

foreach ($websites_without_desa as $website) {
    $website_name = extractDesaFromUrl($website['website_url']);
    echo "Mencari desa untuk website: {$website['website_url']} (ekstrak: $website_name)\n";
    
    // Cari desa yang cocok
    $matching_desa = $db->select("
        SELECT id, nama_desa, kecamatan, kabupaten 
        FROM desa 
        WHERE status = 'aktif' 
        AND id NOT IN (SELECT desa_id FROM website_desa WHERE desa_id IS NOT NULL)
        ORDER BY 
            CASE 
                WHEN LOWER(REPLACE(REPLACE(nama_desa, ' ', ''), '-', '')) = ? THEN 1
                WHEN LOWER(REPLACE(REPLACE(nama_desa, ' ', ''), '-', '')) LIKE ? THEN 2
                WHEN LOWER(nama_desa) LIKE ? THEN 3
                ELSE 4
            END
        LIMIT 1
    ", [
        $website_name,
        "%$website_name%",
        "%$website_name%"
    ]);
    
    if (!empty($matching_desa)) {
        $desa = $matching_desa[0];
        
        // Update relasi
        $db->execute(
            "UPDATE website_desa SET desa_id = ?, updated_at = NOW() WHERE id = ?",
            [$desa['id'], $website['id']]
        );
        
        echo "  ✓ Berhasil menghubungkan dengan desa: {$desa['nama_desa']}, {$desa['kecamatan']}, {$desa['kabupaten']}\n";
        $matched_websites++;
    } else {
        echo "  ✗ Tidak ditemukan desa yang cocok\n";
        $unmatched_websites[] = $website;
    }
}

echo "\nHasil sinkronisasi website:\n";
echo "- Website berhasil dihubungkan: $matched_websites\n";
echo "- Website tidak dapat dihubungkan: " . count($unmatched_websites) . "\n\n";

// 3. Buat entri website untuk desa yang belum memiliki website
echo "[STEP 3] Membuat entri website untuk desa tanpa website...\n";

$desa_without_website = $db->select("
    SELECT d.id, d.nama_desa, d.kecamatan, d.kabupaten 
    FROM desa d 
    LEFT JOIN website_desa wd ON d.id = wd.desa_id 
    WHERE d.status = 'aktif' AND wd.id IS NULL
    ORDER BY d.nama_desa
");

$created_websites = 0;

foreach ($desa_without_website as $desa) {
    // Generate URL website berdasarkan nama desa
    $clean_name = strtolower(str_replace([' ', '_'], '-', $desa['nama_desa']));
    $website_url = "https://{$clean_name}.desa.id/";
    
    // Cek apakah URL sudah ada
    $existing = $db->select(
        "SELECT id FROM website_desa WHERE website_url = ?",
        [$website_url]
    );
    
    if (empty($existing)) {
        // Buat entri website baru
        $db->execute("
            INSERT INTO website_desa (desa_id, website_url, has_database, news_active, developer_type, opendata_sync, keterangan, created_at, updated_at)
            VALUES (?, ?, 'tidak_ada', 'tidak_aktif', 'bukan_clasnet', 'tidak_sinkron', 'Website dibuat otomatis oleh sistem sinkronisasi', NOW(), NOW())
        ", [
            $desa['id'],
            $website_url
        ]);
        
        echo "  ✓ Dibuat website untuk desa: {$desa['nama_desa']} -> $website_url\n";
        $created_websites++;
    } else {
        echo "  ⚠ URL sudah ada untuk desa: {$desa['nama_desa']} -> $website_url\n";
    }
}

echo "\nHasil pembuatan website:\n";
echo "- Website baru dibuat: $created_websites\n\n";

// 4. Statistik akhir
echo "[STEP 4] Menganalisis hasil sinkronisasi...\n";

// Hitung ulang statistik
$result = $db->select("SELECT COUNT(*) as count FROM website_desa WHERE desa_id IS NOT NULL");
$new_website_with_desa = $result[0]['count'];

$result = $db->select("
    SELECT COUNT(*) as count 
    FROM desa d 
    LEFT JOIN website_desa wd ON d.id = wd.desa_id 
    WHERE d.status = 'aktif' AND wd.id IS NULL
");
$new_desa_without_website = $result[0]['count'];

$result = $db->select("SELECT COUNT(*) as count FROM website_desa WHERE desa_id IS NULL");
$new_website_without_desa = $result[0]['count'];

echo "\n=== LAPORAN HASIL SINKRONISASI ===\n";
echo "Waktu selesai: " . date('Y-m-d H:i:s') . "\n\n";

echo "SEBELUM SINKRONISASI:\n";
echo "- Website dengan relasi desa: {$stats['website_with_desa']}\n";
echo "- Desa tanpa website: {$stats['desa_without_website']}\n";
echo "- Website tanpa relasi desa: {$stats['website_without_desa']}\n\n";

echo "SETELAH SINKRONISASI:\n";
echo "- Website dengan relasi desa: $new_website_with_desa\n";
echo "- Desa tanpa website: $new_desa_without_website\n";
echo "- Website tanpa relasi desa: $new_website_without_desa\n\n";

echo "PERUBAHAN:\n";
echo "- Website berhasil dihubungkan: $matched_websites\n";
echo "- Website baru dibuat: $created_websites\n";
echo "- Peningkatan sinkronisasi: " . ($new_website_with_desa - $stats['website_with_desa']) . "\n\n";

// 5. Daftar yang masih perlu perhatian manual
if (!empty($unmatched_websites)) {
    echo "WEBSITE YANG PERLU PERHATIAN MANUAL:\n";
    foreach ($unmatched_websites as $website) {
        echo "- ID {$website['id']}: {$website['website_url']}\n";
    }
    echo "\n";
}

if ($new_desa_without_website > 0) {
    echo "DESA YANG MASIH BELUM MEMILIKI WEBSITE:\n";
    $remaining_desa = $db->select("
        SELECT d.nama_desa, d.kecamatan, d.kabupaten 
        FROM desa d 
        LEFT JOIN website_desa wd ON d.id = wd.desa_id 
        WHERE d.status = 'aktif' AND wd.id IS NULL
        ORDER BY d.nama_desa
        LIMIT 10
    ");
    
    foreach ($remaining_desa as $desa) {
        echo "- {$desa['nama_desa']}, {$desa['kecamatan']}, {$desa['kabupaten']}\n";
    }
    
    if ($new_desa_without_website > 10) {
        echo "- ... dan " . ($new_desa_without_website - 10) . " desa lainnya\n";
    }
    echo "\n";
}

echo "=== SINKRONISASI SELESAI ===\n";
echo "\nRekomendasi lanjutan:\n";
echo "1. Verifikasi manual website yang tidak dapat dihubungkan otomatis\n";
echo "2. Periksa kesesuaian URL website dengan nama desa\n";
echo "3. Update status database dan news_active untuk website yang sudah terverifikasi\n";
echo "4. Lakukan sinkronisasi berkala untuk data baru\n";

?>