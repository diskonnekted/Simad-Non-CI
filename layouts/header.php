<?php
// Ensure this file is included properly
if (!defined('KODE_APP')) {
    define('KODE_APP', true);
}

// Get current user data for header display
if (class_exists('AuthStatic') && AuthStatic::isLoggedIn()) {
    $user = AuthStatic::getCurrentUser();
}
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="description" content="Sistem Manajemen Transaksi Desa - KODE">
    <meta name="keywords" content="transaksi, desa, manajemen, sistem">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Sistem Manajemen Transaksi Desa</title>

    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#007bff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="SIMAD">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="msapplication-TileColor" content="#007bff">
    <meta name="msapplication-tap-highlight" content="no">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="apple-touch-icon" href="favicon.svg">
    <link rel="apple-touch-icon" sizes="72x72" href="img/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="96x96" href="img/icon-96x96.png">
    <link rel="apple-touch-icon" sizes="128x128" href="img/icon-128x128.png">
    <link rel="apple-touch-icon" sizes="144x144" href="img/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="img/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="192x192" href="img/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="384x384" href="img/icon-384x384.png">
    <link rel="apple-touch-icon" sizes="512x512" href="img/icon-512x512.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- PWA Mobile Optimization CSS -->
    <link href="css/pwa-mobile.css?v=<?php echo time(); ?>" rel="stylesheet">
    
    <!-- Critical Form Styling Override -->
    <style>
        /* Force form text visibility with highest priority */
        body input, body select, body textarea, body .form-control,
        form input, form select, form textarea, form .form-control,
        input[type="text"], input[type="url"], input[type="email"], input[type="password"], 
        input[type="date"], input[type="number"], input[type="tel"], 
        select, textarea, .form-control {
            color: #333 !important;
            background-color: #fff !important;
            border: 1px solid #BDC4C9 !important;
        }
        
        body input:focus, body select:focus, body textarea:focus, body .form-control:focus,
        form input:focus, form select:focus, form textarea:focus, form .form-control:focus,
        input:focus, select:focus, textarea:focus, .form-control:focus {
            color: #333 !important;
            background-color: #f7f7f7 !important;
            border-color: #2563eb !important;
            outline: none !important;
        }
        
        body select option, form select option, select option {
            color: #333 !important;
            background-color: #fff !important;
        }
        
        body input::placeholder, body textarea::placeholder,
        form input::placeholder, form textarea::placeholder,
        input::placeholder, textarea::placeholder {
            color: #999 !important;
            opacity: 1 !important;
        }
    </style>
    
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Custom Tailwind Configuration -->
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
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- PWA Service Worker Registration -->
    <script>
        // Register service worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    })
                    .catch(function(err) {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
        
        // PWA Install Prompt
        let deferredPrompt;
        let installButton = null;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent Chrome 67 and earlier from automatically showing the prompt
            e.preventDefault();
            // Stash the event so it can be triggered later
            deferredPrompt = e;
            
            // Show install button
            showInstallButton();
        });
        
        function showInstallButton() {
            if (!installButton) {
                installButton = document.createElement('button');
                installButton.innerHTML = '<i class="fas fa-download mr-2"></i>Install App';
                installButton.className = 'fixed bottom-4 right-4 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg shadow-lg z-50 transition-all duration-300';
                installButton.onclick = installPWA;
                document.body.appendChild(installButton);
            }
        }
        
        function installPWA() {
            if (deferredPrompt) {
                // Show the prompt
                deferredPrompt.prompt();
                // Wait for the user to respond to the prompt
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the A2HS prompt');
                    } else {
                        console.log('User dismissed the A2HS prompt');
                    }
                    deferredPrompt = null;
                    if (installButton) {
                        installButton.remove();
                        installButton = null;
                    }
                });
            }
        }
        
        // Hide install button when app is installed
        window.addEventListener('appinstalled', (evt) => {
            console.log('PWA was installed');
            if (installButton) {
                installButton.remove();
                installButton = null;
            }
        });
    </script>
    
    <!-- PWA Features JavaScript -->
    <script src="js/pwa-features.js"></script>
