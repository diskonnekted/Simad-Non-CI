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

// Hanya admin yang bisa reset PIN
if (!AuthStatic::hasRole(['admin'])) {
    header('Location: desa.php?error=access_denied');
    exit;
}

$desa_id = $_GET['id'] ?? 0;
$success = false;
$error = '';

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

// Proses reset PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pin = $_POST['new_pin'] ?? '';
    $confirm_pin = $_POST['confirm_pin'] ?? '';
    
    // Validasi input
    if (empty($new_pin)) {
        $error = 'PIN baru harus diisi';
    } elseif (strlen($new_pin) !== 6) {
        $error = 'PIN harus 6 digit';
    } elseif (!ctype_digit($new_pin)) {
        $error = 'PIN hanya boleh berisi angka';
    } elseif ($new_pin !== $confirm_pin) {
        $error = 'Konfirmasi PIN tidak cocok';
    } else {
        try {
            // Hash PIN baru
            $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);
            
            // Update PIN di database
            $result = $db->update(
                'desa',
                ['pin' => $hashed_pin, 'updated_at' => date('Y-m-d H:i:s')],
                ['id' => $desa_id]
            );
            
            if ($result) {
                $success = true;
                
                // Log aktivitas
                $db->insert('activity_logs', [
                    'user_id' => $user['id'],
                    'activity_type' => 'reset_pin_desa',
                    'description' => "Reset PIN untuk desa {$desa['nama_desa']}, {$desa['kecamatan']}",
                    'target_table' => 'desa',
                    'target_id' => $desa_id,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
            } else {
                $error = 'Gagal mengupdate PIN';
            }
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<?php include 'layouts/header.php'; ?>

<!-- Main Content -->
<div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Breadcrumb -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="index.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="desa.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">Manajemen Desa</a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="desa-view.php?id=<?= $desa['id'] ?>" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">Detail Desa</a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Reset PIN</span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Reset PIN Desa</h1>
                        <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($desa['nama_desa']) ?>, <?= htmlspecialchars($desa['kecamatan']) ?></p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="desa-view.php?id=<?= $desa['id'] ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
        <!-- Success Message -->
        <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">PIN berhasil direset!</h3>
                    <div class="mt-2 text-sm text-green-700">
                        <p>PIN untuk desa <?= htmlspecialchars($desa['nama_desa']) ?> telah berhasil direset. Desa dapat menggunakan PIN baru untuk login ke portal klien.</p>
                    </div>
                    <div class="mt-4">
                        <div class="-mx-2 -my-1.5 flex">
                            <a href="desa-view.php?id=<?= $desa['id'] ?>" class="bg-green-50 px-2 py-1.5 rounded-md text-sm font-medium text-green-800 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-green-50 focus:ring-green-600">
                                Kembali ke Detail Desa
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <!-- Error Message -->
        <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Terjadi kesalahan!</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form Reset PIN -->
        <?php if (!$success): ?>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Reset PIN Akses Portal
                </h2>
                <p class="text-sm text-gray-600 mt-1">Masukkan PIN baru untuk desa ini. PIN akan digunakan untuk login ke portal klien.</p>
            </div>
            <div class="p-6">
                <!-- Warning -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Peringatan Keamanan</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    <li>PIN lama akan tidak dapat digunakan lagi setelah reset</li>
                                    <li>Pastikan untuk memberitahu desa tentang PIN baru</li>
                                    <li>PIN baru harus dirahasiakan dan hanya diketahui oleh pihak yang berwenang</li>
                                    <li>Aktivitas reset PIN akan tercatat dalam log sistem</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="new_pin" class="block text-sm font-medium text-gray-700 mb-2">
                                PIN Baru <span class="text-red-500">*</span>
                            </label>
                            <input type="password" 
                                   id="new_pin" 
                                   name="new_pin" 
                                   maxlength="6" 
                                   pattern="[0-9]{6}" 
                                   placeholder="Masukkan 6 digit angka"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                   required>
                            <p class="text-xs text-gray-500 mt-1">PIN harus 6 digit angka</p>
                        </div>
                        
                        <div>
                            <label for="confirm_pin" class="block text-sm font-medium text-gray-700 mb-2">
                                Konfirmasi PIN <span class="text-red-500">*</span>
                            </label>
                            <input type="password" 
                                   id="confirm_pin" 
                                   name="confirm_pin" 
                                   maxlength="6" 
                                   pattern="[0-9]{6}" 
                                   placeholder="Ulangi PIN baru"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                   required>
                            <p class="text-xs text-gray-500 mt-1">Ulangi PIN untuk konfirmasi</p>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200">
                        <a href="desa-view.php?id=<?= $desa['id'] ?>" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Batal
                        </a>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            Reset PIN
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Validasi PIN real-time
document.addEventListener('DOMContentLoaded', function() {
    const newPinInput = document.getElementById('new_pin');
    const confirmPinInput = document.getElementById('confirm_pin');
    
    // Hanya izinkan angka
    [newPinInput, confirmPinInput].forEach(input => {
        input.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
        });
    });
    
    // Validasi konfirmasi PIN
    confirmPinInput.addEventListener('input', function() {
        if (this.value && newPinInput.value && this.value !== newPinInput.value) {
            this.setCustomValidity('PIN tidak cocok');
        } else {
            this.setCustomValidity('');
        }
    });
    
    newPinInput.addEventListener('input', function() {
        if (confirmPinInput.value && this.value !== confirmPinInput.value) {
            confirmPinInput.setCustomValidity('PIN tidak cocok');
        } else {
            confirmPinInput.setCustomValidity('');
        }
    });
});
</script>

<?php include 'layouts/footer.php'; ?>