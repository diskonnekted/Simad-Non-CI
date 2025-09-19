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

$error = '';
$success = '';

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
            // Cek duplikasi nama desa
            $existing = $db->select(
                "SELECT id FROM desa WHERE nama_desa = ? AND kecamatan = ? AND kabupaten = ?", 
                [$nama_desa, $kecamatan, $kabupaten]
            );
            
            if (!empty($existing)) {
                $error = 'Desa dengan nama yang sama sudah ada di kecamatan dan kabupaten tersebut';
            } else {
                // Insert data desa
                $query = "
                    INSERT INTO desa (
                        nama_desa, kecamatan, kabupaten, provinsi, kode_pos, alamat,
                        nama_kepala_desa, jabatan_kepala_desa, no_hp_kepala_desa, email_desa, catatan_khusus
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                
                $params = [
                    $nama_desa, $kecamatan, $kabupaten, $provinsi, $kode_pos, $alamat,
                    $kontak_person, $jabatan, $no_telepon, $email ?: null, $catatan
                ];
                
                $db->execute($query, $params);
                
                $success = 'Desa berhasil ditambahkan';
                
                // Reset form
                $_POST = [];
            }
        } catch (Exception $e) {
            $error = 'Gagal menambahkan desa: ' . $e->getMessage();
        }
    }
}
?>
<?php include 'layouts/header.php'; ?>

    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Tambah Desa Baru</h1>
                    </div>
                    <div>
                        <a href="desa.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Kembali
                        </a>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                <strong>Sukses!</strong> <?= htmlspecialchars($success) ?>
                            </p>
                        </div>
                        <div class="ml-auto pl-3">
                            <a href="desa.php" class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Lihat Daftar Desa
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">
                                <strong>Error!</strong> <?= htmlspecialchars($error) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-6">
                    <form method="POST" class="space-y-8">
                        <!-- Informasi Desa Section -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-6 pb-3 border-b border-gray-200">
                                Informasi Desa
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Nama Desa <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="nama_desa" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($_POST['nama_desa'] ?? '') ?>" 
                                           placeholder="Contoh: Sukamaju">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Kecamatan <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="kecamatan" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($_POST['kecamatan'] ?? '') ?>" 
                                           placeholder="Contoh: Cianjur">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Kabupaten <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="kabupaten" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($_POST['kabupaten'] ?? '') ?>" 
                                           placeholder="Contoh: Cianjur">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Provinsi
                                    </label>
                                    <input type="text" name="provinsi"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($_POST['provinsi'] ?? 'Jawa Barat') ?>" 
                                           placeholder="Contoh: Jawa Barat">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Kode Pos
                                    </label>
                                    <input type="text" name="kode_pos" maxlength="5"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($_POST['kode_pos'] ?? '') ?>" 
                                           placeholder="43xxx">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Alamat Lengkap
                                    </label>
                                    <textarea name="alamat_lengkap" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                              placeholder="Alamat lengkap kantor desa atau balai desa"><?= htmlspecialchars($_POST['alamat_lengkap'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Informasi Kontak Person Section -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-6 pb-3 border-b border-gray-200">
                                Informasi Kontak Person
                            </h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Nama Kontak Person <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="kontak_person" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($_POST['kontak_person'] ?? '') ?>" 
                                           placeholder="Nama lengkap kontak person">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Jabatan
                                    </label>
                                    <input type="text" name="jabatan"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($_POST['jabatan'] ?? '') ?>" 
                                           placeholder="Contoh: Kepala Desa, Sekretaris Desa">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Nomor Telepon <span class="text-red-500">*</span>
                                    </label>
                                    <input type="tel" name="no_telepon" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($_POST['no_telepon'] ?? '') ?>" 
                                           placeholder="08xxxxxxxxxx">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Email
                                    </label>
                                    <input type="email" name="email"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                                           placeholder="email@domain.com">
                                </div>
                            </div>
                        </div>

                        <!-- Catatan Tambahan Section -->
                        <div class="bg-gray-50 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-6 pb-3 border-b border-gray-200">
                                Catatan Tambahan
                            </h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Catatan
                                </label>
                                <textarea name="catatan" rows="4"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Catatan khusus tentang desa ini (opsional)"><?= htmlspecialchars($_POST['catatan'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex flex-col sm:flex-row gap-3 pt-6 border-t border-gray-200">
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                Simpan Desa
                            </button>
                            <a href="desa.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Kembali ke Daftar
                            </a>
                            <button type="reset" class="inline-flex items-center px-4 py-2 border border-yellow-300 text-sm font-medium rounded-md text-yellow-700 bg-yellow-50 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Reset Form
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto format nomor telepon
        const phoneInput = document.querySelector('input[name="no_telepon"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.startsWith('62')) {
                    value = '0' + value.substring(2);
                }
                this.value = value;
            });
        }
        
        // Auto capitalize nama desa
        const textInputs = document.querySelectorAll('input[name="nama_desa"], input[name="kecamatan"], input[name="kabupaten"], input[name="provinsi"]');
        textInputs.forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
            });
        });
        
        // Validasi kode pos
        const postalCodeInput = document.querySelector('input[name="kode_pos"]');
        if (postalCodeInput) {
            postalCodeInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').substring(0, 5);
            });
        }
    });
</script>

<?php include 'layouts/footer.php'; ?>
