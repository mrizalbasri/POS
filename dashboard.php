<?php
/**
 * Dashboard Page
 * POS Application
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Dashboard';
$db = getDB();

// ============================================
// QUERY: Statistics Cards
// ============================================

// Total Pendapatan Hari Ini
$stmt = $db->query("SELECT COALESCE(SUM(total_bayar), 0) as total FROM transactions WHERE DATE(tanggal_transaksi) = CURDATE() AND status = 'selesai'");
$revenueToday = $stmt->fetch()['total'];

// Total Pendapatan Kemarin (untuk perbandingan)
$stmt = $db->query("SELECT COALESCE(SUM(total_bayar), 0) as total FROM transactions WHERE DATE(tanggal_transaksi) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND status = 'selesai'");
$revenueYesterday = $stmt->fetch()['total'];

// Persentase perubahan revenue
$revenueChange = $revenueYesterday > 0 ? round((($revenueToday - $revenueYesterday) / $revenueYesterday) * 100, 1) : 0;

// Total Pendapatan Bulan Ini
$stmt = $db->query("SELECT COALESCE(SUM(total_bayar), 0) as total FROM transactions WHERE MONTH(tanggal_transaksi) = MONTH(CURDATE()) AND YEAR(tanggal_transaksi) = YEAR(CURDATE()) AND status = 'selesai'");
$revenueMonth = $stmt->fetch()['total'];

// Total Transaksi Hari Ini
$stmt = $db->query("SELECT COUNT(*) as total FROM transactions WHERE DATE(tanggal_transaksi) = CURDATE() AND status = 'selesai'");
$transactionsToday = $stmt->fetch()['total'];

// Total Transaksi Bulan Ini
$stmt = $db->query("SELECT COUNT(*) as total FROM transactions WHERE MONTH(tanggal_transaksi) = MONTH(CURDATE()) AND YEAR(tanggal_transaksi) = YEAR(CURDATE()) AND status = 'selesai'");
$transactionsMonth = $stmt->fetch()['total'];

// Total Produk Aktif
$stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE status = 'aktif'");
$totalProducts = $stmt->fetch()['total'];

// Produk Stok Rendah (< 10)
$stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE stok < 10 AND status = 'aktif'");
$lowStockCount = $stmt->fetch()['total'];

// Total Pelanggan
$stmt = $db->query("SELECT COUNT(*) as total FROM customers");
$totalCustomers = $stmt->fetch()['total'];

// Pelanggan Baru Bulan Ini
$stmt = $db->query("SELECT COUNT(*) as total FROM customers WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$newCustomersMonth = $stmt->fetch()['total'];

// ============================================
// QUERY: Transaksi Terakhir (5)
// ============================================
$stmt = $db->query("
    SELECT t.no_invoice, t.tanggal_transaksi, t.total_bayar, t.metode_pembayaran, t.status,
           COALESCE(c.nama_pelanggan, 'Umum') as nama_pelanggan,
           u.nama_lengkap as kasir
    FROM transactions t
    LEFT JOIN customers c ON t.customer_id = c.id
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.tanggal_transaksi DESC
    LIMIT 5
");
$recentTransactions = $stmt->fetchAll();

// ============================================
// QUERY: Produk Terlaris (5)
// ============================================
$stmt = $db->query("
    SELECT p.nama_produk, p.kode_produk, p.harga_jual, p.stok,
           COALESCE(SUM(td.jumlah), 0) as total_terjual,
           COALESCE(SUM(td.subtotal), 0) as total_pendapatan
    FROM products p
    LEFT JOIN transaction_details td ON p.id = td.product_id
    LEFT JOIN transactions t ON td.transaction_id = t.id AND t.status = 'selesai'
    WHERE p.status = 'aktif'
    GROUP BY p.id
    ORDER BY total_terjual DESC
    LIMIT 5
");
$topProducts = $stmt->fetchAll();

// ============================================
// QUERY: Produk Stok Rendah
// ============================================
$stmt = $db->query("
    SELECT kode_produk, nama_produk, stok, satuan, harga_jual
    FROM products
    WHERE stok < 10 AND status = 'aktif'
    ORDER BY stok ASC
    LIMIT 5
");
$lowStockProducts = $stmt->fetchAll();

// ============================================
// QUERY: Data Chart - Pendapatan 7 hari terakhir
// ============================================
$stmt = $db->query("
    SELECT DATE(tanggal_transaksi) as tanggal,
           COALESCE(SUM(total_bayar), 0) as total
    FROM transactions
    WHERE tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND status = 'selesai'
    GROUP BY DATE(tanggal_transaksi)
    ORDER BY tanggal ASC
");
$chartData = $stmt->fetchAll();

// Build full 7-day data (fill gaps with 0)
$chartLabels = [];
$chartValues = [];
$chartDataMap = [];
foreach ($chartData as $row) {
    $chartDataMap[$row['tanggal']] = $row['total'];
}
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('d M', strtotime($date));
    $chartValues[] = $chartDataMap[$date] ?? 0;
}

// ============================================
// QUERY: Ringkasan Metode Pembayaran (Bulan Ini)
// ============================================
$stmt = $db->query("
    SELECT metode_pembayaran, COUNT(*) as jumlah, SUM(total_bayar) as total
    FROM transactions
    WHERE MONTH(tanggal_transaksi) = MONTH(CURDATE())
    AND YEAR(tanggal_transaksi) = YEAR(CURDATE())
    AND status = 'selesai'
    GROUP BY metode_pembayaran
    ORDER BY total DESC
");
$paymentSummary = $stmt->fetchAll();

$paymentLabels = [
    'tunai' => 'Tunai',
    'kartu_debit' => 'Kartu Debit',
    'kartu_kredit' => 'Kartu Kredit',
    'e-wallet' => 'E-Wallet',
    'transfer' => 'Transfer',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <meta name="description" content="Dashboard POS System - Ringkasan penjualan, produk, dan pelanggan">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Topbar -->
            <?php include __DIR__ . '/includes/header.php'; ?>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Flash Message -->
                <?php $flash = getFlash(); ?>
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'warning' ?> alert-flash alert-dismissible fade show mb-4">
                        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards Row -->
                <div class="row g-4 mb-4">
                    <!-- Pendapatan Hari Ini -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card card-revenue">
                            <div class="stat-header">
                                <div class="stat-icon icon-revenue">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <?php if ($revenueChange != 0): ?>
                                <span class="stat-badge <?= $revenueChange >= 0 ? 'up' : 'down' ?>">
                                    <i class="bi bi-arrow-<?= $revenueChange >= 0 ? 'up' : 'down' ?>"></i>
                                    <?= abs($revenueChange) ?>%
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="stat-value"><?= formatRupiah($revenueToday) ?></div>
                            <div class="stat-label">Pendapatan Hari Ini</div>
                        </div>
                    </div>

                    <!-- Transaksi Hari Ini -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card card-transaction">
                            <div class="stat-header">
                                <div class="stat-icon icon-transaction">
                                    <i class="bi bi-receipt"></i>
                                </div>
                                <span class="stat-badge up">
                                    <i class="bi bi-calendar3"></i> Bulan: <?= $transactionsMonth ?>
                                </span>
                            </div>
                            <div class="stat-value"><?= $transactionsToday ?></div>
                            <div class="stat-label">Transaksi Hari Ini</div>
                        </div>
                    </div>

                    <!-- Total Produk -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card card-product">
                            <div class="stat-header">
                                <div class="stat-icon icon-product">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                                <?php if ($lowStockCount > 0): ?>
                                <span class="stat-badge down">
                                    <i class="bi bi-exclamation-triangle"></i> <?= $lowStockCount ?> stok rendah
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="stat-value"><?= $totalProducts ?></div>
                            <div class="stat-label">Produk Aktif</div>
                        </div>
                    </div>

                    <!-- Total Pelanggan -->
                    <div class="col-xl-3 col-md-6">
                        <div class="stat-card card-customer">
                            <div class="stat-header">
                                <div class="stat-icon icon-customer">
                                    <i class="bi bi-people"></i>
                                </div>
                                <?php if ($newCustomersMonth > 0): ?>
                                <span class="stat-badge up">
                                    <i class="bi bi-plus"></i> <?= $newCustomersMonth ?> baru
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="stat-value"><?= $totalCustomers ?></div>
                            <div class="stat-label">Total Pelanggan</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-4 mb-4">
                    <!-- Chart: Pendapatan 7 Hari -->
                    <div class="col-lg-8">
                        <div class="panel">
                            <div class="panel-header">
                                <div class="panel-title">
                                    <i class="bi bi-graph-up"></i> Pendapatan 7 Hari Terakhir
                                </div>
                                <span class="stat-badge up">
                                    Total: <?= formatRupiah(array_sum($chartValues)) ?>
                                </span>
                            </div>
                            <div class="panel-body">
                                <div class="chart-container">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Panel: Ringkasan -->
                    <div class="col-lg-4">
                        <div class="panel h-100">
                            <div class="panel-header">
                                <div class="panel-title">
                                    <i class="bi bi-info-circle"></i> Ringkasan Bulan Ini
                                </div>
                            </div>
                            <div class="panel-body">
                                <div class="quick-info-item">
                                    <div class="quick-info-label">
                                        <i class="bi bi-wallet2 text-merah"></i> Total Pendapatan
                                    </div>
                                    <div class="quick-info-value"><?= formatRupiah($revenueMonth) ?></div>
                                </div>
                                <div class="quick-info-item">
                                    <div class="quick-info-label">
                                        <i class="bi bi-receipt text-biru"></i> Total Transaksi
                                    </div>
                                    <div class="quick-info-value"><?= $transactionsMonth ?></div>
                                </div>
                                <div class="quick-info-item">
                                    <div class="quick-info-label">
                                        <i class="bi bi-calculator text-merah"></i> Rata-rata / Transaksi
                                    </div>
                                    <div class="quick-info-value">
                                        <?= $transactionsMonth > 0 ? formatRupiah($revenueMonth / $transactionsMonth) : 'Rp 0' ?>
                                    </div>
                                </div>

                                <hr class="my-3">
                                <h6 class="fw-700 mb-3" style="font-size:13px; color:var(--gray-500);">
                                    <i class="bi bi-credit-card-2-front"></i> METODE PEMBAYARAN
                                </h6>
                                <?php foreach ($paymentSummary as $ps): ?>
                                <div class="quick-info-item">
                                    <div class="quick-info-label">
                                        <span class="badge-payment"><?= $paymentLabels[$ps['metode_pembayaran']] ?? $ps['metode_pembayaran'] ?></span>
                                    </div>
                                    <div class="quick-info-value" style="font-size:13px;">
                                        <?= $ps['jumlah'] ?>x &middot; <?= formatRupiah($ps['total']) ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tables Row -->
                <div class="row g-4 mb-4">
                    <!-- Transaksi Terakhir -->
                    <div class="col-lg-7">
                        <div class="panel">
                            <div class="panel-header">
                                <div class="panel-title">
                                    <i class="bi bi-clock-history"></i> Transaksi Terakhir
                                </div>
                                <a href="transaksi.php" class="btn btn-sm btn-outline-primary" style="font-size:12px; border-radius:8px;">
                                    Lihat Semua <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                            <div class="panel-body p-0">
                                <div class="table-responsive">
                                    <table class="table-modern">
                                        <thead>
                                            <tr>
                                                <th>Invoice</th>
                                                <th>Pelanggan</th>
                                                <th>Total</th>
                                                <th>Metode</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($recentTransactions)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    <i class="bi bi-inbox" style="font-size:24px;"></i>
                                                    <p class="mt-2 mb-0">Belum ada transaksi</p>
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                            <?php foreach ($recentTransactions as $trx): ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight:600; font-size:12px;"><?= e($trx['no_invoice']) ?></div>
                                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($trx['tanggal_transaksi'])) ?></small>
                                                </td>
                                                <td><?= e($trx['nama_pelanggan']) ?></td>
                                                <td style="font-weight:700;"><?= formatRupiah($trx['total_bayar']) ?></td>
                                                <td>
                                                    <span class="badge-payment"><?= $paymentLabels[$trx['metode_pembayaran']] ?? $trx['metode_pembayaran'] ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge-status badge-<?= $trx['status'] ?>"><?= ucfirst($trx['status']) ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Produk Terlaris -->
                    <div class="col-lg-5">
                        <div class="panel">
                            <div class="panel-header">
                                <div class="panel-title">
                                    <i class="bi bi-trophy"></i> Produk Terlaris
                                </div>
                            </div>
                            <div class="panel-body p-0">
                                <div class="table-responsive">
                                    <table class="table-modern">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Produk</th>
                                                <th>Terjual</th>
                                                <th>Pendapatan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topProducts as $i => $prod): ?>
                                            <tr>
                                                <td>
                                                    <span style="width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;border-radius:6px;font-size:11px;font-weight:700;
                                                        <?php if ($i === 0): ?>background:var(--warning-light);color:var(--warning);
                                                        <?php elseif ($i === 1): ?>background:var(--gray-100);color:var(--gray-600);
                                                        <?php elseif ($i === 2): ?>background:#FBE9E7;color:#BF360C;
                                                        <?php else: ?>background:var(--gray-50);color:var(--gray-400);<?php endif; ?>">
                                                        <?= $i + 1 ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="font-weight:600;"><?= e($prod['nama_produk']) ?></div>
                                                    <small class="text-muted"><?= e($prod['kode_produk']) ?></small>
                                                </td>
                                                <td style="font-weight:600;"><?= $prod['total_terjual'] ?></td>
                                                <td style="font-weight:600; font-size:12px;"><?= formatRupiah($prod['total_pendapatan']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <?php if (!empty($lowStockProducts)): ?>
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="panel" style="border-left: 4px solid var(--danger);">
                            <div class="panel-header">
                                <div class="panel-title" style="color: var(--danger);">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Peringatan Stok Rendah
                                </div>
                                <a href="produk.php" class="btn btn-sm btn-outline-danger" style="font-size:12px; border-radius:8px;">
                                    Kelola Stok <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                            <div class="panel-body p-0">
                                <div class="table-responsive">
                                    <table class="table-modern">
                                        <thead>
                                            <tr>
                                                <th>Kode</th>
                                                <th>Nama Produk</th>
                                                <th>Stok Tersisa</th>
                                                <th>Satuan</th>
                                                <th>Harga Jual</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lowStockProducts as $lsp): ?>
                                            <tr>
                                                <td><code><?= e($lsp['kode_produk']) ?></code></td>
                                                <td style="font-weight:600;"><?= e($lsp['nama_produk']) ?></td>
                                                <td>
                                                    <span style="color:var(--danger);font-weight:700;">
                                                        <i class="bi bi-exclamation-circle"></i> <?= $lsp['stok'] ?>
                                                    </span>
                                                </td>
                                                <td><?= e($lsp['satuan']) ?></td>
                                                <td><?= formatRupiah($lsp['harga_jual']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /.content-area -->
        </div><!-- /.main-content -->
    </div><!-- /.app-wrapper -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <script>
        // Toggle Sidebar (Mobile)
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, 'rgba(2, 119, 189, 0.25)');
        gradient.addColorStop(1, 'rgba(2, 119, 189, 0.02)');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Pendapatan',
                    data: <?= json_encode($chartValues) ?>,
                    borderColor: '#0277BD',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#0277BD',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#C62828',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a1a2e',
                        titleFont: { size: 12, weight: '600' },
                        bodyFont: { size: 13 },
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.raw.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 11, weight: '500' },
                            color: '#9E9E9E'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.04)',
                            drawBorder: false
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#9E9E9E',
                            callback: function(value) {
                                if (value >= 1000000) return 'Rp ' + (value/1000000).toFixed(1) + 'jt';
                                if (value >= 1000) return 'Rp ' + (value/1000).toFixed(0) + 'rb';
                                return 'Rp ' + value;
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert-flash').forEach(el => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
