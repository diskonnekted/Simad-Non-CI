<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Redirect jika sudah login
if (AuthStatic::isLoggedIn()) {
    // Redirect berdasarkan role
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'programmer') {
            header('Location: dashboard-programmer.php');
            exit;
        } elseif ($_SESSION['role'] === 'akunting') {
            header('Location: dashboard-finance.php');
            exit;
        } else {
            header('Location: dashboard.php');
            exit;
        }
    } else {
        header('Location: dashboard.php');
        exit;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $result = AuthStatic::login($username, $password, $remember);
        
        if ($result['success']) {
            // Redirect berdasarkan role setelah login berhasil
            if (isset($_SESSION['role'])) {
                if ($_SESSION['role'] === 'programmer') {
                    header('Location: dashboard-programmer.php');
                    exit;
                } elseif ($_SESSION['role'] === 'akunting') {
                    header('Location: dashboard-finance.php');
                    exit;
                } else {
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="SIMAD - Sistem Informasi Pengadaan Desa">
    <title>Login - SIMAD</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <form method="POST" class="space-y-6">
                <!-- Header -->
                <div class="text-center">
                    <img src="img/clasnet.png" alt="SIMAD Logo" class="mx-auto h-20 w-auto mb-4">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">SIMAD</h1>
                    <h4 class="text-lg text-gray-600 font-normal">Sistem Informasi Pengadaan Desa</h4>
                </div>
            
                <!-- Alerts -->
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-800"><?= htmlspecialchars($error) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-800"><?= htmlspecialchars($success) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Role Info -->
                <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                    <h5 class="text-sm font-medium text-blue-900 mb-3 flex items-center">
                        <i class="fas fa-info-circle mr-2"></i> Akses Role:
                    </h5>
                    <ul class="text-xs text-blue-800 space-y-1">
                        <li><strong>Admin:</strong> Akses penuh sistem</li>
                        <li><strong>Sales:</strong> Transaksi & laporan penjualan</li>
                        <li><strong>Teknisi:</strong> Maintenance & support</li>
                        <li><strong>Finance:</strong> Keuangan & piutang</li>
                    </ul>
                </div>
            
                <!-- Form Fields -->
                <div class="space-y-4">
                    <!-- Username Field -->
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user text-gray-400"></i>
                        </div>
                        <input type="text" name="username" 
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500" 
                               placeholder="Username atau Email" 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    </div>
                    
                    <!-- Password Field -->
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-key text-gray-400"></i>
                        </div>
                        <input type="password" name="password" 
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500" 
                               placeholder="Password" required>
                    </div>
                    
                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" 
                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                               <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                        <label for="remember" class="ml-2 block text-sm text-gray-900">
                            Ingat Saya
                        </label>
                    </div>
                    
                    <!-- Login Button -->
                    <button type="submit" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-150 ease-in-out">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        LOGIN
                    </button>
                </div>
        </form>
        
            <!-- Footer Links -->
            <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                <a href="index.php" 
                   class="text-sm text-primary-600 hover:text-primary-500 flex items-center transition duration-150 ease-in-out">
                    <i class="fas fa-home mr-1"></i> Beranda
                </a>
                <a href="forgot-password.php" 
                   class="text-sm text-primary-600 hover:text-primary-500 flex items-center transition duration-150 ease-in-out">
                    <i class="fas fa-lock mr-1"></i> Lupa Password
                </a>
            </div>
        </div>
    </div>
    
    <!-- Demo Accounts Modal -->
    <div id="demoModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-lg shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center mb-4">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                </div>
                <div class="text-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Demo Akun untuk Testing</h3>
                </div>
                
                <!-- Demo Accounts Table -->
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg mb-4">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Password</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Admin</span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">admin</td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">admin123</td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <button onclick="fillLogin('admin', 'admin123')" 
                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        Gunakan
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Sales</span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">sales</td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">sales123</td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <button onclick="fillLogin('sales', 'sales123')" 
                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        Gunakan
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Teknisi</span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">teknisi</td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">teknisi123</td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <button onclick="fillLogin('teknisi', 'teknisi123')" 
                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        Gunakan
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Finance</span>
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">finance</td>
                                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">finance123</td>
                                <td class="px-3 py-4 whitespace-nowrap">
                                    <button onclick="fillLogin('finance', 'finance123')" 
                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                        Gunakan
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Close Button -->
                <div class="text-center">
                    <button onclick="hideDemoAccounts()" 
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showDemoAccounts() {
            document.getElementById('demoModal').classList.remove('hidden');
        }
        
        function hideDemoAccounts() {
            document.getElementById('demoModal').classList.add('hidden');
        }
        
        function fillLogin(username, password) {
            document.querySelector('input[name="username"]').value = username;
            document.querySelector('input[name="password"]').value = password;
            hideDemoAccounts();
        }
        
        // Close modal when clicking outside
        document.getElementById('demoModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDemoAccounts();
            }
        });
    </script>
</body>
</html>
