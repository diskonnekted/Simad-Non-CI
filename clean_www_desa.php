<?php
// Script untuk membersihkan nama desa yang mengandung 'www'

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

echo "\n=== PEMBERSIHAN NAMA DESA DENGAN WWW ===\n";

// Cari desa yang mengandung 'www'
$stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan FROM desa WHERE nama_desa LIKE '%www%' OR nama_desa LIKE '%Www%' ORDER BY nama_desa");
$stmt->execute();
$desaWww = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total desa dengan 'www' dalam nama: " . count($desaWww) . "\n\n";

if (count($desaWww) > 0) {
    echo "Daftar desa yang akan dibersihkan:\n";
    foreach ($desaWww as $desa) {
        echo "- {$desa['nama_desa']} (Kecamatan: {$desa['kecamatan']})\n";
    }
    
    echo "\n=== PROSES PEMBERSIHAN ===\n";
    
    $updated = 0;
    $failed = 0;
    
    foreach ($desaWww as $desa) {
        $namaLama = $desa['nama_desa'];
        
        // Bersihkan nama desa
        $namaBaru = $namaLama;
        
        // Hapus 'Www.' dari awal nama
        if (stripos($namaBaru, 'Www.') === 0) {
            $namaBaru = substr($namaBaru, 4); // Hapus 'Www.'
        }
        
        // Hapus 'www.' dari awal nama (lowercase)
        if (stripos($namaBaru, 'www.') === 0) {
            $namaBaru = substr($namaBaru, 4); // Hapus 'www.'
        }
        
        // Capitalize first letter
        $namaBaru = ucfirst(strtolower($namaBaru));
        
        // Jika nama berubah, update ke database
        if ($namaBaru !== $namaLama) {
            $stmt = $pdo->prepare("UPDATE desa SET nama_desa = ? WHERE id = ?");
            $result = $stmt->execute([$namaBaru, $desa['id']]);
            
            if ($result && $stmt->rowCount() > 0) {
                echo "✓ Updated: '{$namaLama}' -> '{$namaBaru}'\n";
                $updated++;
            } else {
                echo "✗ Failed: '{$namaLama}'\n";
                $failed++;
            }
        } else {
            echo "- No change needed: '{$namaLama}'\n";
        }
    }
    
    echo "\n=== RINGKASAN PEMBERSIHAN ===\n";
    echo "Total desa berhasil dibersihkan: $updated\n";
    echo "Total desa gagal dibersihkan: $failed\n";
    echo "Total desa tidak perlu diubah: " . (count($desaWww) - $updated - $failed) . "\n";
    
} else {
    echo "Tidak ada desa dengan 'www' dalam nama.\n";
}

// Verifikasi hasil
echo "\n=== VERIFIKASI HASIL ===\n";
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM desa WHERE nama_desa LIKE '%www%' OR nama_desa LIKE '%Www%'");
$stmt->execute();
$remaining = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Desa yang masih mengandung 'www': {$remaining['total']}\n";

if ($remaining['total'] > 0) {
    $stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan FROM desa WHERE nama_desa LIKE '%www%' OR nama_desa LIKE '%Www%' ORDER BY nama_desa");
    $stmt->execute();
    $stillWww = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nDesa yang masih mengandung 'www':\n";
    foreach ($stillWww as $desa) {
        echo "- {$desa['nama_desa']} (Kecamatan: {$desa['kecamatan']})\n";
    }
}

echo "\nSelesai!\n";
?>