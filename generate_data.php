<?php
/**
 * Generate Dummy Data Page
 * POS Application
 * 
 * Halaman untuk men-generate data dummy:
 * - 1000 Produk
 * - 300 Pelanggan
 * - Transaksi (10-60 per pelanggan) dalam rentang Jan-Des 2025
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Generate Data';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .gen-card {
            background: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            padding: 32px;
            max-width: 720px;
            margin: 0 auto;
        }
        .gen-header {
            text-align: center;
            margin-bottom: 32px;
        }
        .gen-header-icon {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--merah-hati), var(--biru-laut));
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px; color: #fff; font-size: 28px;
            box-shadow: 0 8px 24px rgba(198,40,40,0.25);
        }
        .gen-header h2 { font-size: 22px; font-weight: 700; color: var(--gray-900); margin-bottom: 4px; }
        .gen-header p { font-size: 13px; color: var(--gray-500); }

        .step-item {
            display: flex; align-items: flex-start; gap: 16px;
            padding: 16px 0; border-bottom: 1px solid var(--gray-100);
        }
        .step-item:last-child { border-bottom: none; }
        .step-num {
            width: 36px; height: 36px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700; flex-shrink: 0;
            background: var(--gray-100); color: var(--gray-400);
            transition: var(--transition-base);
        }
        .step-num.active { background: var(--biru-laut-50); color: var(--biru-laut); }
        .step-num.done { background: var(--success-light); color: var(--success); }
        .step-num.error { background: var(--danger-light); color: var(--danger); }
        .step-body { flex: 1; }
        .step-title { font-size: 14px; font-weight: 600; color: var(--gray-800); margin-bottom: 4px; }
        .step-desc { font-size: 12px; color: var(--gray-500); margin-bottom: 8px; }
        .step-progress { display: none; }
        .step-progress.show { display: block; }
        .step-progress .progress { height: 8px; border-radius: 4px; background: var(--gray-100); }
        .step-progress .progress-bar {
            border-radius: 4px;
            background: linear-gradient(90deg, var(--biru-laut), var(--merah-hati));
            transition: width 0.3s ease;
        }
        .step-status { font-size: 11px; margin-top: 4px; font-weight: 500; color: var(--gray-500); }

        .gen-summary {
            background: var(--gray-50); border-radius: var(--border-radius-sm);
            padding: 20px; margin-top: 24px; display: none;
        }
        .gen-summary.show { display: block; animation: alertSlideIn 0.3s ease; }
        .gen-summary h5 { font-size: 14px; font-weight: 700; margin-bottom: 12px; }
        .gen-summary .sum-row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; }
        .gen-summary .sum-label { color: var(--gray-600); }
        .gen-summary .sum-value { font-weight: 700; color: var(--gray-900); }

        .btn-generate {
            width: 100%; height: 48px; border: none; border-radius: var(--border-radius-sm);
            background: linear-gradient(135deg, var(--merah-hati), var(--biru-laut));
            color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;
            transition: var(--transition-base); margin-top: 24px;
        }
        .btn-generate:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(198,40,40,0.35); }
        .btn-generate:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

        .log-area {
            max-height: 200px; overflow-y: auto; background: var(--gray-900);
            border-radius: var(--border-radius-sm); padding: 16px;
            font-family: 'Courier New', monospace; font-size: 12px;
            color: #8BC34A; margin-top: 16px; display: none;
        }
        .log-area.show { display: block; }
        .log-area .log-line { margin-bottom: 2px; }
        .log-area .log-error { color: #FF5252; }
        .log-area .log-info { color: #64B5F6; }
        .log-area .log-success { color: #8BC34A; }
        .log-area .log-warn { color: #FFD54F; }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="content-area">
                <div class="gen-card">
                    <div class="gen-header">
                        <div class="gen-header-icon"><i class="bi bi-database-fill-gear"></i></div>
                        <h2>Generate Data Dummy</h2>
                        <p>Generate 1.000 produk, 300 pelanggan, dan ribuan transaksi</p>
                    </div>

                    <!-- Warning -->
                    <div class="alert alert-warning alert-flash mb-4" style="font-size:13px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <strong>Perhatian!</strong> Proses ini akan <strong>menghapus semua data</strong> pada tabel produk, pelanggan, dan transaksi, lalu mengisi ulang dengan data baru.
                        </div>
                    </div>

                    <!-- Steps -->
                    <div id="stepsContainer">
                        <!-- Step 0: Cleanup -->
                        <div class="step-item" id="step-0">
                            <div class="step-num" id="stepNum-0">1</div>
                            <div class="step-body">
                                <div class="step-title">Bersihkan Data Lama</div>
                                <div class="step-desc">Mengosongkan tabel transaction_details, transactions, products, customers</div>
                                <div class="step-progress" id="stepProgress-0">
                                    <div class="progress"><div class="progress-bar" id="stepBar-0" style="width:0%"></div></div>
                                    <div class="step-status" id="stepStatus-0"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 1: Products -->
                        <div class="step-item" id="step-1">
                            <div class="step-num" id="stepNum-1">2</div>
                            <div class="step-body">
                                <div class="step-title">Generate 1.000 Produk</div>
                                <div class="step-desc">Nama real produk, harga kelipatan 1000, stok 10000</div>
                                <div class="step-progress" id="stepProgress-1">
                                    <div class="progress"><div class="progress-bar" id="stepBar-1" style="width:0%"></div></div>
                                    <div class="step-status" id="stepStatus-1"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Customers -->
                        <div class="step-item" id="step-2">
                            <div class="step-num" id="stepNum-2">3</div>
                            <div class="step-body">
                                <div class="step-title">Generate 300 Pelanggan</div>
                                <div class="step-desc">Nama acak Indonesia realistis</div>
                                <div class="step-progress" id="stepProgress-2">
                                    <div class="progress"><div class="progress-bar" id="stepBar-2" style="width:0%"></div></div>
                                    <div class="step-status" id="stepStatus-2"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Transactions -->
                        <div class="step-item" id="step-3">
                            <div class="step-num" id="stepNum-3">4</div>
                            <div class="step-body">
                                <div class="step-title">Generate Transaksi</div>
                                <div class="step-desc">10-60 transaksi per pelanggan, 1-10 item per transaksi (Jan-Des 2025)</div>
                                <div class="step-progress" id="stepProgress-3">
                                    <div class="progress"><div class="progress-bar" id="stepBar-3" style="width:0%"></div></div>
                                    <div class="step-status" id="stepStatus-3"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Update Stats -->
                        <div class="step-item" id="step-4">
                            <div class="step-num" id="stepNum-4">5</div>
                            <div class="step-body">
                                <div class="step-title">Update Statistik Pelanggan</div>
                                <div class="step-desc">Hitung ulang total_transaksi dan total_belanja tiap pelanggan</div>
                                <div class="step-progress" id="stepProgress-4">
                                    <div class="progress"><div class="progress-bar" id="stepBar-4" style="width:0%"></div></div>
                                    <div class="step-status" id="stepStatus-4"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="gen-summary" id="genSummary">
                        <h5><i class="bi bi-check-circle-fill text-success me-1"></i> Selesai!</h5>
                        <div class="sum-row"><span class="sum-label">Produk</span><span class="sum-value" id="sumProducts">-</span></div>
                        <div class="sum-row"><span class="sum-label">Pelanggan</span><span class="sum-value" id="sumCustomers">-</span></div>
                        <div class="sum-row"><span class="sum-label">Transaksi</span><span class="sum-value" id="sumTransactions">-</span></div>
                        <div class="sum-row"><span class="sum-label">Detail Transaksi</span><span class="sum-value" id="sumDetails">-</span></div>
                        <div class="sum-row"><span class="sum-label">Waktu Total</span><span class="sum-value" id="sumTime">-</span></div>
                    </div>

                    <!-- Log -->
                    <div class="log-area" id="logArea"></div>

                    <!-- Button -->
                    <button class="btn-generate" id="btnGenerate" onclick="startGenerate()">
                        <i class="bi bi-play-fill me-2"></i>Mulai Generate Data
                    </button>

                    <div class="text-center mt-3">
                        <a href="dashboard.php" class="text-muted" style="font-size:13px;">
                            <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        const steps = ['cleanup', 'products', 'customers', 'transactions', 'update_stats'];
        let startTime;

        function log(msg, type = 'info') {
            const area = document.getElementById('logArea');
            area.classList.add('show');
            area.innerHTML += `<div class="log-line log-${type}">[${new Date().toLocaleTimeString('id-ID')}] ${msg}</div>`;
            area.scrollTop = area.scrollHeight;
        }

        function setStep(idx, state, pct, status) {
            const num = document.getElementById(`stepNum-${idx}`);
            const bar = document.getElementById(`stepBar-${idx}`);
            const prog = document.getElementById(`stepProgress-${idx}`);
            const stat = document.getElementById(`stepStatus-${idx}`);

            num.className = `step-num ${state}`;
            prog.classList.add('show');
            bar.style.width = pct + '%';
            if (status) stat.textContent = status;

            if (state === 'done') {
                num.innerHTML = '<i class="bi bi-check-lg"></i>';
            } else if (state === 'error') {
                num.innerHTML = '<i class="bi bi-x-lg"></i>';
            }
        }

        async function runStep(stepName, stepIdx) {
            setStep(stepIdx, 'active', 10, 'Memulai...');
            log(`Step ${stepIdx + 1}: ${stepName} dimulai...`, 'info');

            try {
                // For transactions, we do batches
                if (stepName === 'transactions') {
                    return await runTransactionBatches(stepIdx);
                }

                const resp = await fetch('generate_data_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `step=${stepName}`
                });
                const data = await resp.json();

                if (data.success) {
                    setStep(stepIdx, 'done', 100, data.message);
                    log(`✓ ${data.message}`, 'success');
                    return data;
                } else {
                    throw new Error(data.message || 'Unknown error');
                }
            } catch (err) {
                setStep(stepIdx, 'error', 100, 'Error: ' + err.message);
                log(`✗ Error: ${err.message}`, 'error');
                throw err;
            }
        }

        async function runTransactionBatches(stepIdx) {
            const totalCustomers = 300;
            const batchSize = 10; // 10 pelanggan per batch
            const totalBatches = Math.ceil(totalCustomers / batchSize);
            let totalTrx = 0;
            let totalDetails = 0;

            for (let batch = 0; batch < totalBatches; batch++) {
                const offset = batch * batchSize;
                const pct = Math.round(((batch + 1) / totalBatches) * 100);

                setStep(stepIdx, 'active', pct,
                    `Batch ${batch + 1}/${totalBatches} — Pelanggan ${offset + 1}-${Math.min(offset + batchSize, totalCustomers)}`);

                const resp = await fetch('generate_data_process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `step=transactions&offset=${offset}&limit=${batchSize}`
                });
                const data = await resp.json();

                if (!data.success) throw new Error(data.message);

                totalTrx += data.transactions || 0;
                totalDetails += data.details || 0;

                if ((batch + 1) % 5 === 0 || batch === totalBatches - 1) {
                    log(`  Batch ${batch + 1}/${totalBatches}: +${data.transactions} transaksi, +${data.details} detail`, 'info');
                }
            }

            setStep(stepIdx, 'done', 100, `${totalTrx.toLocaleString('id-ID')} transaksi, ${totalDetails.toLocaleString('id-ID')} detail`);
            log(`✓ Total: ${totalTrx.toLocaleString('id-ID')} transaksi, ${totalDetails.toLocaleString('id-ID')} detail`, 'success');
            return { transactions: totalTrx, details: totalDetails };
        }

        async function startGenerate() {
            const btn = document.getElementById('btnGenerate');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
            startTime = Date.now();

            document.getElementById('logArea').innerHTML = '';
            document.getElementById('genSummary').classList.remove('show');
            log('=== Mulai Generate Data ===', 'warn');

            let results = {};

            try {
                // Step 0: Cleanup
                await runStep('cleanup', 0);

                // Step 1: Products
                const prodResult = await runStep('products', 1);
                results.products = prodResult.count || 0;

                // Step 2: Customers
                const custResult = await runStep('customers', 2);
                results.customers = custResult.count || 0;

                // Step 3: Transactions
                const trxResult = await runStep('transactions', 3);
                results.transactions = trxResult.transactions || 0;
                results.details = trxResult.details || 0;

                // Step 4: Update stats
                await runStep('update_stats', 4);

                // Show summary
                const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
                document.getElementById('sumProducts').textContent = (results.products || 0).toLocaleString('id-ID');
                document.getElementById('sumCustomers').textContent = (results.customers || 0).toLocaleString('id-ID');
                document.getElementById('sumTransactions').textContent = (results.transactions || 0).toLocaleString('id-ID');
                document.getElementById('sumDetails').textContent = (results.details || 0).toLocaleString('id-ID');
                document.getElementById('sumTime').textContent = elapsed + ' detik';
                document.getElementById('genSummary').classList.add('show');

                log(`=== Selesai dalam ${elapsed} detik ===`, 'success');
                btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Selesai!';
                btn.style.background = 'var(--success)';

            } catch (err) {
                log(`=== GAGAL: ${err.message} ===`, 'error');
                btn.innerHTML = '<i class="bi bi-arrow-clockwise me-2"></i>Coba Lagi';
                btn.style.background = 'var(--danger)';
                btn.disabled = false;
                btn.onclick = () => { btn.style.background = ''; startGenerate(); };
            }
        }
    </script>
</body>
</html>
