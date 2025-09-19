<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Skip auth for testing
$db = getDatabase();

// Test query yang sama dengan dashboard.php
$monthly_stats = $db->select("
    SELECT 
        DATE_FORMAT(t.created_at, '%Y-%m') as bulan,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(total_amount), 0) as total_nilai
    FROM transaksi t
    WHERE t.created_at >= CONCAT(YEAR(CURDATE()), '-01-01')
    GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
    ORDER BY bulan ASC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Chart Render</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            width: 800px;
            height: 400px;
            margin: 20px;
        }
    </style>
</head>
<body>
    <h1>Test Chart Rendering</h1>
    
    <h2>Data yang akan di-render:</h2>
    <pre><?= json_encode($monthly_stats, JSON_PRETTY_PRINT) ?></pre>
    
    <h2>Dashboard Chart (Implementasi Asli):</h2>
    <div class="chart-container">
        <canvas id="dashboardChart"></canvas>
    </div>
    
    <h2>Fixed Chart (Implementasi Diperbaiki):</h2>
    <div class="chart-container">
        <canvas id="fixedChart"></canvas>
    </div>
    
    <script>
    // Data dari PHP
    const monthlyData = <?= json_encode($monthly_stats) ?>;
    console.log('Monthly Data:', monthlyData);
    
    // === IMPLEMENTASI DASHBOARD ASLI ===
    function generateMonthLabels() {
        const labels = [];
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth();
        
        for (let i = 0; i <= currentMonth; i++) {
            labels.push(monthNames[i] + ' ' + currentYear);
        }
        return labels;
    }
    
    // Prepare chart data (dashboard style)
    let chartLabels, transaksiData, nilaiData;
    
    if (monthlyData && monthlyData.length > 0) {
        chartLabels = monthlyData.map(item => {
            const [year, month] = item.bulan.split('-');
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            return monthNames[parseInt(month) - 1] + ' ' + year;
        });
        transaksiData = monthlyData.map(item => item.jumlah_transaksi);
        nilaiData = monthlyData.map(item => Math.round(item.total_nilai / 1000000));
    } else {
        // No data, show empty chart with proper labels
        chartLabels = generateMonthLabels();
        const monthCount = new Date().getMonth() + 1; // Current month index + 1
        transaksiData = new Array(monthCount).fill(0);
        nilaiData = new Array(monthCount).fill(0);
    }
    
    console.log('Chart Labels:', chartLabels);
    console.log('Transaksi Data:', transaksiData);
    console.log('Nilai Data:', nilaiData);
    
    // Dashboard Chart (implementasi asli)
    const dashboardCtx = document.getElementById('dashboardChart').getContext('2d');
    const dashboardChart = new Chart(dashboardCtx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Jumlah Transaksi',
                data: transaksiData,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: false,
                tension: 0.4,
                pointBackgroundColor: 'rgb(59, 130, 246)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }, {
                label: 'Total Nilai (Juta)',
                data: nilaiData,
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3,
                fill: false,
                tension: 0.4,
                pointBackgroundColor: 'rgb(16, 185, 129)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Bulan'
                    },
                    grid: {
                        display: false
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah Transaksi'
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Nilai (Juta)'
                    },
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // === IMPLEMENTASI FIXED ===
    // Fixed Chart (implementasi yang diperbaiki)
    const fixedCtx = document.getElementById('fixedChart').getContext('2d');
    const fixedChart = new Chart(fixedCtx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Jumlah Transaksi',
                data: transaksiData,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: false,
                tension: 0.4,
                pointBackgroundColor: 'rgb(59, 130, 246)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }, {
                label: 'Total Nilai (Juta)',
                data: nilaiData,
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3,
                fill: false,
                tension: 0.4,
                pointBackgroundColor: 'rgb(16, 185, 129)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1000
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.datasetIndex === 0) {
                                return context.dataset.label + ': ' + context.parsed.y + ' transaksi';
                            } else {
                                return context.dataset.label + ': Rp ' + (context.parsed.y * 1000000).toLocaleString('id-ID');
                            }
                        }
                    }
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Bulan'
                    },
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Jumlah Transaksi'
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        stepSize: 5
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Nilai (Juta)'
                    },
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        stepSize: 50
                    }
                }
            }
        }
    });
    </script>
</body>
</html>