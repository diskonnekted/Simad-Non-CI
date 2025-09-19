<?php
// Script untuk mengupdate kontak person di data desa secara bulk

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

echo "\n=== UPDATE KONTAK PERSON DARI CSV ===\n";

// Baca data dari CSV
$csvFile = 'data-desa.csv';
if (!file_exists($csvFile)) {
    echo "File $csvFile tidak ditemukan\n";
    exit;
}

$kontakData = [];
if (($handle = fopen($csvFile, "r")) !== FALSE) {
    $header = fgetcsv($handle, 1000, ",");
    
    // Tampilkan header untuk debugging
    echo "Header CSV: " . implode(', ', $header) . "\n\n";
    
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (count($data) >= 11) {
            $namaDesa = trim($data[1]);
            $kecamatan = trim($data[2]);
            $namaLengkap = trim($data[3]);
            $telepon = trim($data[6]);
            $jabatan = trim($data[10]);
            
            if (!empty($namaDesa) && !empty($namaLengkap) && !empty($jabatan)) {
                $key = strtolower($namaDesa);
                
                if (!isset($kontakData[$key])) {
                    $kontakData[$key] = [
                        'nama_desa' => $namaDesa,
                        'kecamatan' => $kecamatan,
                        'kepala_desa' => '',
                        'sekretaris' => '',
                        'no_hp_kepala' => '',
                        'no_hp_sekdes' => ''
                    ];
                }
                
                // Identifikasi jabatan
                if (stripos($jabatan, 'KEPALA DESA') !== false || stripos($jabatan, 'KADES') !== false) {
                    $kontakData[$key]['kepala_desa'] = $namaLengkap;
                    if (!empty($telepon)) {
                        $kontakData[$key]['no_hp_kepala'] = $telepon;
                    }
                } elseif (stripos($jabatan, 'SEKRETARIS DESA') !== false || stripos($jabatan, 'SEKDES') !== false) {
                    $kontakData[$key]['sekretaris'] = $namaLengkap;
                    if (!empty($telepon)) {
                        $kontakData[$key]['no_hp_sekdes'] = $telepon;
                    }
                }
            }
        }
    }
    fclose($handle);
}

echo "Data kontak dari CSV: " . count($kontakData) . " desa\n";

// Tampilkan sample data
echo "\n=== SAMPLE DATA KONTAK ===\n";
$counter = 0;
foreach ($kontakData as $key => $data) {
    if ($counter >= 5) break;
    echo "Desa: {$data['nama_desa']} (Kecamatan: {$data['kecamatan']})\n";
    echo "  Kepala Desa: {$data['kepala_desa']}\n";
    echo "  Sekretaris: {$data['sekretaris']}\n";
    echo "  No HP Kepala: {$data['no_hp_kepala']}\n";
    echo "  No HP Sekdes: {$data['no_hp_sekdes']}\n\n";
    $counter++;
}

// Update data ke database
echo "=== PROSES UPDATE KE DATABASE ===\n";

$updatedKepala = 0;
$updatedSekretaris = 0;
$updatedHpKepala = 0;
$updatedHpSekdes = 0;
$notFound = 0;

foreach ($kontakData as $key => $data) {
    // Cari desa di database
    $stmt = $pdo->prepare("SELECT id, nama_desa, kecamatan FROM desa WHERE LOWER(nama_desa) = ? LIMIT 1");
    $stmt->execute([strtolower($data['nama_desa'])]);
    $desa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($desa) {
        $updates = [];
        $params = [];
        
        // Update kepala desa jika ada dan belum ada di database
        if (!empty($data['kepala_desa'])) {
            $stmt = $pdo->prepare("SELECT nama_kepala_desa FROM desa WHERE id = ?");
            $stmt->execute([$desa['id']]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (empty($current['nama_kepala_desa'])) {
                $updates[] = "nama_kepala_desa = ?";
                $params[] = $data['kepala_desa'];
                $updatedKepala++;
            }
        }
        
        // Update sekretaris desa jika ada dan belum ada di database
        if (!empty($data['sekretaris'])) {
            $stmt = $pdo->prepare("SELECT nama_sekdes FROM desa WHERE id = ?");
            $stmt->execute([$desa['id']]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (empty($current['nama_sekdes'])) {
                $updates[] = "nama_sekdes = ?";
                $params[] = $data['sekretaris'];
                $updatedSekretaris++;
            }
        }
        
        // Update no HP kepala desa jika ada dan belum ada di database
        if (!empty($data['no_hp_kepala'])) {
            $stmt = $pdo->prepare("SELECT no_hp_kepala_desa FROM desa WHERE id = ?");
            $stmt->execute([$desa['id']]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (empty($current['no_hp_kepala_desa'])) {
                $updates[] = "no_hp_kepala_desa = ?";
                $params[] = $data['no_hp_kepala'];
                $updatedHpKepala++;
            }
        }
        
        // Update no HP sekdes jika ada dan belum ada di database
        if (!empty($data['no_hp_sekdes'])) {
            $stmt = $pdo->prepare("SELECT no_hp_sekdes FROM desa WHERE id = ?");
            $stmt->execute([$desa['id']]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (empty($current['no_hp_sekdes'])) {
                $updates[] = "no_hp_sekdes = ?";
                $params[] = $data['no_hp_sekdes'];
                $updatedHpSekdes++;
            }
        }
        
        // Lakukan update jika ada perubahan
        if (!empty($updates)) {
            $sql = "UPDATE desa SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $desa['id'];
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                echo "✓ Updated: {$data['nama_desa']} (" . implode(', ', $updates) . ")\n";
            } else {
                echo "✗ Failed: {$data['nama_desa']}\n";
            }
        }
    } else {
        $notFound++;
        echo "? Not found: {$data['nama_desa']}\n";
    }
}

echo "\n=== RINGKASAN UPDATE ===\n";
echo "Kepala desa diupdate: $updatedKepala\n";
echo "Sekretaris desa diupdate: $updatedSekretaris\n";
echo "No HP kepala desa diupdate: $updatedHpKepala\n";
echo "No HP sekdes diupdate: $updatedHpSekdes\n";
echo "Desa tidak ditemukan: $notFound\n";

// Statistik akhir
echo "\n=== STATISTIK KONTAK PERSON ===\n";

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_desa,
        SUM(CASE WHEN nama_kepala_desa != '' AND nama_kepala_desa IS NOT NULL THEN 1 ELSE 0 END) as ada_kepala,
        SUM(CASE WHEN nama_sekdes != '' AND nama_sekdes IS NOT NULL THEN 1 ELSE 0 END) as ada_sekretaris,
        SUM(CASE WHEN no_hp_kepala_desa != '' AND no_hp_kepala_desa IS NOT NULL THEN 1 ELSE 0 END) as ada_hp_kepala,
        SUM(CASE WHEN no_hp_sekdes != '' AND no_hp_sekdes IS NOT NULL THEN 1 ELSE 0 END) as ada_hp_sekdes
    FROM desa 
    WHERE kecamatan != '' AND kecamatan IS NOT NULL
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total desa: {$stats['total_desa']}\n";
echo "Desa dengan kepala desa: {$stats['ada_kepala']}\n";
echo "Desa dengan sekretaris: {$stats['ada_sekretaris']}\n";
echo "Desa dengan no HP kepala: {$stats['ada_hp_kepala']}\n";
echo "Desa dengan no HP sekdes: {$stats['ada_hp_sekdes']}\n";

echo "\nSelesai!\n";
?>