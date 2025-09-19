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

$user = AuthStatic::getCurrentUser();
/** @var Database $db */
$db = getDatabase();

$error = '';
$success = '';
$form_data = [
    'nama' => '',
    'email' => '',
    'username' => '',
    'role' => '',
    'status' => 'aktif'
];

// Custom head content for this page
$custom_head = '
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'nama' => trim($_POST['nama'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'role' => $_POST['role'] ?? '',
        'status' => $_POST['status'] ?? 'aktif'
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
    
    if (empty($form_data['password'])) {
        $errors[] = 'Password harus diisi.';
    } elseif (strlen($form_data['password']) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }
    
    if ($form_data['password'] !== $form_data['confirm_password']) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }
    
    if (empty($form_data['role'])) {
        $errors[] = 'Role harus dipilih.';
    } elseif (!in_array($form_data['role'], ['admin', 'akunting', 'supervisor', 'teknisi', 'programmer'])) {
        $errors[] = 'Role tidak valid.';
    }
    
    if (!in_array($form_data['status'], ['aktif', 'tidak_aktif'])) {
        $errors[] = 'Status tidak valid.';
    }
    
    // Cek duplikasi email
    if (empty($errors)) {
        $existing_email = $db->select(
            "SELECT id FROM users WHERE email = ?",
            [$form_data['email']]
        );
        
        if (!empty($existing_email)) {
            $errors[] = 'Email sudah digunakan oleh user lain.';
        }
    }
    
    // Cek duplikasi username
    if (empty($errors)) {
        $existing_username = $db->select(
            "SELECT id FROM users WHERE username = ?",
            [$form_data['username']]
        );
        
        if (!empty($existing_username)) {
            $errors[] = 'Username sudah digunakan oleh user lain.';
        }
    }
    
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
            
            // Insert user baru
            $db->execute(
                "INSERT INTO users (nama_lengkap, email, username, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [
                    $form_data['nama'],
                    $form_data['email'],
                    $form_data['username'],
                    $hashed_password,
                    $form_data['role'],
                    $form_data['status']
                ]
            );
            
            header('Location: user.php?success=added');
            exit;
        } catch (Exception $e) {
            $error = 'Gagal menyimpan data user. Silakan coba lagi.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

$page_title = 'Tambah User';
require_once 'layouts/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Tambah User</h1>
            <p class="mt-1 text-sm text-gray-600">Tambahkan pengguna baru ke sistem</p>
        </div>
        <a href="user.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <i class="fas fa-arrow-left mr-2"></i>Kembali
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-red-800"><?php echo $error; ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Form Section -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Informasi User</h3>
                </div>
                <div class="p-6">
                    <form method="POST" id="userForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="nama" class="block text-sm font-medium text-gray-700">Nama Lengkap <span class="text-red-500">*</span></label>
                                <input type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="nama" name="nama" 
                                       value="<?php echo htmlspecialchars($form_data['nama']); ?>" 
                                       placeholder="Masukkan nama lengkap" required>
                                <p class="mt-2 text-sm text-gray-500">Nama lengkap pengguna (minimal 2 karakter)</p>
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                                <input type="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                       placeholder="user@example.com" required>
                                <p class="mt-2 text-sm text-gray-500">Email harus unik dan valid</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label for="username" class="block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                                <input type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($form_data['username']); ?>" 
                                       placeholder="username" required>
                                <p class="mt-2 text-sm text-gray-500">Username harus unik (minimal 3 karakter, hanya huruf, angka, dan underscore)</p>
                            </div>
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                                <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="role" name="role" required>
                                    <option value="">Pilih Role</option>
                                    <option value="admin" <?php echo $form_data['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                    <option value="akunting" <?php echo $form_data['role'] === 'akunting' ? 'selected' : ''; ?>>Akunting</option>
                                    <option value="supervisor" <?php echo $form_data['role'] === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                    <option value="teknisi" <?php echo $form_data['role'] === 'teknisi' ? 'selected' : ''; ?>>Teknisi</option>
                                    <option value="programmer" <?php echo $form_data['role'] === 'programmer' ? 'selected' : ''; ?>>Programmer</option>
                                </select>
                                <p class="mt-2 text-sm text-gray-500">Tentukan hak akses pengguna</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="password" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm pr-10" id="password" name="password" 
                                           placeholder="Masukkan password" required>
                                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p class="mt-2 text-sm text-gray-500">Password minimal 6 karakter</p>
                            </div>
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Konfirmasi Password <span class="text-red-500">*</span></label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="password" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm pr-10" id="confirm_password" name="confirm_password" 
                                           placeholder="Ulangi password" required>
                                    <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600" id="toggleConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <p class="mt-2 text-sm text-gray-500">Harus sama dengan password</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Status <span class="text-red-500">*</span></label>
                                <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" id="status" name="status" required>
                                    <option value="aktif" <?php echo $form_data['status'] === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="tidak_aktif" <?php echo $form_data['status'] === 'tidak_aktif' ? 'selected' : ''; ?>>Tidak Aktif</option>
                                </select>
                                <p class="mt-2 text-sm text-gray-500">Status awal pengguna</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-8">
                            <a href="user.php" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-times mr-2"></i>Batal
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i>Simpan User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Info Section -->
        <div class="lg:col-span-1">
            <!-- Role Information -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Informasi Role</h3>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <div class="flex items-center mb-2">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 mr-3">Admin</span>
                            <span class="font-medium text-gray-900">Administrator</span>
                        </div>
                        <p class="text-sm text-gray-500">Akses penuh ke semua fitur sistem, termasuk manajemen user dan pengaturan.</p>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex items-center mb-2">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 mr-3">Akunting</span>
                            <span class="font-medium text-gray-900">Akunting</span>
                        </div>
                        <p class="text-sm text-gray-500">Akses ke fitur keuangan, laporan, dan manajemen biaya operasional.</p>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex items-center mb-2">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 mr-3">Supervisor</span>
                            <span class="font-medium text-gray-900">Supervisor</span>
                        </div>
                        <p class="text-sm text-gray-500">Akses ke manajemen jadwal, peralatan, dan supervisi operasional.</p>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex items-center mb-2">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-cyan-100 text-cyan-800 mr-3">Teknisi</span>
                            <span class="font-medium text-gray-900">Teknisi</span>
                        </div>
                        <p class="text-sm text-gray-500">Akses terbatas ke jadwal kerja dan peralatan yang ditugaskan.</p>
                    </div>
                    
                    <div class="mb-0">
                        <div class="flex items-center mb-2">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 mr-3">Programmer</span>
                            <span class="font-medium text-gray-900">Programmer</span>
                        </div>
                        <p class="text-sm text-gray-500">Akses terbatas sama dengan teknisi - jadwal kerja dan peralatan.</p>
                    </div>
                </div>
            </div>
            
            <!-- Password Guidelines -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Panduan Password</h3>
                </div>
                <div class="p-6">
                    <ul class="space-y-3">
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <span class="text-sm text-gray-700">Minimal 6 karakter</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <span class="text-sm text-gray-700">Kombinasi huruf dan angka</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <span class="text-sm text-gray-700">Hindari informasi pribadi</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <span class="text-sm text-gray-700">Gunakan karakter khusus untuk keamanan ekstra</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Tips -->
            <div class="bg-white shadow rounded-lg mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Tips</h3>
                </div>
                <div class="p-6">
                    <ul class="space-y-3">
                        <li class="flex items-center">
                            <i class="fas fa-lightbulb text-yellow-500 mr-3"></i>
                            <span class="text-sm text-gray-700">Username tidak dapat diubah setelah dibuat</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-lightbulb text-yellow-500 mr-3"></i>
                            <span class="text-sm text-gray-700">Email digunakan untuk notifikasi sistem</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-lightbulb text-yellow-500 mr-3"></i>
                            <span class="text-sm text-gray-700">User dapat mengubah password sendiri setelah login</span>
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
});
</script>

<?php require_once 'layouts/footer.php'; ?>