</head>
<body class="h-full bg-gray-50">
    <!-- Loading Overlay -->
    <div id="loading-overlay" class="fixed inset-0 bg-white bg-opacity-90 hidden items-center justify-center z-50">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600"></div>
    </div>

    <!-- Mobile toggle button only -->
    <button id="toggleSidebarMobile" aria-expanded="true" aria-controls="sidebar" class="lg:hidden fixed top-4 left-4 z-40 text-gray-600 hover:text-gray-900 cursor-pointer p-2 hover:bg-gray-100 focus:bg-gray-100 focus:ring-2 focus:ring-gray-100 rounded bg-white shadow-sm">
        <i class="fas fa-bars text-lg"></i>
    </button> 

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed top-0 left-0 z-20 flex flex-col flex-shrink-0 hidden w-64 h-full font-normal duration-75 lg:flex transition-width" aria-label="Sidebar">
        <div class="relative flex-1 flex flex-col min-h-0 border-r border-gray-200 bg-white">
            <!-- App Title at Top -->
            <div class="px-4 py-6 border-b border-gray-200 bg-primary-50">
                <a href="index.php" class="flex flex-col">
                    <div class="text-2xl font-bold text-primary-600">SIMAD</div>
                    <div class="text-sm text-primary-500 -mt-1">Sistem Informasi Manajemen Desa</div>
                </a>
            </div>
            
            <!-- User Profile Section -->
            <div class="px-4 py-4 border-b border-gray-200 bg-white">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <?php if (!empty($user['foto_profil']) && file_exists('uploads/users/' . $user['foto_profil'])): ?>
                            <img class="w-10 h-10 rounded-full object-cover" src="uploads/users/<?php echo htmlspecialchars($user['foto_profil']); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($user['nama_lengkap']); ?>">
                        <?php else: ?>
                            <img class="w-10 h-10 rounded-full" src="img/profileimg.png" alt="user photo">
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">
                            <?php echo htmlspecialchars($user['nama_lengkap'] ?? 'User'); ?>
                        </p>
                        <p class="text-xs text-gray-500 truncate">
                            <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                        </p>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-1">
                            <?php echo ucfirst($user['role'] ?? 'user'); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex-1 flex flex-col pt-5 pb-4 overflow-y-auto">
                <div class="flex-1 px-3 bg-white divide-y space-y-1">
                    <!-- Dashboard Section -->
                    <div class="space-y-2 pb-2">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-2">DASHBOARD</div>
                        <ul class="space-y-2">
                            <?php if (AuthStatic::hasRole(['programmer'])): ?>
                            <li>
                                <a href="dashboard-programmer.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'dashboard-programmer.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-code <?php echo strpos($_SERVER['PHP_SELF'], 'dashboard-programmer.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Dashboard Programmer
                                </a>
                            </li>
                            <?php else: ?>
                            <li>
                                <a href="dashboard.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-home <?php echo strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Dashboard
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (AuthStatic::hasRole(['admin']) || AuthStatic::hasRole(['admin', 'akunting', 'supervisor'])): ?>
                            <li>
                                <a href="pembelian.php?view=statistik" class="<?php echo (strpos($_SERVER['PHP_SELF'], 'pembelian.php') !== false && isset($_GET['view']) && $_GET['view'] === 'statistik') ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-chart-line <?php echo (strpos($_SERVER['PHP_SELF'], 'pembelian.php') !== false && isset($_GET['view']) && $_GET['view'] === 'statistik') ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Statistik Pembelian
                                </a>
                            </li>
                            <li>
                                <a href="transaksi-dashboard.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'transaksi-dashboard.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-chart-bar <?php echo strpos($_SERVER['PHP_SELF'], 'transaksi-dashboard.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Statistik Penjualan
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <!-- Transaksi Section -->
                    <?php if (!AuthStatic::hasRole(['programmer']) && (AuthStatic::hasRole(['admin']) || AuthStatic::hasRole(['admin', 'akunting', 'supervisor']))): ?>
                    <div class="space-y-2 pt-2">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-2">TRANSAKSI</div>
                        <ul class="space-y-2">
                            <li>
                                <a href="pos.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'pos.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-cash-register <?php echo strpos($_SERVER['PHP_SELF'], 'pos.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    POS (Point of Sale)
                                </a>
                            </li>
                            <li>
                                <a href="transaksi.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'transaksi.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-exchange-alt <?php echo strpos($_SERVER['PHP_SELF'], 'transaksi.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Penjualan Produk
                                </a>
                            </li>
                            <li>
                                <a href="pembelian.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'pembelian.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-shopping-cart <?php echo strpos($_SERVER['PHP_SELF'], 'pembelian.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Pembelian Produk
                                </a>
                            </li>
                            <li>
                                <a href="piutang.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'piutang.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-credit-card <?php echo strpos($_SERVER['PHP_SELF'], 'piutang.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Hutang Piutang
                                </a>
                            </li>
                            <li>
                                <a href="penerimaan.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'penerimaan.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-inbox <?php echo strpos($_SERVER['PHP_SELF'], 'penerimaan.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Penerimaan Barang
                                </a>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Keuangan Section -->
                    <?php if (AuthStatic::hasRole(['admin', 'finance'])): ?>
                    <div class="space-y-2 pt-2">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-2">KEUANGAN</div>
                        <ul class="space-y-2">
                            <li>
                                <a href="bank.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'bank.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-university <?php echo strpos($_SERVER['PHP_SELF'], 'bank.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Bank
                                </a>
                            </li>
                            <li>
                                <a href="saldo-bank.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'saldo-bank.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-wallet <?php echo strpos($_SERVER['PHP_SELF'], 'saldo-bank.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Saldo Bank
                                </a>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <!-- Data Desa Section for Programmer -->
                    <?php if (AuthStatic::hasRole(['programmer'])): ?>
                    <div class="space-y-2 pt-2">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-2">DATA DESA</div>
                        <ul class="space-y-2">
                            <li>
                                <a href="desa.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'desa.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-map-marker-alt <?php echo strpos($_SERVER['PHP_SELF'], 'desa.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Data Desa
                                </a>
                            </li>
                            <li>
                                <a href="website-desa.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'website-desa.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-globe <?php echo strpos($_SERVER['PHP_SELF'], 'website-desa.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Website Desa
                                </a>
                            </li>
                            <li>
                                <a href="peta-desa.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'peta-desa.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-globe-americas <?php echo strpos($_SERVER['PHP_SELF'], 'peta-desa.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Peta Desa
                                </a>
                            </li>
                            <li>
                                <a href="tracking-desa.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'tracking-desa.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-map-marked-alt <?php echo strpos($_SERVER['PHP_SELF'], 'tracking-desa.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Tracking Desa
                                </a>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Master Data Section -->
                    <?php if (!AuthStatic::hasRole(['programmer'])): ?>
                    <div class="space-y-2 pt-2">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-2">MASTER DATA</div>
                        <ul class="space-y-2">
                            <?php if (AuthStatic::hasRole(['admin', 'akunting', 'supervisor'])): ?>
                            <li>
                                <a href="produk.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'produk.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-cubes <?php echo strpos($_SERVER['PHP_SELF'], 'produk.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Produk
                                </a>
                            </li>
                            <li>
                                <a href="kategori.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'kategori.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-tags <?php echo strpos($_SERVER['PHP_SELF'], 'kategori.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Kategori Produk
                                </a>
                            </li>
                            <li>
                                <a href="stock-opname.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'stock-opname.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-clipboard-check <?php echo strpos($_SERVER['PHP_SELF'], 'stock-opname.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Stock Opname
                                </a>
                            </li>
                            <li>
                                <a href="vendor.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'vendor.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-truck <?php echo strpos($_SERVER['PHP_SELF'], 'vendor.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Vendor
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (AuthStatic::hasRole(['admin', 'supervisor']) || !AuthStatic::hasRole(['programmer'])): ?>
                            <li>
                                <a href="layanan.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'layanan.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-cogs <?php echo strpos($_SERVER['PHP_SELF'], 'layanan.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Layanan Desa
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a href="desa.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'desa.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-map-marker-alt <?php echo strpos($_SERVER['PHP_SELF'], 'desa.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Data Desa
                                </a>
                            </li>
                            <li>
                                <a href="peta-desa.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'peta-desa.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-globe-americas <?php echo strpos($_SERVER['PHP_SELF'], 'peta-desa.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Peta Desa
                                </a>
                            </li>
                            <li>
                                <a href="tracking-desa.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'tracking-desa.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-map-marked-alt <?php echo strpos($_SERVER['PHP_SELF'], 'tracking-desa.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Tracking Desa
                                </a>
                            </li>
                            <?php if (AuthStatic::hasRole(['admin']) || !AuthStatic::hasRole(['programmer'])): ?>
                            <li>
                                <a href="peralatan.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'peralatan.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-tools <?php echo strpos($_SERVER['PHP_SELF'], 'peralatan.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Daftar Peralatan
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (AuthStatic::hasRole(['admin', 'akunting', 'supervisor'])): ?>
                            <li>
                                <a href="biaya.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'biaya.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-dollar-sign <?php echo strpos($_SERVER['PHP_SELF'], 'biaya.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Daftar Biaya
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a href="website-desa.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'website-desa.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-globe <?php echo strpos($_SERVER['PHP_SELF'], 'website-desa.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Website Desa
                                </a>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <!-- Maintenance Section -->
                    <div class="space-y-2 pt-2">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-2">MAINTENANCE</div>
                        <ul class="space-y-2">
                            <?php if (AuthStatic::hasRole(['admin', 'programmer'])): ?>
                            <li>
                                <a href="website-maintenance.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'website-maintenance.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-wrench <?php echo strpos($_SERVER['PHP_SELF'], 'website-maintenance.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Website Maintenance
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (AuthStatic::hasRole(['admin', 'programmer'])): ?>
                            <li>
                                <a href="jadwal.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'jadwal.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-calendar <?php echo strpos($_SERVER['PHP_SELF'], 'jadwal.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Jadwal Kunjungan
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <!-- Manajemen Section -->
                    <div class="space-y-2 pt-2">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide px-3 py-2">MANAJEMEN</div>
                        <ul class="space-y-2">
                            <?php if (!AuthStatic::hasRole(['programmer']) && AuthStatic::hasRole(['admin'])): ?>
                            <li>
                                <a href="user.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'user.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-users <?php echo strpos($_SERVER['PHP_SELF'], 'user.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    User
                                </a>
                            </li>
                            <li>
                                <a href="promo-banner.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'promo-banner.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-images <?php echo strpos($_SERVER['PHP_SELF'], 'promo-banner.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Promo Banner
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (!AuthStatic::hasRole(['programmer']) && (AuthStatic::hasRole(['admin', 'supervisor']))): ?>
                            <li>
                                <a href="laporan.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'laporan.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-file-alt <?php echo strpos($_SERVER['PHP_SELF'], 'laporan.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Laporan
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if (AuthStatic::hasRole(['admin'])): ?>
                            <li>
                                <a href="backup.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'backup.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-download <?php echo strpos($_SERVER['PHP_SELF'], 'backup.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Backup Database
                                </a>
                            </li>
                            <li>
                                <a href="restore.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'restore.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-upload <?php echo strpos($_SERVER['PHP_SELF'], 'restore.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Restore Database
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a href="profile.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'profile.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-user <?php echo strpos($_SERVER['PHP_SELF'], 'profile.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Profile
                                </a>
                            </li>
                            <li>
                                <a href="logout.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'logout.php') !== false ? 'bg-primary-100 border-r-4 border-primary-500 text-primary-700' : 'text-gray-700 hover:bg-gray-100'; ?> group flex items-center px-2 py-2 text-sm font-medium rounded-l-lg">
                                    <i class="fas fa-sign-out-alt <?php echo strpos($_SERVER['PHP_SELF'], 'logout.php') !== false ? 'text-primary-500' : 'text-gray-400 group-hover:text-gray-500'; ?> mr-3"></i>
                                    Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                    

                </div>
            </div>
        </div>
    </aside>
    
    <!-- Mobile sidebar backdrop -->
    <div class="fixed inset-0 z-10 hidden bg-gray-900 bg-opacity-50 lg:hidden" id="sidebarBackdrop"></div> 

    <!-- Main content -->
    <div id="main-content" class="h-full w-full bg-gray-50 relative lg:ml-64 overflow-x-hidden">
        <main class="pt-2 px-1 sm:px-2 max-w-full">