<?php
require_once 'config/database.php';

// Ambil data desa dengan koordinat
$db = new Database();
$query = "SELECT id, nama_desa, kecamatan, kabupaten, provinsi, latitude, longitude, status, 
                 nama_kepala_desa, no_hp_kepala_desa, email_desa
          FROM desa 
          WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND status = 'aktif'
          ORDER BY kecamatan, nama_desa";
$desa_data = $db->select($query);

// Hitung statistik
$total_desa = count($desa_data);
$kecamatan_count = count(array_unique(array_column($desa_data, 'kecamatan')));

// Group by kecamatan untuk statistik
$kecamatan_stats = [];
foreach ($desa_data as $desa) {
    $kecamatan = $desa['kecamatan'];
    if (!isset($kecamatan_stats[$kecamatan])) {
        $kecamatan_stats[$kecamatan] = 0;
    }
    $kecamatan_stats[$kecamatan]++;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peta Penyebaran Desa - SIMAD</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Mapbox CSS -->
    <link href="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css" rel="stylesheet" />
    
    <!-- Custom CSS -->
    <style>
        #map {
            height: calc(100vh - 120px);
            width: 100%;
            border-radius: 0;
        }
        
        .mapboxgl-popup-content {
            font-family: 'Inter', sans-serif;
            border-radius: 0.5rem;
        }
        
        .map-style-selector {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 0.5rem;
        }
        
        .style-option {
            display: block;
            width: 100%;
            padding: 0.5rem;
            margin: 0.25rem 0;
            border: none;
            border-radius: 0.375rem;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.875rem;
        }
        
        .style-option:hover {
            background: #e2e8f0;
        }
        
        .style-option.active {
            background: #3b82f6;
            color: white;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .kecamatan-card {
            transition: all 0.3s ease;
        }
        
        .kecamatan-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 transition duration-200">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            <i class="fas fa-map-marked-alt mr-2 text-blue-600"></i>
                            Peta Penyebaran Desa
                        </h1>
                        <p class="text-gray-600 text-sm mt-1">Visualisasi lokasi geografis desa-desa di Kabupaten Banjarnegara</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="tracking-desa.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-list mr-2"></i>Data Desa
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- Statistik Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="stats-card text-white rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">Total Desa</p>
                        <p class="text-3xl font-bold"><?= number_format($total_desa) ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full p-3">
                        <i class="fas fa-home text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-green-600 text-white rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">Kecamatan</p>
                        <p class="text-3xl font-bold"><?= number_format($kecamatan_count) ?></p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full p-3">
                        <i class="fas fa-map text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-purple-600 text-white rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">Kabupaten</p>
                        <p class="text-3xl font-bold">Banjarnegara</p>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-full p-3">
                        <i class="fas fa-map-marker-alt text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Peta Full Screen -->
        <div class="relative w-full">
            <div id="map"></div>
            <div class="map-style-selector">
                <div class="text-xs font-semibold text-gray-700 mb-2">Jenis Peta</div>
                <button class="style-option active" data-style="streets-v12">
                    <i class="fas fa-road mr-2"></i>Streets
                </button>
                <button class="style-option" data-style="satellite-streets-v12">
                    <i class="fas fa-satellite mr-2"></i>Satellite
                </button>
                <button class="style-option" data-style="outdoors-v12">
                    <i class="fas fa-mountain mr-2"></i>Outdoors
                </button>
                <button class="style-option" data-style="light-v11">
                    <i class="fas fa-sun mr-2"></i>Light
                </button>
                <button class="style-option" data-style="dark-v11">
                    <i class="fas fa-moon mr-2"></i>Dark
                </button>
                <button class="style-option" data-style="navigation-day-v1">
                    <i class="fas fa-compass mr-2"></i>Navigation
                </button>
            </div>
            
            <!-- Floating Info Panel -->
            <div class="absolute top-4 right-4 bg-white rounded-lg shadow-lg p-4 max-w-xs z-10">
                <h3 class="text-sm font-semibold text-gray-900 mb-2">
                    <i class="fas fa-chart-bar mr-2 text-green-600"></i>
                    Statistik Desa
                </h3>
                <div class="text-xs space-y-1">
                    <div class="flex justify-between">
                        <span>Total Desa:</span>
                        <span class="font-medium"><?= number_format($total_desa) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Kecamatan:</span>
                        <span class="font-medium"><?= number_format($kecamatan_count) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Kabupaten:</span>
                        <span class="font-medium">Banjarnegara</span>
                    </div>
                </div>
            </div>
            
            <!-- Floating Actions Panel -->
            <div class="absolute bottom-4 right-4 bg-white rounded-lg shadow-lg p-3 z-10">
                <div class="flex flex-col space-y-2">
                    <button onclick="resetMapView()" 
                            class="bg-blue-600 text-white px-3 py-2 rounded text-xs hover:bg-blue-700 transition duration-200">
                        <i class="fas fa-home mr-1"></i>Reset
                    </button>
                    <a href="tracking-desa.php" 
                       class="bg-green-600 text-white px-3 py-2 rounded text-xs hover:bg-green-700 transition duration-200 text-center">
                        <i class="fas fa-table mr-1"></i>Tabel
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Mapbox GL JS -->
    <script src="https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js"></script>
    
    <script>
        // Mapbox access token
        mapboxgl.accessToken = 'pk.eyJ1IjoibWl6d2FyIiwiYSI6ImNrYzdrZmdoYzBsemsycXBoODdkeXYwdXoifQ.Y0sY9ZaWB4sBWsoYx0NLpw';
        
        // Data desa dari PHP
        const desaData = <?= json_encode($desa_data) ?>;
        
        // Inisialisasi peta dengan Mapbox
        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [109.6426, -7.3549], // [lng, lat]
            zoom: 9
        });
        
        // Variabel untuk menyimpan markers
        const markers = [];
        const kecamatanGroups = {};
        
        // Tunggu peta selesai dimuat
        map.on('load', function() {
            // Tambahkan marker untuk setiap desa
            desaData.forEach(desa => {
                const lat = parseFloat(desa.latitude);
                const lng = parseFloat(desa.longitude);
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    // Buat custom marker element
                    const markerElement = document.createElement('div');
                    markerElement.className = 'custom-mapbox-marker';
                    markerElement.style.cssText = `
                        width: 24px;
                        height: 24px;
                        background-color: #3B82F6;
                        border: 3px solid #FFFFFF;
                        border-radius: 50%;
                        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                    `;
                    markerElement.innerHTML = '<i class="fas fa-home" style="color: white; font-size: 10px;"></i>';
                    
                    // Buat popup content
                    const popupContent = `
                        <div class="p-3 min-w-64">
                            <h3 class="font-bold text-lg text-blue-600 mb-2">${desa.nama_desa}</h3>
                            <div class="space-y-1 text-sm">
                                <p><i class="fas fa-map-marker-alt text-red-500 mr-2"></i><strong>Kecamatan:</strong> ${desa.kecamatan}</p>
                                <p><i class="fas fa-building text-blue-500 mr-2"></i><strong>Kabupaten:</strong> ${desa.kabupaten}</p>
                                <p><i class="fas fa-map text-green-500 mr-2"></i><strong>Provinsi:</strong> ${desa.provinsi}</p>
                                ${desa.nama_kepala_desa ? `<p><i class="fas fa-user text-purple-500 mr-2"></i><strong>Kepala Desa:</strong> ${desa.nama_kepala_desa}</p>` : ''}
                                ${desa.no_hp_kepala_desa ? `<p><i class="fas fa-phone text-orange-500 mr-2"></i><strong>Telepon:</strong> ${desa.no_hp_kepala_desa}</p>` : ''}
                                ${desa.email_desa ? `<p><i class="fas fa-envelope text-teal-500 mr-2"></i><strong>Email:</strong> ${desa.email_desa}</p>` : ''}
                                <p><i class="fas fa-info-circle text-gray-500 mr-2"></i><strong>Status:</strong> <span class="text-green-600 font-medium">${desa.status}</span></p>
                            </div>
                        </div>
                    `;
                    
                    // Buat popup
                    const popup = new mapboxgl.Popup({
                        offset: 25,
                        className: 'custom-popup'
                    }).setHTML(popupContent);
                    
                    // Buat marker
                    const marker = new mapboxgl.Marker(markerElement)
                        .setLngLat([lng, lat])
                        .setPopup(popup)
                        .addTo(map);
                    
                    markers.push(marker);
                    
                    // Group by kecamatan
                    if (!kecamatanGroups[desa.kecamatan]) {
                        kecamatanGroups[desa.kecamatan] = [];
                    }
                    kecamatanGroups[desa.kecamatan].push(marker);
                }
            });
            
            console.log(`Peta berhasil dimuat dengan ${desaData.length} desa`);
        });
        
        // Fungsi untuk mengganti style peta
        function changeMapStyle(styleId) {
            map.setStyle('mapbox://styles/mapbox/' + styleId);
            
            // Update active button
            document.querySelectorAll('.style-option').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-style="${styleId}"]`).classList.add('active');
            
            // Re-add markers setelah style berubah
            map.once('styledata', function() {
                // Markers akan tetap ada karena menggunakan Marker API, bukan layer
            });
        }
        
        // Event listener untuk tombol style
        document.querySelectorAll('.style-option').forEach(button => {
            button.addEventListener('click', function() {
                const styleId = this.getAttribute('data-style');
                changeMapStyle(styleId);
            });
        });
        
        // Fungsi untuk fokus ke kecamatan
        function focusKecamatan(kecamatan) {
            const kecamatanMarkers = kecamatanGroups[kecamatan];
            if (kecamatanMarkers && kecamatanMarkers.length > 0) {
                // Hitung bounds dari semua marker di kecamatan
                const bounds = new mapboxgl.LngLatBounds();
                kecamatanMarkers.forEach(marker => {
                    bounds.extend(marker.getLngLat());
                });
                
                // Fit map ke bounds
                map.fitBounds(bounds, {
                    padding: 50,
                    duration: 1000
                });
                
                // Highlight markers sementara
                kecamatanMarkers.forEach(marker => {
                    marker.togglePopup();
                    setTimeout(() => {
                        if (marker.getPopup().isOpen()) {
                            marker.togglePopup();
                        }
                    }, 2000);
                });
            }
        }
        
        // Fungsi untuk reset view peta
        function resetMapView() {
            map.flyTo({
                center: [109.6426, -7.3549],
                zoom: 9,
                duration: 1000
            });
        }
        
        // Fungsi toggle fullscreen
        function toggleFullscreen() {
            const mapContainer = document.getElementById('map');
            if (!document.fullscreenElement) {
                mapContainer.requestFullscreen().then(() => {
                    mapContainer.style.height = '100vh';
                    map.resize();
                });
            } else {
                document.exitFullscreen().then(() => {
                    mapContainer.style.height = '600px';
                    map.resize();
                });
            }
        }
        
        // Event listener untuk perubahan ukuran window
        window.addEventListener('resize', () => {
            map.resize();
        });
        
        // Tambahkan kontrol navigasi
        map.addControl(new mapboxgl.NavigationControl(), 'top-left');
        
        // Tambahkan kontrol fullscreen
        map.addControl(new mapboxgl.FullscreenControl(), 'top-left');
    </script>
</body>
</html>