<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();
$conn = $db->getConnection();

// Fungsi helper untuk format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Fungsi helper untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('d', $timestamp);
    $bulan_num = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

// Ambil ID desa dari parameter
$desa_id = $_GET['id'] ?? 0;

if (!$desa_id) {
    header('Location: desa.php?error=invalid_id');
    exit;
}

// Ambil data desa
try {
    $desa = $db->select(
        "SELECT * FROM desa WHERE id = ? AND status != 'deleted'",
        [$desa_id]
    );
    
    if (empty($desa)) {
        header('Location: desa.php?error=not_found');
        exit;
    }
    
    $desa = $desa[0];
    
    // Ambil statistik transaksi
    $stats = $db->select("
        SELECT 
            COUNT(*) as total_transaksi,
            COALESCE(SUM(total_amount), 0) as total_nilai,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as transaksi_30_hari,
            COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END), 0) as nilai_30_hari
        FROM transaksi 
        WHERE desa_id = ? AND status_transaksi != 'selesai'
    ", [$desa_id]);
    
    $transaksi_stats = $stats[0] ?? [
        'total_transaksi' => 0,
        'total_nilai' => 0,
        'transaksi_30_hari' => 0,
        'nilai_30_hari' => 0
    ];
    
    // Ambil data piutang
    $piutang = $db->select("
        SELECT 
            COUNT(*) as total_piutang,
            COALESCE(SUM(jumlah_piutang), 0) as total_sisa,
            COUNT(CASE WHEN tanggal_jatuh_tempo < NOW() THEN 1 END) as jatuh_tempo
        FROM piutang 
        WHERE desa_id = ?
    ", [$desa_id]);
    
    $piutang_stats = $piutang[0] ?? [
        'total_piutang' => 0,
        'total_sisa' => 0,
        'jatuh_tempo' => 0
    ];
    
    // Ambil transaksi terbaru
    $transaksi_terbaru = $db->select("
        SELECT t.*, u.nama_lengkap as sales_name
        FROM transaksi t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.desa_id = ?
        ORDER BY t.created_at DESC
        LIMIT 5
    ", [$desa_id]);
    
    // Ambil jadwal kunjungan
    $jadwal_kunjungan = $db->select("
        SELECT jk.*, u.nama_lengkap as teknisi_name
        FROM jadwal_kunjungan jk
        LEFT JOIN users u ON jk.user_id = u.id
        WHERE jk.desa_id = ? AND jk.tanggal_kunjungan >= CURDATE()
        ORDER BY jk.tanggal_kunjungan ASC
        LIMIT 5
    ", [$desa_id]);
    
    // Ambil data website desa
    $website_desa = $db->select("
        SELECT website_url, has_database, news_active, developer_type, opendata_sync, keterangan
        FROM website_desa
        WHERE desa_id = ?
        LIMIT 1
    ", [$desa_id]);
    
    $website_info = $website_desa[0] ?? null;
    
    // Nama pembuat tidak tersedia (field created_by tidak ada di tabel desa)
    $creator_name = 'System';
    
    // Fungsi untuk membaca data perangkat desa dari CSV
    function getPerangkatDesa($nama_desa, $desa_id = null) {
        global $conn;
        $perangkat = [];
        
        try {
            // Prioritas 1: Ambil dari database jika desa_id tersedia
            if ($desa_id && $conn) {
                $stmt = $conn->prepare("SELECT nama_lengkap, jabatan, tempat_lahir, tanggal_lahir, alamat, no_telepon, pendidikan, tahun_diangkat, no_sk FROM perangkat_desa WHERE desa_id = ? AND status = 'aktif' ORDER BY CASE jabatan WHEN 'Kepala Desa' THEN 1 WHEN 'Sekretaris Desa' THEN 2 WHEN 'Kepala Dusun I' THEN 3 WHEN 'Kepala Dusun II' THEN 4 WHEN 'Anggota BPD' THEN 5 ELSE 6 END, nama_lengkap");
                $stmt->execute([$desa_id]);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $kepala_desa_found = false; // Flag untuk memastikan hanya 1 kepala desa
                
                foreach ($result as $row) {
                    $ttl = '';
                    if ($row['tempat_lahir'] && $row['tanggal_lahir']) {
                        $ttl = $row['tempat_lahir'] . ', ' . date('d-m-Y', strtotime($row['tanggal_lahir']));
                    }
                    
                    // Cek jika ini kepala desa
                    $is_kepala_desa = (stripos($row['jabatan'], 'kepala desa') !== false || stripos($row['jabatan'], 'kades') !== false);
                    
                    // Jika sudah ada kepala desa dan ini juga kepala desa, skip
                    if ($is_kepala_desa && $kepala_desa_found) {
                        error_log("Warning: Duplikasi kepala desa ditemukan untuk desa ID {$desa_id}: {$row['nama_lengkap']}");
                        continue;
                    }
                    
                    if ($is_kepala_desa) {
                        $kepala_desa_found = true;
                    }
                    
                    $perangkat[] = [
                        'nama_lengkap' => $row['nama_lengkap'],
                        'jabatan' => $row['jabatan'],
                        'telepon' => $row['no_telepon'],
                        'alamat' => $row['alamat'],
                        'ttl' => $ttl,
                        'pendidikan' => $row['pendidikan'],
                        'tahun_diangkat' => $row['tahun_diangkat'],
                        'no_sk' => $row['no_sk']
                    ];
                }
                
                // Jika data ditemukan di database, return langsung
                if (!empty($perangkat)) {
                    return $perangkat;
                }
            }
            
            // Prioritas 2: Fallback ke CSV jika tidak ada data di database
            $csv_file = 'data-desa.csv';
            $nama_desa_clean = trim(preg_replace('/^desa\s+/i', '', $nama_desa));
            
            if (file_exists($csv_file)) {
                $handle = fopen($csv_file, 'r');
                $header = fgetcsv($handle); // Skip header
                
                $kepala_desa_found_csv = false; // Flag untuk CSV juga
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    // Kolom CSV: No, Nama Desa, Kecamatan, Nama Lengkap, TTL, Alamat, Telepon, Pendidikan, Diangkat, No SK, Jabatan
                    // Hilangkan kata "Desa" dari data CSV juga untuk perbandingan
                    $csv_nama_desa = trim(preg_replace('/^desa\s+/i', '', trim($data[1])));
                    if (count($data) >= 11 && strtolower($csv_nama_desa) === strtolower($nama_desa_clean)) {
                        
                        $jabatan = trim($data[10]);
                        $is_kepala_desa_csv = (stripos($jabatan, 'kepala desa') !== false || stripos($jabatan, 'kades') !== false);
                        
                        // Jika sudah ada kepala desa dan ini juga kepala desa, skip
                        if ($is_kepala_desa_csv && $kepala_desa_found_csv) {
                            error_log("Warning: Duplikasi kepala desa ditemukan di CSV untuk desa {$nama_desa}: {$data[3]}");
                            continue;
                        }
                        
                        if ($is_kepala_desa_csv) {
                            $kepala_desa_found_csv = true;
                        }
                        
                        $perangkat[] = [
                            'nama_lengkap' => trim($data[3]),
                            'jabatan' => $jabatan,
                            'telepon' => trim($data[6]),
                            'alamat' => trim($data[5]),
                            'ttl' => trim($data[4]),
                            'pendidikan' => trim($data[7]),
                            'tahun_diangkat' => trim($data[8]),
                            'no_sk' => trim($data[9])
                        ];
                    }
                }
                fclose($handle);
            }
        } catch (Exception $e) {
            error_log("Error in getPerangkatDesa: " . $e->getMessage());
        }
        
        // Urutkan berdasarkan jabatan (Kepala Desa, Sekretaris Desa, dll)
        usort($perangkat, function($a, $b) {
            $priority = [
                'kepala desa' => 1,
                'sekretaris desa' => 2,
                'kasi' => 3,
                'kaur' => 4,
                'kadus' => 5,
                'kepala dusun' => 5,
                'staf' => 6
            ];
            
            $a_priority = 999;
            $b_priority = 999;
            
            foreach ($priority as $key => $value) {
                if (stripos($a['jabatan'], $key) !== false) {
                    $a_priority = $value;
                    break;
                }
            }
            
            foreach ($priority as $key => $value) {
                if (stripos($b['jabatan'], $key) !== false) {
                    $b_priority = $value;
                    break;
                }
            }
            
            return $a_priority - $b_priority;
        });
        
        return $perangkat;
    }
    
    // Ambil data perangkat desa
    $perangkat_desa = getPerangkatDesa($desa['nama_desa'], $desa_id);
    
} catch (Exception $e) {
    header('Location: desa.php?error=database_error');
    exit;
}
?>
<?php include 'layouts/header.php'; ?>
<!-- Main Content -->
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mr-auto px-4 sm:px-6 lg:px-8 py-8">


        <!-- Header Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Detail Desa <?= htmlspecialchars($desa['nama_desa']) ?></h1>
                        <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($desa['kecamatan']) ?>, <?= htmlspecialchars($desa['kabupaten']) ?>, <?= htmlspecialchars($desa['provinsi']) ?></p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="desa.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Kembali
                        </a>
                        <a href="desa-edit.php?id=<?= $desa['id'] ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit Desa
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <!-- Action Buttons -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="px-6 py-4">
                <div class="flex flex-wrap gap-3">
                    <?php if (AuthStatic::hasRole(['admin', 'sales'])): ?>
                    <a href="transaksi-add.php?desa_id=<?= $desa['id'] ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Buat Transaksi
                    </a>
                    <?php endif; ?>
                    <a href="transaksi.php?desa_id=<?= $desa['id'] ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Lihat Semua Transaksi
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistik -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Statistik Transaksi
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-blue-600"><?= number_format($transaksi_stats['total_transaksi']) ?></div>
                        <div class="text-sm text-gray-600 mt-1">Total Transaksi</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-green-600"><?= formatRupiah($transaksi_stats['total_nilai']) ?></div>
                        <div class="text-sm text-gray-600 mt-1">Total Nilai</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-purple-600"><?= number_format($transaksi_stats['transaksi_30_hari']) ?></div>
                        <div class="text-sm text-gray-600 mt-1">Transaksi 30 Hari</div>
                    </div>
                    <div class="bg-indigo-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-indigo-600"><?= formatRupiah($transaksi_stats['nilai_30_hari']) ?></div>
                        <div class="text-sm text-gray-600 mt-1">Nilai 30 Hari</div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-orange-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-orange-600"><?= number_format($piutang_stats['total_piutang']) ?></div>
                        <div class="text-sm text-gray-600 mt-1">Total Piutang</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-yellow-600"><?= formatRupiah($piutang_stats['total_sisa']) ?></div>
                        <div class="text-sm text-gray-600 mt-1">Sisa Piutang</div>
                    </div>
                    <div class="<?= $piutang_stats['jatuh_tempo'] > 0 ? 'bg-red-50' : 'bg-gray-50' ?> rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold <?= $piutang_stats['jatuh_tempo'] > 0 ? 'text-red-600' : 'text-gray-600' ?>">
                            <?= number_format($piutang_stats['jatuh_tempo']) ?>
                        </div>
                        <div class="text-sm text-gray-600 mt-1">Piutang Jatuh Tempo</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Informasi Desa -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Informasi Desa
                    </h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Nama Desa:</span>
                            <span class="text-gray-900"><?= htmlspecialchars($desa['nama_desa']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Kecamatan:</span>
                            <span class="text-gray-900"><?= htmlspecialchars($desa['kecamatan']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Kabupaten:</span>
                            <span class="text-gray-900"><?= htmlspecialchars($desa['kabupaten']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Provinsi:</span>
                            <span class="text-gray-900"><?= htmlspecialchars($desa['provinsi'] ?? '') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Kode Pos:</span>
                            <span class="text-gray-900"><?= htmlspecialchars($desa['kode_pos'] ?? '') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Status:</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $desa['status'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                <?= strtoupper($desa['status']) ?>
                            </span>
                        </div>
                        
                        <?php if ($website_info && $website_info['website_url']): ?>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-700">Website Resmi:</span>
                            <div class="flex items-center space-x-2">
                                <a href="<?= htmlspecialchars($website_info['website_url']) ?>" target="_blank" 
                                   class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                    Kunjungi Website
                                </a>
                                <span class="text-xs text-gray-500">
                                    <?= strpos($website_info['website_url'], '.desa.id') !== false ? '(Resmi)' : '(Eksternal)' ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($desa['alamat']): ?>
                    <div class="mt-6">
                        <span class="font-medium text-gray-700">Alamat Lengkap:</span>
                        <div class="mt-2 p-3 bg-gray-50 rounded-md">
                            <?= nl2br(htmlspecialchars($desa['alamat'] ?? '')) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Informasi Kontak -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Informasi Kontak
                    </h2>
                </div>
                <div class="p-6">
                    <div class="text-center mb-6">
                          <h3 class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($desa['nama_kepala_desa'] ?? '') ?></h3>
                          <p class="text-gray-600 mt-1"><?= htmlspecialchars($desa['jabatan_kepala_desa'] ?? 'Kepala Desa') ?></p>
                      </div>
                     
                     <div class="space-y-3 mb-6">
                         <div class="flex items-center">
                             <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                             </svg>
                             <span class="text-gray-900"><?= htmlspecialchars($desa['no_hp_sekdes'] ?? $desa['no_hp_kepala_desa'] ?? '') ?></span>
                         </div>
                         <?php if ($desa['email_desa']): ?>
                         <div class="flex items-center">
                             <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                             </svg>
                             <span class="text-gray-900"><?= htmlspecialchars($desa['email_desa'] ?? '') ?></span>
                         </div>
                         <?php endif; ?>
                     </div>
                     
                     <div class="flex space-x-3">
                         <a href="tel:<?= htmlspecialchars($desa['no_hp_sekdes'] ?? $desa['no_hp_kepala_desa'] ?? '') ?>" class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                             <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                             </svg>
                             Telepon
                         </a>
                         <?php if ($desa['email_desa']): ?>
                         <a href="mailto:<?= htmlspecialchars($desa['email_desa'] ?? '') ?>" class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                             <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                             </svg>
                             Email
                         </a>
                         <?php endif; ?>
                     </div>
                     
                     <!-- PIN Management Section -->
                     <?php if (AuthStatic::hasRole(['admin'])): ?>
                     <div class="mt-6 pt-6 border-t border-gray-200">
                         <h4 class="text-sm font-medium text-gray-700 mb-3 flex items-center">
                             <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                             </svg>
                             Manajemen PIN Portal
                         </h4>
                         <p class="text-xs text-gray-500 mb-3">PIN digunakan desa untuk login ke portal klien</p>
                         <div class="flex space-x-2">
                             <button onclick="showResetPinModal()" class="inline-flex items-center px-3 py-2 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                 <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                 </svg>
                                 Reset PIN
                             </button>
                             <button onclick="showPinInfoModal()" class="inline-flex items-center px-3 py-2 border border-blue-300 text-xs font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                 <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                 </svg>
                                 Info PIN
                             </button>
                         </div>
                     </div>
                     <?php endif; ?>
                    
                    <?php if ($desa['catatan_khusus']): ?>
                    <div class="mt-6">
                        <span class="font-medium text-gray-700">Catatan:</span>
                        <div class="mt-2 p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded-md">
                            <?= nl2br(htmlspecialchars($desa['catatan_khusus'] ?? '')) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Transaksi Terbaru -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m8 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                        </svg>
                        Transaksi Terbaru
                    </h2>
                    <a href="transaksi.php?desa_id=<?= $desa['id'] ?>" class="text-sm text-blue-600 hover:text-blue-800">
                        Lihat Semua
                    </a>
                </div>
                <div class="p-6">
                    <?php if (empty($transaksi_terbaru)): ?>
                        <p class="text-gray-500 text-center py-8">Belum ada transaksi</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($transaksi_terbaru as $t): ?>
                                    <tr>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900"><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900"><?= formatRupiah($t['total_amount']) ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $t['status_transaksi'] === 'selesai' ? 'bg-green-100 text-green-800' : ($t['status_transaksi'] === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                                <?= ucfirst($t['status_transaksi']) ?>
                                            </span>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($t['sales_name']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Jadwal Kunjungan -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Jadwal Kunjungan Mendatang
                    </h2>
                    <a href="jadwal.php?desa_id=<?= $desa['id'] ?>" class="text-sm text-blue-600 hover:text-blue-800">
                        Lihat Semua
                    </a>
                </div>
                <div class="p-6">
                    <?php if (empty($jadwal_kunjungan)): ?>
                        <p class="text-gray-500 text-center py-8">Belum ada jadwal kunjungan</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keperluan</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teknisi</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($jadwal_kunjungan as $j): ?>
                                    <tr>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900"><?= date('d/m/Y', strtotime($j['tanggal_kunjungan'])) ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $j['jenis_kunjungan']))) ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($j['teknisi_name'] ?? 'Belum ditentukan') ?></td>
                                        <td class="px-3 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $j['status'] === 'selesai' ? 'bg-green-100 text-green-800' : ($j['status'] === 'dijadwalkan' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') ?>">
                                                <?= ucfirst(str_replace('_', ' ', $j['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Perangkat Desa -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Perangkat Desa <?= htmlspecialchars($desa['nama_desa']) ?>
                </h2>
                <p class="text-sm text-gray-600 mt-1">Daftar lengkap perangkat desa dan struktur organisasi</p>
            </div>
            <div class="p-6">
                <?php if (empty($perangkat_desa)): ?>
                    <div class="text-center py-12">
                        <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Data Perangkat Desa Tidak Tersedia</h3>
                        <p class="text-gray-500">Data perangkat desa untuk <?= htmlspecialchars($desa['nama_desa']) ?> belum tersedia dalam sistem.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Telepon</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alamat</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pendidikan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tahun Diangkat</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($perangkat_desa as $index => $perangkat): ?>
                                <tr class="<?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?> hover:bg-blue-50 transition-colors duration-200">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="ml-3">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($perangkat['jabatan']) ?></div>
                                                <?php if ($perangkat['no_sk']): ?>
                                                <div class="text-xs text-gray-500">SK: <?= htmlspecialchars($perangkat['no_sk']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($perangkat['nama_lengkap']) ?></div>
                                        <?php if ($perangkat['ttl']): ?>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($perangkat['ttl']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <?php if ($perangkat['telepon'] && $perangkat['telepon'] !== '-' && $perangkat['telepon'] !== '00000000000'): ?>
                                        <div class="flex items-center">
                                            <a href="tel:<?= htmlspecialchars($perangkat['telepon']) ?>" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                </svg>
                                                <?= htmlspecialchars($perangkat['telepon']) ?>
                                            </a>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-sm text-gray-400">Tidak tersedia</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="text-sm text-gray-900 max-w-xs truncate" title="<?= htmlspecialchars($perangkat['alamat']) ?>">
                                            <?= htmlspecialchars($perangkat['alamat']) ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?= htmlspecialchars($perangkat['pendidikan']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($perangkat['tahun_diangkat']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Summary Statistics -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-blue-50 rounded-lg p-4 text-center">
                                <div class="text-2xl font-bold text-blue-600"><?= count($perangkat_desa) ?></div>
                                <div class="text-sm text-gray-600 mt-1">Total Perangkat</div>
                            </div>
                            <div class="bg-green-50 rounded-lg p-4 text-center">
                                <div class="text-2xl font-bold text-green-600">
                                    <?= count(array_filter($perangkat_desa, function($p) { return stripos($p['jabatan'], 'kepala desa') !== false; })) ?>
                                </div>
                                <div class="text-sm text-gray-600 mt-1">Kepala Desa</div>
                            </div>
                            <div class="bg-purple-50 rounded-lg p-4 text-center">
                                <div class="text-2xl font-bold text-purple-600">
                                    <?= count(array_filter($perangkat_desa, function($p) { return stripos($p['jabatan'], 'kadus') !== false || stripos($p['jabatan'], 'kepala dusun') !== false; })) ?>
                                </div>
                                <div class="text-sm text-gray-600 mt-1">Kepala Dusun</div>
                            </div>
                            <div class="bg-orange-50 rounded-lg p-4 text-center">
                                <div class="text-2xl font-bold text-orange-600">
                                    <?= count(array_filter($perangkat_desa, function($p) { return stripos($p['jabatan'], 'bpd') !== false; })) ?>
                                </div>
                                <div class="text-sm text-gray-600 mt-1">Anggota BPD</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informasi Sistem -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Informasi Sistem
                </h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">ID Desa:</span>
                            <span class="text-gray-900">#<?= $desa['id'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Dibuat:</span>
                            <span class="text-gray-900"><?= formatTanggalIndonesia($desa['created_at']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Dibuat oleh:</span>
                            <span class="text-gray-900"><?= htmlspecialchars($creator_name) ?></span>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Terakhir Update:</span>
                            <span class="text-gray-900"><?= $desa['updated_at'] ? formatTanggalIndonesia($desa['updated_at']) : 'Belum pernah' ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Status Sistem:</span>
                            <div class="flex flex-col items-end">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                                <span class="text-xs text-gray-500 mt-1">Data tersinkronisasi</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Reset PIN -->
<div id="resetPinModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Reset PIN Desa
                </h3>
                <button onclick="closeResetPinModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-4 w-4 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-2">
                        <h4 class="text-sm font-medium text-yellow-800">Peringatan</h4>
                        <p class="text-xs text-yellow-700 mt-1">PIN lama akan tidak dapat digunakan lagi. Pastikan memberitahu desa tentang PIN baru.</p>
                    </div>
                </div>
            </div>
            
            <p class="text-sm text-gray-600 mb-4">
                Anda akan diarahkan ke halaman reset PIN untuk desa <strong><?= htmlspecialchars($desa['nama_desa']) ?></strong>.
            </p>
            
            <div class="flex justify-end space-x-3">
                <button onclick="closeResetPinModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    Batal
                </button>
                <a href="desa-reset-pin.php?id=<?= $desa['id'] ?>" class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    Lanjutkan Reset
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Info PIN -->
<div id="pinInfoModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Informasi PIN Portal
                </h3>
                <button onclick="closePinInfoModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="space-y-3 text-sm text-gray-600">
                <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                    <h4 class="font-medium text-blue-800 mb-2">Tentang PIN Portal</h4>
                    <ul class="text-xs text-blue-700 space-y-1">
                        <li>• PIN digunakan desa untuk login ke portal klien</li>
                        <li>• PIN terdiri dari 6 digit angka</li>
                        <li>• PIN dibuat saat registrasi desa pertama kali</li>
                        <li>• PIN disimpan dengan enkripsi yang aman</li>
                    </ul>
                </div>
                
                <div class="bg-green-50 border border-green-200 rounded-md p-3">
                    <h4 class="font-medium text-green-800 mb-2">Cara Menggunakan</h4>
                    <ul class="text-xs text-green-700 space-y-1">
                        <li>• Desa login dengan nama desa, kecamatan, dan PIN</li>
                        <li>• Setelah login, desa dapat mengakses fitur portal</li>
                        <li>• Portal dapat diakses di: <a href="<?= $_SERVER['HTTP_HOST'] ?>/client/login.php" class="underline" target="_blank"><?= $_SERVER['HTTP_HOST'] ?>/client/</a></li>
                    </ul>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-md p-3">
                    <h4 class="font-medium text-red-800 mb-2">Jika Desa Lupa PIN</h4>
                    <ul class="text-xs text-red-700 space-y-1">
                        <li>• Desa dapat menghubungi admin untuk reset PIN</li>
                        <li>• Admin dapat mereset PIN melalui halaman ini</li>
                        <li>• PIN baru akan diberikan kepada desa</li>
                    </ul>
                </div>
            </div>
            
            <div class="flex justify-end mt-4">
                <button onclick="closePinInfoModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Modal functions
function showResetPinModal() {
    document.getElementById('resetPinModal').classList.remove('hidden');
}

function closeResetPinModal() {
    document.getElementById('resetPinModal').classList.add('hidden');
}

function showPinInfoModal() {
    document.getElementById('pinInfoModal').classList.remove('hidden');
}

function closePinInfoModal() {
    document.getElementById('pinInfoModal').classList.add('hidden');
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const resetModal = document.getElementById('resetPinModal');
    const infoModal = document.getElementById('pinInfoModal');
    
    if (event.target === resetModal) {
        closeResetPinModal();
    }
    
    if (event.target === infoModal) {
        closePinInfoModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeResetPinModal();
        closePinInfoModal();
    }
});
</script>

<?php include 'layouts/footer.php'; ?>
