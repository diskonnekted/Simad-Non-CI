<?php
// Test file untuk memverifikasi perbaikan purchase_process_indicator
require_once 'config/auth.php';
require_once 'components/purchase_process_indicator.php';

// Test data pembelian yang valid
$test_pembelian = [
    'id' => 1,
    'nomor_po' => 'PO-20250911-001',
    'status_pembelian' => 'dikirim'
];

// Test data penerimaan
$test_penerimaan = [
    'status_pembelian' => 'dikirim'
];

echo "<h2>Test Purchase Process Indicator</h2>";
echo "<p>Testing dengan data pembelian yang valid:</p>";

// Test fungsi renderPurchaseProcessIndicator
try {
    renderPurchaseProcessIndicator($test_pembelian, $test_penerimaan);
    echo "<p style='color: green;'>✓ Fungsi renderPurchaseProcessIndicator berhasil dijalankan tanpa error</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

// Test dengan data integer (kasus error sebelumnya)
echo "<hr><p>Testing dengan data pembelian sebagai integer (kasus error sebelumnya):</p>";
try {
    renderPurchaseProcessIndicator(1, $test_penerimaan);
    echo "<p style='color: green;'>✓ Fungsi berhasil menangani input integer tanpa error</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

// Test fungsi getCurrentStep
echo "<hr><p>Testing fungsi getCurrentStep:</p>";
try {
    $step = getCurrentStep($test_pembelian, $test_penerimaan);
    echo "<p style='color: green;'>✓ getCurrentStep berhasil: Step = $step</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error getCurrentStep: " . $e->getMessage() . "</p>";
}

// Test dengan integer
try {
    $step = getCurrentStep(1, $test_penerimaan);
    echo "<p style='color: green;'>✓ getCurrentStep berhasil menangani integer: Step = $step</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error getCurrentStep dengan integer: " . $e->getMessage() . "</p>";
}

echo "<hr><p>Semua test selesai!</p>";
?>