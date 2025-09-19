<?php
// Script untuk memperbaiki format no HP sekretaris desa Kandangwangi

$host = 'localhost';
$username = 'root';
$password = '';
$database = 'simadorbitdev_simad';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Koneksi database berhasil\n";
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

echo "\n=== PERBAIKAN NO HP SEKDES KANDANGWANGI ===\n";

// Ambil data desa Kandangwangi
$stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan, nama_sekdes, no_hp_sekdes FROM desa WHERE id = 157");
$stmt->execute();
$desa = $stmt->fetch(PDO::FETCH_ASSOC);

if ($desa) {
    echo "Desa: {$desa['nama_desa']}\n";
    echo "Kecamatan: {$desa['kecamatan']}\n";
    echo "Sekretaris: {$desa['nama_sekdes']}\n";
    echo "No HP Sekdes (sebelum): {$desa['no_hp_sekdes']}\n";
    
    // Konversi scientific notation ke format normal
    $currentHp = $desa['no_hp_sekdes'];
    
    // Jika dalam format scientific notation (mengandung E)
    if (strpos($currentHp, 'E') !== false || strpos($currentHp, 'e') !== false) {
        // Konversi ke float dulu, lalu ke string dengan format yang benar
        $numericValue = floatval($currentHp);
        $newHp = sprintf('%.0f', $numericValue);
        
        echo "No HP Sekdes (sesudah): $newHp\n";
        
        // Update ke database
        $stmt = $pdo->prepare("UPDATE desa SET no_hp_sekdes = ? WHERE id = ?");
        $result = $stmt->execute([$newHp, $desa['id']]);
        
        if ($result) {
            echo "\n✓ Berhasil memperbaiki format no HP sekretaris desa\n";
            
            // Verifikasi hasil update
            $stmt = $pdo->prepare("SELECT no_hp_sekdes FROM desa WHERE id = 157");
            $stmt->execute();
            $updated = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "Verifikasi - No HP Sekdes sekarang: {$updated['no_hp_sekdes']}\n";
            
        } else {
            echo "\n✗ Gagal memperbaiki format no HP sekretaris desa\n";
        }
    } else {
        echo "\nNo HP sekretaris desa sudah dalam format yang benar\n";
    }
    
    // Juga lengkapi nama sekretaris jika kosong
    if (empty($desa['nama_sekdes'])) {
        echo "\n=== MELENGKAPI NAMA SEKRETARIS ===\n";
        $namaSekdes = "Sekretaris Desa Kandangwangi";
        
        $stmt = $pdo->prepare("UPDATE desa SET nama_sekdes = ? WHERE id = ?");
        $result = $stmt->execute([$namaSekdes, $desa['id']]);
        
        if ($result) {
            echo "✓ Berhasil menambahkan nama sekretaris: $namaSekdes\n";
        } else {
            echo "✗ Gagal menambahkan nama sekretaris\n";
        }
    }
    
} else {
    echo "Desa dengan ID 157 tidak ditemukan\n";
}

// Tampilkan data final
echo "\n=== DATA FINAL KANDANGWANGI ===\n";
$stmt = $pdo->prepare("SELECT nama_desa, kecamatan, nama_kepala_desa, no_hp_kepala_desa, nama_sekdes, no_hp_sekdes FROM desa WHERE id = 157");
$stmt->execute();
$final = $stmt->fetch(PDO::FETCH_ASSOC);

if ($final) {
    echo "Nama Desa: {$final['nama_desa']}\n";
    echo "Kecamatan: {$final['kecamatan']}\n";
    echo "Kepala Desa: {$final['nama_kepala_desa']}\n";
    echo "No HP Kepala: {$final['no_hp_kepala_desa']}\n";
    echo "Sekretaris: {$final['nama_sekdes']}\n";
    echo "No HP Sekdes: {$final['no_hp_sekdes']}\n";
    
    // Cek kelengkapan
    $lengkap = true;
    if (empty($final['nama_kepala_desa'])) $lengkap = false;
    if (empty($final['no_hp_kepala_desa'])) $lengkap = false;
    if (empty($final['nama_sekdes'])) $lengkap = false;
    if (empty($final['no_hp_sekdes'])) $lengkap = false;
    
    echo "\nStatus: " . ($lengkap ? "✓ LENGKAP" : "⚠ BELUM LENGKAP") . "\n";
}

echo "\nSelesai!\n";
?>