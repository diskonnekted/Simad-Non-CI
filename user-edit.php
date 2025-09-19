<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Cek autentikasi dan role
if (!AuthStatic::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!AuthStatic::hasRole(['admin'])) {
    header('Location: index.php?error=access_denied');
    exit;
}

$current_user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$user_id = intval($_GET['id'] ?? 0);

if (!$user_id) {
    header('Location: user.php?error=invalid_id');
    exit;
}

// Ambil data user
$user_data = $db->select(
    "SELECT * FROM users WHERE id = ?",
    [$user_id]
);

if (empty($user_data)) {
    header('Location: user.php?error=not_found');
    exit;
}

$user = $user_data[0];
$error = '';
$success = '';
$form_data = [
    'nama' => $user['nama_lengkap'],
    'email' => $user['email'],
    'username' => $user['username'],
    'role' => $user['role'],
    'status' => $user['status'],
    'no_hp' => $user['no_hp']
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'nama' => trim($_POST['nama'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'role' => $_POST['role'] ?? '',
        'status' => $_POST['status'] ?? '',
        'no_hp' => trim($_POST['no_hp'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    // Validasi input
    $errors = [];
    
    if (empty($form_data['nama'])) {
        $errors[] = 'Nama lengkap harus diisi.';
    } elseif (strlen($form_data['nama']) < 2) {
        $errors[] = 'Nama lengkap minimal 2 karakter.';
    }
    
    if (empty($form_data['email'])) {
        $errors[] = 'Email harus diisi.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    
    if (empty($form_data['username'])) {
        $errors[] = 'Username harus diisi.';
    } elseif (strlen($form_data['username']) < 3) {
        $errors[] = 'Username minimal 3 karakter.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $form_data['username'])) {
        $errors[] = 'Username hanya boleh mengandung huruf, angka, dan underscore.';
    }
    
    if (empty($form_data['role'])) {
        $errors[] = 'Role harus dipilih.';
    } elseif (!in_array($form_data['role'], ['admin', 'akunting', 'supervisor', 'teknisi', 'programmer'])) {
        $errors[] = 'Role tidak valid.';
    }
    
    if (!in_array($form_data['status'], ['aktif', 'tidak_aktif'])) {
        $errors[] = 'Status tidak valid.';
    }
    
    // Validasi nomor HP (opsional)
    if (!empty($form_data['no_hp'])) {
        if (!preg_match('/^[0-9+\-\s()]+$/', $form_data['no_hp'])) {
            $errors[] = 'Format nomor HP tidak valid.';
        } elseif (strlen($form_data['no_hp']) < 10 || strlen($form_data['no_hp']) > 20) {
            $errors[] = 'Nomor HP harus antara 10-20 karakter.';
        }
    }
    
    // Handle foto profil upload
    $foto_profil_name = $user['foto_profil']; // Keep existing photo by default
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/users/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_tmp = $_FILES['foto_profil']['tmp_name'];
        $file_size = $_FILES['foto_profil']['size'];
        $file_type = $_FILES['foto_profil']['type'];
        $file_name = $_FILES['foto_profil']['name'];
        
        // Validate file size (2MB max)
         if ($file_size > 2 * 1024 * 1024) {
             $error = 'Ukuran file foto terlalu besar. Maksimal 2MB.';
         }
         
         // Validate file type
         $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
         if (!in_array($file_type, $allowed_types)) {
             $error = 'Tipe file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
         }
         
         if (!$error) {
            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $foto_profil_name = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $foto_profil_name;
            
            // Move uploaded file
             if (!move_uploaded_file($file_tmp, $upload_path)) {
                 $error = 'Gagal mengupload foto profil.';
                 $foto_profil_name = $user['foto_profil']; // Revert to original
            } else {
                // Delete old photo if exists and different from new one
                if ($user['foto_profil'] && $user['foto_profil'] !== $foto_profil_name) {
                    $old_photo_path = $upload_dir . $user['foto_profil'];
                    if (file_exists($old_photo_path)) {
                        unlink($old_photo_path);
                    }
                }
            }
        }
    }
    
    // Validasi password jika diisi
    if (!empty($form_data['password'])) {
        if (strlen($form_data['password']) < 6) {
            $errors[] = 'Password minimal 6 karakter.';
        }
        
        if ($form_data['password'] !== $form_data['confirm_password']) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        }
    }
    
    // Cek duplikasi email (kecuali email sendiri)
    if (empty($errors)) {
        $existing_email = $db->select(
            "SELECT id FROM users WHERE email = ? AND id != ?",
            [$form_data['email'], $user_id]
        );
        
        if (!empty($existing_email)) {
            $errors[] = 'Email sudah digunakan oleh user lain.';
        }
    }
    
    // Cek duplikasi username (kecuali username sendiri)
    if (empty($errors)) {
        $existing_username = $db->select(
            "SELECT id FROM users WHERE username = ? AND id != ?",
            [$form_data['username'], $user_id]
        );
        
        if (!empty($existing_username)) {
            $errors[] = 'Username sudah digunakan oleh user lain.';
        }
    }
    
    // Tidak bisa mengubah status diri sendiri menjadi tidak aktif
    if ($user_id == $current_user['id'] && $form_data['status'] === 'tidak_aktif') {
        $errors[] = 'Anda tidak dapat menonaktifkan akun Anda sendiri.';
    }
    
    if (empty($errors)) {
        try {
            // Prepare update query
            if (!empty($form_data['password'])) {
                // Update dengan password baru
                $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
                $db->execute(
                    "UPDATE users SET nama_lengkap = ?, email = ?, username = ?, password = ?, role = ?, status = ?, no_hp = ?, foto_profil = ?, updated_at = NOW() WHERE id = ?",
                    [
                        $form_data['nama'],
                        $form_data['email'],
                        $form_data['username'],
                        $hashed_password,
                        $form_data['role'],
                        $form_data['status'],
                        $form_data['no_hp'],
                        $foto_profil_name,
                        $user_id
                    ]
                );
            } else {
                // Update tanpa mengubah password
                $db->execute(
                    "UPDATE users SET nama_lengkap = ?, email = ?, username = ?, role = ?, status = ?, no_hp = ?, foto_profil = ?, updated_at = NOW() WHERE id = ?",
                    [
                        $form_data['nama'],
                        $form_data['email'],
                        $form_data['username'],
                        $form_data['role'],
                        $form_data['status'],
                        $form_data['no_hp'],
                        $foto_profil_name,
                        $user_id
                    ]
                );
            }
            
            header('Location: user.php?success=updated');
            exit;
        } catch (Exception $e) {
            $error = 'Gagal memperbarui data user. Silakan coba lagi.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Helper functions
function getRoleBadge($role) {
    $badges = [
        'admin' => 'danger',
        'akunting' => 'success',
        'supervisor' => 'warning',
        'teknisi' => 'info'
    ];
    return $badges[$role] ?? 'secondary';
}

function getRoleText($role) {
    $roles = [
        'admin' => 'Administrator',
        'akunting' => 'Akunting',
        'supervisor' => 'Supervisor',
        'teknisi' => 'Teknisi'
    ];
    return $roles[$role] ?? ucfirst($role);
}

$page_title = 'Edit User - ' . $user['nama_lengkap'];
require_once 'layouts/header.php';
?>

<div class="container-fluid px-4">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 mb-1">Edit User</h1>
            <p class="text-gray-600">Perbarui informasi pengguna sistem</p>
        </div>
        <div class="flex gap-2">
            <a href="user-view.php?id=<?php echo $user['id']; ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-eye mr-2"></i>Lihat Detail
            </a>
            <a href="user.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                <i class="fas fa-arrow-left mr-2"></i>Kembali
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 relative" role="alert">
        <span class="block sm:inline"><?php echo $error; ?></span>
        <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
            <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <title>Close</title>
                <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
            </svg>
        </span>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Form Section -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h6 class="text-lg font-semibold text-gray-800">Informasi User</h6>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $user['role'] === 'admin' ? 'bg-red-100 text-red-800' : ($user['role'] === 'akunting' ? 'bg-green-100 text-green-800' : ($user['role'] === 'supervisor' ? 'bg-yellow-100 text-yellow-800' : ($user['role'] === 'teknisi' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'))); ?>">
                        <?php echo getRoleText($user['role']); ?>
                    </span>
                </div>
                <div class="p-6">
                    <form method="POST" id="userForm" enctype="multipart/form-data">
                        <!-- Foto Profil -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Foto Profil</label>
                            <div class="flex items-center space-x-6">
                                <div class="shrink-0">
                                    <img id="preview-foto" class="h-16 w-16 object-cover rounded-full" 
                                         src="<?php echo $user['foto_profil'] ? 'uploads/users/' . htmlspecialchars($user['foto_profil']) : 'img/clasnet.png'; ?>" 
                                         alt="Foto profil">
                                </div>
                                <label class="block">
                                    <span class="sr-only">Pilih foto profil</span>
                                    <input type="file" class="block w-full text-sm text-slate-500
                                        file:mr-4 file:py-2 file:px-4
                                        file:rounded-full file:border-0
                                        file:text-sm file:font-semibold
                                        file:bg-blue-50 file:text-blue-700
                                        hover:file:bg-blue-100" 
                                           id="foto_profil" name="foto_profil" accept="image/*" onchange="previewImage(this)">
                                    <p class="mt-1 text-sm text-gray-500">PNG, JPG, JPEG hingga 2MB</p>
                                </label>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="nama" class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="nama" name="nama" 
                                       value="<?php echo htmlspecialchars($form_data['nama']); ?>" 
                                       placeholder="Masukkan nama lengkap" required>
                                <p class="mt-1 text-sm text-gray-500">Nama lengkap pengguna (minimal 2 karakter)</p>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
                                <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                       placeholder="user@example.com" required>
                                <p class="mt-1 text-sm text-gray-500">Email harus unik dan valid</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label for="no_hp" class="block text-sm font-medium text-gray-700 mb-2">Nomor HP / DANA</label>
                                <input type="tel" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="no_hp" name="no_hp" 
                                       value="<?php echo htmlspecialchars($form_data['no_hp'] ?? ''); ?>" 
                                       placeholder="08xxxxxxxxxx">
                                <p class="mt-1 text-sm text-gray-500">Nomor HP yang juga digunakan untuk DANA (10-20 karakter)</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username <span class="text-red-500">*</span></label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($form_data['username']); ?>" 
                                       placeholder="username" required>
                                <p class="mt-1 text-sm text-gray-500">Username harus unik (minimal 3 karakter, hanya huruf, angka, dan underscore)</p>
                            </div>
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role <span class="text-red-500">*</span></label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="role" name="role" required>
                                    <option value="">Pilih Role</option>
                                    <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                <option value="akunting" <?php echo $form_data['role'] === 'akunting' ? 'selected' : ''; ?>>Akunting</option>
                                <option value="supervisor" <?php echo $form_data['role'] === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="teknisi" <?php echo $form_data['role'] === 'teknisi' ? 'selected' : ''; ?>>Teknisi</option>
                                <option value="programmer" <?php echo $form_data['role'] === 'programmer' ? 'selected' : ''; ?>>Programmer</option>
                                </select>
                                <p class="mt-1 text-sm text-gray-500">Tentukan hak akses pengguna</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status <span class="text-red-500">*</span></label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="status" name="status" required 
                                        <?php echo $user_id == $current_user['id'] ? 'disabled' : ''; ?>>
                                    <option value="aktif" <?php echo $form_data['status'] === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="tidak_aktif" <?php echo $form_data['status'] === 'tidak_aktif' ? 'selected' : ''; ?>>Tidak Aktif</option>
                                </select>
                                <?php if ($user_id == $current_user['id']): ?>
                                <input type="hidden" name="status" value="<?php echo $form_data['status']; ?>">
                                <p class="mt-1 text-sm text-yellow-600">Anda tidak dapat mengubah status akun Anda sendiri</p>
                                <?php else: ?>
                                <p class="mt-1 text-sm text-gray-500">Status pengguna dalam sistem</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="border-t border-gray-200 my-6"></div>
                        
                        <h6 class="text-blue-600 text-lg font-medium mb-3">Ubah Password (Opsional)</h6>
                        <p class="text-gray-500 text-sm mb-4">Kosongkan jika tidak ingin mengubah password</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password Baru</label>
                                <div class="relative">
                                    <input type="password" class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="password" name="password" 
                                           placeholder="Masukkan password baru">
                                    <button class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Password minimal 6 karakter (kosongkan jika tidak ingin mengubah)</p>
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password Baru</label>
                                <div class="relative">
                                    <input type="password" class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" id="confirm_password" name="confirm_password" 
                                           placeholder="Ulangi password baru">
                                    <button class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" type="button" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Harus sama dengan password baru</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-2">
                            <a href="user.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                <i class="fas fa-times mr-2"></i>Batal
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i>Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Info Section -->
        <div class="lg:col-span-1">
            <!-- Current User Info -->
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h6 class="text-lg font-semibold text-gray-800">Informasi Saat Ini</h6>
                </div>
                <div class="p-6">
                    <div class="text-center mb-6">
                        <div class="w-20 h-20 rounded-full overflow-hidden bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center text-white mb-3 mx-auto">
                            <?php if ($user['foto_profil']): ?>
                                <img src="uploads/users/<?php echo htmlspecialchars($user['foto_profil']); ?>" alt="Foto profil" class="w-full h-full object-cover">
                            <?php else: ?>
                                <img src="img/clasnet.png" alt="Logo Clasnet" class="w-12 h-12 object-contain">
                            <?php endif; ?>
                        </div>
                        <h6 class="text-lg font-medium text-gray-900 mb-1"><?php echo htmlspecialchars($user['nama_lengkap']); ?></h6>
                        <p class="text-gray-500 text-sm mb-2">@<?php echo htmlspecialchars($user['username']); ?></p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $user['role'] === 'admin' ? 'bg-red-100 text-red-800' : ($user['role'] === 'akunting' ? 'bg-green-100 text-green-800' : ($user['role'] === 'supervisor' ? 'bg-yellow-100 text-yellow-800' : ($user['role'] === 'teknisi' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'))); ?>">
                            <?php echo getRoleText($user['role']); ?>
                        </span>
                    </div>
                    
                    <div class="space-y-3">
                        <div>
                            <span class="text-sm text-gray-500">Email:</span>
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        
                        <div>
                            <span class="text-sm text-gray-500">Nomor HP / DANA:</span>
                            <p class="text-gray-900 font-medium"><?php echo $user['no_hp'] ? htmlspecialchars($user['no_hp']) : 'Belum diisi'; ?></p>
                        </div>
                        
                        <div>
                            <span class="text-sm text-gray-500">Bergabung:</span>
                            <p class="text-gray-900 font-medium"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
                        </div>
                        
                        <div>
                            <span class="text-sm text-gray-500">Login Terakhir:</span>
                            <p class="text-gray-900 font-medium"><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Belum pernah'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Role Information -->
            <div class="bg-white rounded-lg shadow-md mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h6 class="text-lg font-semibold text-gray-800">Informasi Role</h6>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <div class="flex items-center mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-2">Admin</span>
                                <span class="font-medium text-gray-900">Administrator</span>
                            </div>
                            <p class="text-gray-500 text-sm">Akses penuh ke semua fitur sistem, termasuk manajemen user dan pengaturan.</p>
                        </div>
                        
                        <div>
                            <div class="flex items-center mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-2">Akunting</span>
                                <span class="font-medium text-gray-900">Akunting</span>
                            </div>
                            <p class="text-gray-500 text-sm">Akses ke fitur keuangan, laporan, dan manajemen biaya operasional.</p>
                        </div>
                        
                        <div>
                            <div class="flex items-center mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-2">Supervisor</span>
                                <span class="font-medium text-gray-900">Supervisor</span>
                            </div>
                            <p class="text-gray-500 text-sm">Akses ke manajemen jadwal, peralatan, dan supervisi operasional.</p>
                        </div>
                        
                        <div>
                            <div class="flex items-center mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-2">Teknisi</span>
                                <span class="font-medium text-gray-900">Teknisi</span>
                            </div>
                            <p class="text-gray-500 text-sm">Akses terbatas ke jadwal kerja dan peralatan yang ditugaskan.</p>
                        </div>
                        
                        <div>
                            <div class="flex items-center mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 mr-2">Programmer</span>
                                <span class="font-medium text-gray-900">Programmer</span>
                            </div>
                            <p class="text-gray-500 text-sm">Akses terbatas sama dengan teknisi - jadwal kerja dan peralatan.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tips -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h6 class="text-lg font-semibold text-gray-800">Tips</h6>
                </div>
                <div class="p-6">
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mr-3 mt-0.5"></i>
                            <span class="text-gray-700">Kosongkan password jika tidak ingin mengubah</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mr-3 mt-0.5"></i>
                            <span class="text-gray-700">Username tidak dapat sama dengan user lain</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mr-3 mt-0.5"></i>
                            <span class="text-gray-700">Perubahan role akan berlaku setelah user login ulang</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const password = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (password.type === 'password') {
        password.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        password.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}); 

// Preview image function
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('preview-foto').src = e.target.result;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// File size validation
document.getElementById('foto_profil').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        // Check file size (2MB = 2 * 1024 * 1024 bytes)
        if (file.size > 2 * 1024 * 1024) {
            alert('Ukuran file terlalu besar! Maksimal 2MB.');
            this.value = '';
            return;
        }
        
        // Check file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
            alert('Tipe file tidak didukung! Gunakan JPG, JPEG, atau PNG.');
            this.value = '';
            return;
        }
    }
});

document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
    const confirmPassword = document.getElementById('confirm_password');
    const icon = this.querySelector('i');
    
    if (confirmPassword.type === 'password') {
        confirmPassword.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        confirmPassword.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});

// Real-time password confirmation check
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && password !== confirmPassword) {
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    } else if (confirmPassword && password === confirmPassword) {
        this.classList.add('is-valid');
        this.classList.remove('is-invalid');
    } else {
        this.classList.remove('is-invalid', 'is-valid');
    }
});

// Username validation
document.getElementById('username').addEventListener('input', function() {
    const username = this.value;
    const regex = /^[a-zA-Z0-9_]+$/;
    
    if (username && !regex.test(username)) {
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    } else if (username && username.length >= 3) {
        this.classList.add('is-valid');
        this.classList.remove('is-invalid');
    } else {
        this.classList.remove('is-invalid', 'is-valid');
    }
});

// Email validation
document.getElementById('email').addEventListener('input', function() {
    const email = this.value;
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !regex.test(email)) {
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    } else if (email && regex.test(email)) {
        this.classList.add('is-valid');
        this.classList.remove('is-invalid');
    } else {
        this.classList.remove('is-invalid', 'is-valid');
    }
});

// Form validation before submit
document.getElementById('userForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Jika password diisi, harus valid
    if (password) {
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Password dan konfirmasi password tidak cocok!');
            return false;
        }
        
        if (password.length < 6) {
            e.preventDefault();
            alert('Password minimal 6 karakter!');
            return false;
        }
    }
});
</script>

<?php require_once 'layouts/footer.php'; ?>