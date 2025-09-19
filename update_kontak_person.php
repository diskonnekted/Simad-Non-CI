<?php
require_once 'config/database.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Memulai update kontak person untuk semua desa...\n";
    
    // Update nama kepala desa untuk semua desa
    $sql_kades = "
        UPDATE desa d 
        SET nama_kepala_desa = (
            SELECT nama_lengkap 
            FROM perangkat_desa 
            WHERE desa_id = d.id 
            AND jabatan = 'Kepala Desa' 
            LIMIT 1
        )
        WHERE nama_kepala_desa IS NULL OR nama_kepala_desa = ''
    ";
    
    $stmt = $pdo->prepare($sql_kades);
    $stmt->execute();
    $updated_kades = $stmt->rowCount();
    echo "Updated kepala desa: $updated_kades desa\n";
    
    // Update sekretaris desa untuk semua desa
    $sql_sekdes = "
        UPDATE desa d 
        SET nama_sekdes = (
            SELECT nama_lengkap 
            FROM perangkat_desa 
            WHERE desa_id = d.id 
            AND (jabatan LIKE '%Sekretaris%' OR jabatan LIKE '%Sekertaris%') 
            LIMIT 1
        ),
        no_hp_sekdes = (
            SELECT no_telepon 
            FROM perangkat_desa 
            WHERE desa_id = d.id 
            AND (jabatan LIKE '%Sekretaris%' OR jabatan LIKE '%Sekertaris%') 
            LIMIT 1
        )
        WHERE (nama_sekdes IS NULL OR nama_sekdes = '') 
        AND EXISTS (
            SELECT 1 FROM perangkat_desa 
            WHERE desa_id = d.id 
            AND (jabatan LIKE '%Sekretaris%' OR jabatan LIKE '%Sekertaris%')
        )
    ";
    
    $stmt = $pdo->prepare($sql_sekdes);
    $stmt->execute();
    $updated_sekdes = $stmt->rowCount();
    echo "Updated sekretaris desa: $updated_sekdes desa\n";
    
    // Untuk desa yang tidak memiliki sekretaris, gunakan kepala desa sebagai kontak person
    $sql_fallback = "
        UPDATE desa d 
        SET nama_sekdes = nama_kepala_desa,
        no_hp_sekdes = (
            SELECT no_telepon 
            FROM perangkat_desa 
            WHERE desa_id = d.id 
            AND jabatan = 'Kepala Desa' 
            LIMIT 1
        )
        WHERE (nama_sekdes IS NULL OR nama_sekdes = '') 
        AND nama_kepala_desa IS NOT NULL
        AND NOT EXISTS (
            SELECT 1 FROM perangkat_desa 
            WHERE desa_id = d.id 
            AND (jabatan LIKE '%Sekretaris%' OR jabatan LIKE '%Sekertaris%')
        )
    ";
    
    $stmt = $pdo->prepare($sql_fallback);
    $stmt->execute();
    $updated_fallback = $stmt->rowCount();
    echo "Updated kontak person dengan kepala desa: $updated_fallback desa\n";
    
    // Tampilkan statistik
    $sql_stats = "SELECT 
        COUNT(*) as total_desa,
        COUNT(nama_kepala_desa) as desa_dengan_kades,
        COUNT(nama_sekdes) as desa_dengan_sekdes,
        COUNT(no_hp_sekdes) as desa_dengan_kontak
        FROM desa WHERE status = 'aktif'";
    
    $stmt = $pdo->prepare($sql_stats);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\n=== STATISTIK KONTAK PERSON ===\n";
    echo "Total desa aktif: {$stats['total_desa']}\n";
    echo "Desa dengan kepala desa: {$stats['desa_dengan_kades']}\n";
    echo "Desa dengan sekretaris desa: {$stats['desa_dengan_sekdes']}\n";
    echo "Desa dengan nomor kontak: {$stats['desa_dengan_kontak']}\n";
    
    echo "\nUpdate kontak person selesai!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>