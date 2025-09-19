<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin', 'sales', 'programmer'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$user = AuthStatic::getCurrentUser();
$db = getDatabase();

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

$error = '';
$success = '';
$desa = null;

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
} catch (Exception $e) {
    header('Location: desa.php?error=database_error');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_desa = trim($_POST['nama_desa'] ?? '');
    $kecamatan = trim($_POST['kecamatan'] ?? '');
    $kabupaten = trim($_POST['kabupaten'] ?? '');
    $provinsi = trim($_POST['provinsi'] ?? '');
    $kode_pos = trim($_POST['kode_pos'] ?? '');
    $alamat = trim($_POST['alamat_lengkap'] ?? '');
    $kontak_person = trim($_POST['kontak_person'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    $status = $_POST['status'] ?? 'aktif';
    $latitude = trim($_POST['latitude'] ?? '') ?: null;
    $longitude = trim($_POST['longitude'] ?? '') ?: null;
    
    // Validasi
    if (empty($nama_desa)) {
        $error = 'Nama desa harus diisi';
    } elseif (empty($kecamatan)) {
        $error = 'Kecamatan harus diisi';
    } elseif (empty($kabupaten)) {
        $error = 'Kabupaten harus diisi';
    } elseif (empty($kontak_person)) {
        $error = 'Kontak person harus diisi';
    } elseif (empty($no_telepon)) {
        $error = 'Nomor telepon harus diisi';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } else {
        try {
            // Cek duplikasi nama desa (kecuali desa yang sedang diedit)
            $existing = $db->select(
                "SELECT id FROM desa WHERE nama_desa = ? AND kecamatan = ? AND kabupaten = ? AND id != ?", 
                [$nama_desa, $kecamatan, $kabupaten, $desa_id]
            );
            
            if (!empty($existing)) {
                $error = 'Desa dengan nama yang sama sudah ada di kecamatan dan kabupaten tersebut';
            } else {
                // Update data desa
                $query = "
                    UPDATE desa SET 
                        nama_desa = ?, kecamatan = ?, kabupaten = ?, provinsi = ?, kode_pos = ?, 
                        alamat = ?, nama_kepala_desa = ?, jabatan_kepala_desa = ?, no_hp_kepala_desa = ?, 
                        email_desa = ?, catatan_khusus = ?, status = ?, latitude = ?, longitude = ?, updated_at = NOW()
                    WHERE id = ?
                ";
                
                $params = [
                    $nama_desa, $kecamatan, $kabupaten, $provinsi, $kode_pos, $alamat,
                    $kontak_person, $jabatan, $no_telepon, $email ?: null, $catatan, $status, $latitude, $longitude, $desa_id
                ];
                
                $db->execute($query, $params);
                
                $success = 'Data desa berhasil diperbarui';
                
                // Refresh data desa
                $desa = $db->select(
                    "SELECT * FROM desa WHERE id = ?",
                    [$desa_id]
                )[0];
            }
        } catch (Exception $e) {
            $error = 'Gagal memperbarui data desa: ' . $e->getMessage();
        }
    }
}
$page_title = 'Edit Desa';
require_once 'layouts/header.php';
?>

<!-- Mapbox CSS -->
<link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet">
    
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 flex items-center">
                            <i class="fas fa-edit mr-3 text-primary-600"></i>
                            Edit Desa: <?= htmlspecialchars($desa['nama_desa']) ?>
                            <span class="ml-3 px-2 py-1 text-xs font-semibold rounded-full <?= $desa['status'] === 'aktif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= strtoupper($desa['status']) ?>
                            </span>
                        </h1>
                        <p class="text-gray-600 mt-1">Perbarui data desa dan informasi kontak</p>
                    </div>

        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <!-- Success Message -->
                <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            <span><?= htmlspecialchars($success) ?></span>
                        </div>
                        <a href="desa.php" class="inline-flex items-center px-3 py-1 border border-green-300 rounded text-sm font-medium text-green-700 bg-white hover:bg-green-50">
                            Kembali ke Daftar
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <!-- Informasi Desa -->
                    <div class="bg-white rounded-lg shadow-sm border border-l-4 border-l-primary-500 p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                            <i class="fas fa-map-marker-alt mr-2 text-primary-600"></i>
                            Informasi Desa
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="nama_desa" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nama Desa <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="nama_desa" 
                                       name="nama_desa" 
                                       value="<?= htmlspecialchars($desa['nama_desa']) ?>" 
                                       placeholder="Contoh: Sukamaju" 
                                       required>
                            </div>
                            <div>
                                <label for="kecamatan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Kecamatan <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="kecamatan" 
                                       name="kecamatan" 
                                       value="<?= htmlspecialchars($desa['kecamatan']) ?>" 
                                       placeholder="Contoh: Cianjur" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label for="kabupaten" class="block text-sm font-medium text-gray-700 mb-2">
                                    Kabupaten <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="kabupaten" 
                                       name="kabupaten" 
                                           value="<?= htmlspecialchars($desa['kabupaten']) ?>" 
                                           placeholder="Contoh: Cianjur" required>
                                </div>
                            </div>
                            <div>
                                <label for="provinsi" class="block text-sm font-medium text-gray-700 mb-2">
                                    Provinsi
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="provinsi" 
                                       name="provinsi" 
                                       value="<?= htmlspecialchars($desa['provinsi']) ?>" 
                                       placeholder="Contoh: Jawa Barat">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                            <div>
                                <label for="kode_pos" class="block text-sm font-medium text-gray-700 mb-2">
                                    Kode Pos
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="kode_pos" 
                                       name="kode_pos" 
                                       value="<?= htmlspecialchars($desa['kode_pos']) ?>" 
                                       placeholder="43xxx" 
                                       maxlength="5">
                            </div>
                            <div>
                                <label for="alamat_lengkap" class="block text-sm font-medium text-gray-700 mb-2">
                                    Alamat Lengkap
                                </label>
                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                          id="alamat_lengkap" 
                                          name="alamat_lengkap" 
                                          rows="3" 
                                          placeholder="Alamat lengkap kantor desa atau balai desa"><?= htmlspecialchars($desa['alamat'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                    Status Desa
                                </label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                        id="status" 
                                        name="status">
                                    <option value="aktif" <?= $desa['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="nonaktif" <?= $desa['status'] === 'nonaktif' ? 'selected' : '' ?>>Non-Aktif</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Kontak -->
                    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                            <i class="fas fa-user mr-2 text-primary-600"></i>
                            Informasi Kontak Person
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="kontak_person" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nama Kontak Person <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="kontak_person" 
                                       name="kontak_person" 
                                       value="<?= htmlspecialchars($desa['nama_kepala_desa'] ?? '') ?>" 
                                       placeholder="Nama lengkap kontak person" 
                                       required>
                            </div>
                            <div>
                                <label for="jabatan" class="block text-sm font-medium text-gray-700 mb-2">
                                    Jabatan
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="jabatan" 
                                       name="jabatan" 
                                       value="<?= htmlspecialchars($desa['jabatan_kepala_desa'] ?? '') ?>" 
                                       placeholder="Contoh: Kepala Desa, Sekretaris Desa">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label for="no_telepon" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nomor Telepon <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="no_telepon" 
                                       name="no_telepon" 
                                       value="<?= htmlspecialchars($desa['no_hp_kepala_desa'] ?? '') ?>" 
                                       placeholder="08xxxxxxxxxx" 
                                       required>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email
                                </label>
                                <input type="email" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="email" 
                                       name="email" 
                                       value="<?= htmlspecialchars($desa['email_desa'] ?? '') ?>" 
                                       placeholder="email@domain.com">
                            </div>
                        </div>
                    </div>

                    <!-- Lokasi Kantor Desa -->
                    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                            <i class="fas fa-map-marked-alt mr-2 text-primary-600"></i>
                            Lokasi Kantor Desa
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="latitude" class="block text-sm font-medium text-gray-700 mb-2">
                                    Latitude
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="latitude" 
                                       name="latitude" 
                                       value="<?= htmlspecialchars($desa['latitude'] ?? '') ?>" 
                                       placeholder="-6.2088" 
                                       step="any">
                            </div>
                            <div>
                                <label for="longitude" class="block text-sm font-medium text-gray-700 mb-2">
                                    Longitude
                                </label>
                                <input type="text" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                       id="longitude" 
                                       name="longitude" 
                                       value="<?= htmlspecialchars($desa['longitude'] ?? '') ?>" 
                                       placeholder="106.8456" 
                                       step="any">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <button type="button" 
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:ring ring-blue-300 transition ease-in-out duration-150" 
                                    onclick="getCurrentLocation()">
                                <i class="fas fa-location-arrow mr-2"></i>
                                Gunakan Lokasi Saat Ini
                            </button>
                            <button type="button" 
                                    class="ml-2 inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:ring ring-green-300 transition ease-in-out duration-150" 
                                    onclick="showMap()">
                                <i class="fas fa-map mr-2"></i>
                                Pilih di Peta
                            </button>
                        </div>
                        
                        <!-- Map Container -->
                        <div id="map-container" class="hidden">
                            <div class="mb-4">
                                <p class="text-sm text-gray-600">Klik pada peta untuk menandai lokasi kantor desa</p>
                            </div>
                            <div id="map" class="w-full h-96 rounded-lg border border-gray-300"></div>
                            <div class="mt-4 flex justify-end">
                                <button type="button" 
                                        class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring ring-gray-300 transition ease-in-out duration-150" 
                                        onclick="hideMap()">
                                    <i class="fas fa-times mr-2"></i>
                                    Tutup Peta
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Catatan Tambahan -->
                    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                            <i class="fas fa-sticky-note mr-2 text-primary-600"></i>
                            Catatan Tambahan
                        </h3>
                        
                        <div>
                            <label for="catatan" class="block text-sm font-medium text-gray-700 mb-2">
                                Catatan
                            </label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500" 
                                      id="catatan" 
                                      name="catatan" 
                                      rows="4" 
                                      placeholder="Catatan khusus tentang desa ini (opsional)"><?= htmlspecialchars($desa['catatan_khusus'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Informasi Sistem -->
                    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200 flex items-center">
                            <i class="fas fa-info-circle mr-2 text-primary-600"></i>
                            Informasi Sistem
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <p class="text-sm text-gray-600"><span class="font-medium text-gray-900">Dibuat:</span> <?= formatTanggalIndonesia($desa['created_at']) ?></p>
                                <p class="text-sm text-gray-600"><span class="font-medium text-gray-900">Terakhir Diperbarui:</span> <?= $desa['updated_at'] ? formatTanggalIndonesia($desa['updated_at']) : 'Belum pernah' ?></p>
                            </div>
                            <div class="space-y-2">
                                <?php
                                // Ambil nama pembuat
                                $creator_id = $desa['created_by'] ?? null;
                                if ($creator_id) {
                                    $creator = $db->select("SELECT username FROM users WHERE id = ?", [$creator_id]);
                                    $creator_name = !empty($creator) ? $creator[0]['username'] : 'Unknown';
                                } else {
                                    $creator_name = 'Unknown';
                                }
                                ?>
                                <p class="text-sm text-gray-600"><span class="font-medium text-gray-900">Dibuat oleh:</span> <?= htmlspecialchars($creator_name) ?></p>
                                <p class="text-sm text-gray-600"><span class="font-medium text-gray-900">ID Desa:</span> #<?= $desa['id'] ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-white rounded-lg shadow-sm border p-6">
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-primary-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary-700 active:bg-primary-900 focus:outline-none focus:border-primary-900 focus:ring ring-primary-300 disabled:opacity-25 transition ease-in-out duration-150">
                                <i class="fas fa-save mr-2"></i> Simpan Perubahan
                            </button>
                            <a href="desa.php" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar
                            </a>
                            <a href="desa-view.php?id=<?= $desa['id'] ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                <i class="fas fa-eye mr-2"></i> Lihat Detail
                            </a>
                            <?php if (AuthStatic::hasRole(['admin'])): ?>
                            <button type="button" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring ring-red-300 disabled:opacity-25 transition ease-in-out duration-150" onclick="confirmDelete()">
                                <i class="fas fa-trash mr-2"></i> Hapus Desa
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <!-- JavaScript -->
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
    <script>
        // Mapbox configuration
        mapboxgl.accessToken = 'pk.eyJ1IjoibWl6d2FyIiwiYSI6ImNrYzdrZmdoYzBsemsycXBoODdkeXYwdXoifQ.Y0sY9ZaWB4sBWsoYx0NLpw';
        let map;
        let marker;
        
        // Auto format nomor telepon
        document.getElementById('no_telepon').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.startsWith('62')) {
                value = '0' + value.substring(2);
            }
            this.value = value;
        });
        
        // Auto capitalize nama desa
        const capitalizeFields = ['nama_desa', 'kecamatan', 'kabupaten', 'provinsi'];
        capitalizeFields.forEach(fieldId => {
            document.getElementById(fieldId).addEventListener('input', function() {
                this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
            });
        });
        
        // Validasi kode pos
        document.getElementById('kode_pos').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 5);
        });
        
        // Get current location
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lng;
                    
                    alert('Lokasi berhasil diambil!');
                }, function(error) {
                    alert('Gagal mengambil lokasi: ' + error.message);
                });
            } else {
                alert('Geolocation tidak didukung oleh browser ini.');
            }
        }
        
        // Show map
        function showMap() {
            const mapContainer = document.getElementById('map-container');
            mapContainer.classList.remove('hidden');
            
            // Get current coordinates
            const currentLat = parseFloat(document.getElementById('latitude').value) || -6.2088;
            const currentLng = parseFloat(document.getElementById('longitude').value) || 106.8456;
            
            // Wait a bit for container to be visible
            setTimeout(() => {
                try {
                    // Initialize map
                    map = new mapboxgl.Map({
                        container: 'map',
                        style: 'mapbox://styles/mapbox/streets-v11',
                        center: [currentLng, currentLat],
                        zoom: 15
                    });
                    
                    // Add load event listener
                    map.on('load', function() {
                        console.log('Map loaded successfully');
                        
                        // Add navigation control
                        map.addControl(new mapboxgl.NavigationControl());
                        
                        // Add existing marker if coordinates exist
                        if (document.getElementById('latitude').value && document.getElementById('longitude').value) {
                            marker = new mapboxgl.Marker({
                                color: '#ef4444',
                                draggable: true
                            })
                            .setLngLat([currentLng, currentLat])
                            .addTo(map);
                            
                            // Update coordinates when marker is dragged
                            marker.on('dragend', function() {
                                const lngLat = marker.getLngLat();
                                document.getElementById('latitude').value = lngLat.lat.toFixed(6);
                                document.getElementById('longitude').value = lngLat.lng.toFixed(6);
                            });
                        }
                        
                        // Add click event to map
                        map.on('click', function(e) {
                            const lngLat = e.lngLat;
                            
                            // Remove existing marker
                            if (marker) {
                                marker.remove();
                            }
                            
                            // Add new marker
                            marker = new mapboxgl.Marker({
                                color: '#ef4444',
                                draggable: true
                            })
                            .setLngLat([lngLat.lng, lngLat.lat])
                            .addTo(map);
                            
                            // Update input fields
                            document.getElementById('latitude').value = lngLat.lat.toFixed(6);
                            document.getElementById('longitude').value = lngLat.lng.toFixed(6);
                            
                            // Update coordinates when marker is dragged
                            marker.on('dragend', function() {
                                const newLngLat = marker.getLngLat();
                                document.getElementById('latitude').value = newLngLat.lat.toFixed(6);
                                document.getElementById('longitude').value = newLngLat.lng.toFixed(6);
                            });
                        });
                    });
                    
                    // Add error event listener
                    map.on('error', function(e) {
                        console.error('Map error:', e);
                        alert('Error loading map: ' + e.error.message);
                    });
                } catch (error) {
                    console.error('Error initializing map:', error);
                    alert('Error initializing map: ' + error.message);
                }
            }, 100);
        }
        
        // Hide map
        function hideMap() {
            const mapContainer = document.getElementById('map-container');
            mapContainer.classList.add('hidden');
            
            if (map) {
                map.remove();
                map = null;
            }
        }
        
        // Update map when coordinates are manually entered
        document.getElementById('latitude').addEventListener('input', updateMapFromCoordinates);
        document.getElementById('longitude').addEventListener('input', updateMapFromCoordinates);
        
        function updateMapFromCoordinates() {
            const lat = parseFloat(document.getElementById('latitude').value);
            const lng = parseFloat(document.getElementById('longitude').value);
            
            if (map && !isNaN(lat) && !isNaN(lng)) {
                // Update map center
                map.setCenter([lng, lat]);
                
                // Remove existing marker
                if (marker) {
                    marker.remove();
                }
                
                // Add new marker
                marker = new mapboxgl.Marker({
                    color: '#ef4444',
                    draggable: true
                })
                .setLngLat([lng, lat])
                .addTo(map);
                
                // Update coordinates when marker is dragged
                marker.on('dragend', function() {
                    const newLngLat = marker.getLngLat();
                    document.getElementById('latitude').value = newLngLat.lat.toFixed(6);
                    document.getElementById('longitude').value = newLngLat.lng.toFixed(6);
                });
            }
        }
        
        // Konfirmasi hapus
        function confirmDelete() {
            if (confirm('Apakah Anda yakin ingin menghapus desa ini?\n\nPerhatian: Data transaksi yang terkait akan tetap ada, namun desa akan dinonaktifkan.')) {
                window.location.href = 'desa-delete.php?id=<?= $desa['id'] ?>';
            }
        }
    </script>
</div>

<?php
include 'layouts/footer.php';
?>
