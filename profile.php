<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$current_user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validasi input
    if (empty($nama_lengkap)) {
        $error = 'Nama lengkap harus diisi';
    } elseif (empty($email)) {
        $error = 'Email harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } else {
        // Cek apakah email sudah digunakan user lain
        $existing_user = $db->selectOne(
            "SELECT id FROM users WHERE email = ? AND id != ?",
            [$email, $current_user['id']]
        );
        
        if ($existing_user) {
            $error = 'Email sudah digunakan oleh user lain';
        } else {
            $update_data = [
                'nama_lengkap' => $nama_lengkap,
                'email' => $email,
                'no_hp' => $no_hp
            ];
            
            // Handle password change
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $error = 'Password saat ini harus diisi untuk mengubah password';
                } elseif (!password_verify($current_password, $current_user['password'])) {
                    $error = 'Password saat ini salah';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Password baru minimal 6 karakter';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Konfirmasi password tidak cocok';
                } else {
                    $update_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
                }
            }
            
            // Handle file upload
            if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'img/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_info = pathinfo($_FILES['foto_profil']['name']);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (!in_array(strtolower($file_info['extension']), $allowed_extensions)) {
                    $error = 'Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF';
                } elseif ($_FILES['foto_profil']['size'] > 2 * 1024 * 1024) { // 2MB
                    $error = 'Ukuran file terlalu besar. Maksimal 2MB';
                } else {
                    $filename = 'profile_' . $current_user['id'] . '_' . time() . '.' . $file_info['extension'];
                    $upload_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_path)) {
                        // Hapus foto lama jika ada
                        if (!empty($current_user['foto_profil']) && file_exists($current_user['foto_profil'])) {
                            unlink($current_user['foto_profil']);
                        }
                        $update_data['foto_profil'] = $upload_path;
                    } else {
                        $error = 'Gagal mengupload foto profil';
                    }
                }
            }
            
            if (empty($error)) {
                try {
                    $set_clause = [];
                    $params = [];
                    
                    foreach ($update_data as $key => $value) {
                        $set_clause[] = "$key = ?";
                        $params[] = $value;
                    }
                    
                    $params[] = $current_user['id'];
                    
                    $query = "UPDATE users SET " . implode(', ', $set_clause) . " WHERE id = ?";
                    $db->execute($query, $params);
                    
                    // Update session data
                    $_SESSION['user'] = $db->selectOne("SELECT * FROM users WHERE id = ?", [$current_user['id']]);
                    
                    $success = 'Profil berhasil diperbarui';
                    $current_user = $_SESSION['user'];
                } catch (Exception $e) {
                    $error = 'Gagal memperbarui profil: ' . $e->getMessage();
                }
            }
        }
    }
}

$page_title = 'Edit Profil';
require_once 'layouts/header.php';
?>

<div class="flex h-screen bg-gray-50">

    
    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Top Navigation -->
        <div class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Edit Profil</h1>
                    <p class="text-sm text-gray-600 mt-1">Kelola informasi profil dan keamanan akun Anda</p>
                </div>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-1 overflow-auto p-6">
            <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="max-w-4xl mx-auto">
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Profile Information -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Informasi Profil</h3>
                            <p class="text-sm text-gray-600 mt-1">Perbarui informasi profil dan foto Anda</p>
                        </div>
                        
                        <div class="p-6 space-y-6">
                            <!-- Profile Photo -->
                            <div class="flex items-center space-x-6">
                                <div class="shrink-0">
                                    <?php if (!empty($current_user['foto_profil']) && file_exists($current_user['foto_profil'])): ?>
                                        <img class="h-20 w-20 rounded-full object-cover" src="<?php echo htmlspecialchars($current_user['foto_profil']); ?>?v=<?php echo time(); ?>" alt="Foto Profil">
                                    <?php else: ?>
                                        <div class="h-20 w-20 rounded-full bg-gray-300 flex items-center justify-center">
                                            <i class="fas fa-user text-gray-600 text-2xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <label for="foto_profil" class="block text-sm font-medium text-gray-700 mb-2">Foto Profil</label>
                                    <input type="file" id="foto_profil" name="foto_profil" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    <p class="text-xs text-gray-500 mt-1">JPG, JPEG, PNG atau GIF. Maksimal 2MB.</p>
                                </div>
                            </div>
                            
                            <!-- Form Fields -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="nama_lengkap" class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap *</label>
                                    <input type="text" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($current_user['nama_lengkap']); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($current_user['email']); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                    <input type="text" id="username" value="<?php echo htmlspecialchars($current_user['username']); ?>" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                                    <p class="text-xs text-gray-500 mt-1">Username tidak dapat diubah</p>
                                </div>
                                
                                <div>
                                    <label for="no_hp" class="block text-sm font-medium text-gray-700 mb-2">No. HP</label>
                                    <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($current_user['no_hp'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                                    <input type="text" id="role" value="<?php echo ucfirst($current_user['role']); ?>" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500">
                                    <p class="text-xs text-gray-500 mt-1">Role tidak dapat diubah</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Ubah Password</h3>
                            <p class="text-sm text-gray-600 mt-1">Kosongkan jika tidak ingin mengubah password</p>
                        </div>
                        
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">Password Saat Ini</label>
                                    <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">Password Baru</label>
                                    <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <p class="text-xs text-gray-500 mt-1">Minimal 6 karakter</p>
                                </div>
                                
                                <div>
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="flex justify-end space-x-4">
                        <a href="index.php" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Batal
                        </a>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-save mr-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Preview image before upload
document.getElementById('foto_profil').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.querySelector('img');
            if (img) {
                img.src = e.target.result;
            } else {
                // Create new img element if doesn't exist
                const container = document.querySelector('.shrink-0');
                container.innerHTML = `<img class="h-20 w-20 rounded-full object-cover" src="${e.target.result}" alt="Foto Profil">`;
            }
        };
        reader.readAsDataURL(file);
    }
});

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Password tidak cocok');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php require_once 'layouts/footer.php'; ?>