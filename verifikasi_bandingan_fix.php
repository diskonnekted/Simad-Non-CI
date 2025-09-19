<?php
require_once 'config/database.php';

try {
    echo "=== VERIFIKASI HASIL PERBAIKAN DESA BANDINGAN ===\n\n";
    
    $db = getDatabase();
    
    // Ambil data ketiga desa Bandingan
    $bandingan_desa = $db->select(
        "SELECT id, nama_desa, kecamatan, nama_kepala_desa, nama_sekdes, no_hp_kepala_desa, no_hp_sekdes
         FROM desa 
         WHERE nama_desa = 'Bandingan'
         ORDER BY kecamatan"
    );
    
    echo "📊 STATUS DATA BANDINGAN SETELAH PERBAIKAN:\n";
    echo str_repeat("=", 70) . "\n";
    
    $total_lengkap = 0;
    $total_desa = count($bandingan_desa);
    
    foreach ($bandingan_desa as $desa) {
        echo "\n🏛️  {$desa['nama_desa']} - Kecamatan {$desa['kecamatan']} (ID: {$desa['id']})\n";
        echo str_repeat("-", 50) . "\n";
        
        // Cek kelengkapan data
        $kepala_lengkap = !empty($desa['nama_kepala_desa']) && !empty($desa['no_hp_kepala_desa']);
        $sekdes_lengkap = !empty($desa['nama_sekdes']) && !empty($desa['no_hp_sekdes']);
        $semua_lengkap = $kepala_lengkap && $sekdes_lengkap;
        
        if ($semua_lengkap) $total_lengkap++;
        
        echo "📋 KONTAK PERSON:\n";
        echo "   👤 Kepala Desa:\n";
        echo "      Nama: " . ($desa['nama_kepala_desa'] ?: '❌ KOSONG') . "\n";
        echo "      HP: " . ($desa['no_hp_kepala_desa'] ?: '❌ KOSONG') . "\n";
        echo "      Status: " . ($kepala_lengkap ? '✅ LENGKAP' : '⚠️  TIDAK LENGKAP') . "\n";
        
        echo "\n   📝 Sekretaris Desa:\n";
        echo "      Nama: " . ($desa['nama_sekdes'] ?: '❌ KOSONG') . "\n";
        echo "      HP: " . ($desa['no_hp_sekdes'] ?: '❌ KOSONG') . "\n";
        echo "      Status: " . ($sekdes_lengkap ? '✅ LENGKAP' : '⚠️  TIDAK LENGKAP') . "\n";
        
        echo "\n🎯 KELENGKAPAN KESELURUHAN: " . ($semua_lengkap ? '✅ LENGKAP' : '⚠️  PERLU DILENGKAPI') . "\n";
    }
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "📊 RINGKASAN VERIFIKASI:\n";
    echo "   • Total desa Bandingan: {$total_desa}\n";
    echo "   • Desa dengan data lengkap: {$total_lengkap}\n";
    echo "   • Desa yang masih perlu dilengkapi: " . ($total_desa - $total_lengkap) . "\n";
    echo "   • Persentase kelengkapan: " . round(($total_lengkap / $total_desa) * 100, 1) . "%\n\n";
    
    // Test tampilan halaman desa
    echo "🌐 TEST TAMPILAN HALAMAN DESA:\n";
    echo str_repeat("-", 40) . "\n";
    
    foreach ($bandingan_desa as $desa) {
        echo "\n📄 Simulasi halaman {$desa['nama_desa']} - Kec. {$desa['kecamatan']}:\n";
        
        if (!empty($desa['nama_kepala_desa']) || !empty($desa['nama_sekdes'])) {
            echo "   ✅ KONTAK PERSON AKAN MUNCUL:\n";
            
            if (!empty($desa['nama_kepala_desa'])) {
                echo "      • Kepala Desa: {$desa['nama_kepala_desa']}";
                if (!empty($desa['no_hp_kepala_desa'])) {
                    echo " - {$desa['no_hp_kepala_desa']}";
                }
                echo "\n";
            }
            
            if (!empty($desa['nama_sekdes'])) {
                echo "      • Sekretaris Desa: {$desa['nama_sekdes']}";
                if (!empty($desa['no_hp_sekdes'])) {
                    echo " - {$desa['no_hp_sekdes']}";
                }
                echo "\n";
            }
        } else {
            echo "   ❌ HALAMAN KOSONG - Tidak ada kontak person yang ditampilkan\n";
        }
    }
    
    // Bandingkan dengan data CSV
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "🔍 VERIFIKASI DENGAN DATA CSV:\n";
    echo str_repeat("-", 40) . "\n";
    
    $csv_file = 'data-desa.csv';
    if (file_exists($csv_file)) {
        $handle = fopen($csv_file, 'r');
        $header = fgetcsv($handle);
        
        $csv_bandingan = [];
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) >= 11 && strtolower(trim($data[1])) === 'bandingan') {
                $kecamatan = trim($data[2]);
                $jabatan = strtolower(trim($data[10]));
                
                if (strpos($jabatan, 'kepala desa') !== false || strpos($jabatan, 'sekretaris desa') !== false || strpos($jabatan, 'sekdes') !== false) {
                    if (!isset($csv_bandingan[$kecamatan])) {
                        $csv_bandingan[$kecamatan] = [];
                    }
                    $csv_bandingan[$kecamatan][] = [
                        'nama' => trim($data[3]),
                        'hp' => trim($data[6]),
                        'jabatan' => trim($data[10])
                    ];
                }
            }
        }
        fclose($handle);
        
        foreach ($bandingan_desa as $desa) {
            $kec = $desa['kecamatan'];
            echo "\n📋 {$desa['nama_desa']} - Kec. {$kec}:\n";
            
            if (isset($csv_bandingan[$kec])) {
                echo "   ✅ Data ditemukan di CSV:\n";
                foreach ($csv_bandingan[$kec] as $perangkat) {
                    echo "      • {$perangkat['jabatan']}: {$perangkat['nama']} ({$perangkat['hp']})\n";
                }
                
                // Bandingkan dengan database
                $db_kepala = $desa['nama_kepala_desa'];
                $db_sekdes = $desa['nama_sekdes'];
                
                $csv_kepala = null;
                $csv_sekdes = null;
                
                foreach ($csv_bandingan[$kec] as $perangkat) {
                    if (strpos(strtolower($perangkat['jabatan']), 'kepala desa') !== false) {
                        $csv_kepala = $perangkat['nama'];
                    }
                    if (strpos(strtolower($perangkat['jabatan']), 'sekretaris desa') !== false || strpos(strtolower($perangkat['jabatan']), 'sekdes') !== false) {
                        $csv_sekdes = $perangkat['nama'];
                    }
                }
                
                echo "   🔍 Perbandingan Database vs CSV:\n";
                if ($csv_kepala) {
                    $match_kepala = ($db_kepala === $csv_kepala);
                    echo "      Kepala Desa: " . ($match_kepala ? '✅ SESUAI' : '⚠️  BERBEDA') . "\n";
                    if (!$match_kepala) {
                        echo "        DB: {$db_kepala}\n";
                        echo "        CSV: {$csv_kepala}\n";
                    }
                }
                
                if ($csv_sekdes) {
                    $match_sekdes = ($db_sekdes === $csv_sekdes);
                    echo "      Sekretaris: " . ($match_sekdes ? '✅ SESUAI' : '⚠️  BERBEDA') . "\n";
                    if (!$match_sekdes) {
                        echo "        DB: {$db_sekdes}\n";
                        echo "        CSV: {$csv_sekdes}\n";
                    }
                }
            } else {
                echo "   ❌ Data tidak ditemukan di CSV untuk kecamatan {$kec}\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "✅ KESIMPULAN VERIFIKASI:\n";
    
    if ($total_lengkap === $total_desa) {
        echo "🎉 SEMUA DESA BANDINGAN SUDAH MEMILIKI DATA KONTAK LENGKAP!\n";
        echo "   • Data sudah terpisah dengan benar per kecamatan\n";
        echo "   • Kontak person akan muncul di halaman desa\n";
        echo "   • Perbaikan data tercampur berhasil\n";
    } else {
        echo "⚠️  Masih ada " . ($total_desa - $total_lengkap) . " desa yang perlu dilengkapi datanya\n";
    }
    
    echo "\n💡 REKOMENDASI:\n";
    echo "1. ✅ Verifikasi nomor HP masih aktif\n";
    echo "2. ✅ Test akses halaman desa di browser\n";
    echo "3. ✅ Dokumentasikan perubahan yang telah dilakukan\n";
    echo "4. ✅ Monitor feedback dari pengguna\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>