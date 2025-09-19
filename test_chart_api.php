<?php
session_start();
$_SESSION['user_id'] = 1; // Set session untuk test

// Simulasi GET request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['periode'] = 6;

// Capture output dari API
ob_start();
include 'api/chart-data.php';
$output = ob_get_clean();

echo "<h2>=== OUTPUT API CHART-DATA ===</h2>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Decode JSON untuk analisis
$data = json_decode($output, true);
if ($data) {
    echo "<h3>=== ANALISIS DATA JSON ===</h3>";
    echo "<p><strong>Success:</strong> " . ($data['success'] ? 'true' : 'false') . "</p>";
    
    if (isset($data['data']['datasets'])) {
        foreach ($data['data']['datasets'] as $index => $dataset) {
            echo "<h4>Dataset " . $index . ": " . $dataset['label'] . "</h4>";
            echo "<p>Data: " . implode(', ', $dataset['data']) . "</p>";
            echo "<p>Total: " . array_sum($dataset['data']) . "</p>";
            
            if ($dataset['label'] == 'Nominal Penjualan (Rp)') {
                echo "<p><strong>MASALAH DITEMUKAN!</strong></p>";
                echo "<p>Total nominal penjualan dari API: " . number_format(array_sum($dataset['data'])) . "</p>";
                echo "<p>Seharusnya: 419,734,000</p>";
            }
        }
    }
} else {
    echo "<p><strong>Error:</strong> Invalid JSON output</p>";
}
?>