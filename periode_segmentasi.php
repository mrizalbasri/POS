<?php
/**
 * Periode Segmentasi - CRUD + Trigger Proses
 * POS Application
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Periode Segmentasi';
$db = getDB();

// ============================================
// HANDLE ACTIONS
// ============================================

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$formData = [
    'nama_periode' => '',
    'tanggal_proses' => date('Y-m-d H:i:s'),
    'tanggal_transaksi_mulai' => '',
    'tanggal_transaksi_selesai' => '',
    'keterangan' => '',
];

// Handle POST (Create)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    $formData = [
        'nama_periode' => trim($_POST['nama_periode'] ?? ''),
        'tanggal_proses' => trim($_POST['tanggal_proses'] ?? date('Y-m-d H:i:s')),
        'tanggal_transaksi_mulai' => trim($_POST['tanggal_transaksi_mulai'] ?? ''),
        'tanggal_transaksi_selesai' => trim($_POST['tanggal_transaksi_selesai'] ?? ''),
        'keterangan' => trim($_POST['keterangan'] ?? ''),
    ];

    if (empty($formData['nama_periode'])) {
        $errors[] = 'Nama periode wajib diisi.';
    }
    if (empty($formData['tanggal_transaksi_mulai'])) {
        $errors[] = 'Tanggal transaksi mulai wajib diisi.';
    }
    if (empty($formData['tanggal_transaksi_selesai'])) {
        $errors[] = 'Tanggal transaksi selesai wajib diisi.';
    }
    if (!empty($formData['tanggal_transaksi_mulai']) && !empty($formData['tanggal_transaksi_selesai'])) {
        if ($formData['tanggal_transaksi_mulai'] > $formData['tanggal_transaksi_selesai']) {
            $errors[] = 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai.';
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare("
            INSERT INTO periode_segmentasi (nama_periode, tanggal_proses, tanggal_transaksi_mulai, tanggal_transaksi_selesai, status, keterangan)
            VALUES (:nama, :tgl_proses, :tgl_mulai, :tgl_selesai, 'pending', :ket)
        ");
        $stmt->execute([
            ':nama' => $formData['nama_periode'],
            ':tgl_proses' => $formData['tanggal_proses'],
            ':tgl_mulai' => $formData['tanggal_transaksi_mulai'],
            ':tgl_selesai' => $formData['tanggal_transaksi_selesai'],
            ':ket' => $formData['keterangan'] ?: null,
        ]);
        $newId = $db->lastInsertId();
        setFlash('success', 'Periode segmentasi berhasil dibuat. Silakan jalankan proses segmentasi.');
        redirect('periode_segmentasi.php?action=detail&id=' . $newId);
    }
}

// Handle Delete
if ($action === 'delete' && $id > 0) {
    $stmt = $db->prepare("DELETE FROM periode_segmentasi WHERE id = :id");
    $stmt->execute([':id' => $id]);
    setFlash('success', 'Periode segmentasi berhasil dihapus.');
    redirect('periode_segmentasi.php');
}

// Load detail data
$detail = null;
$clusters = [];
$pelangganPerCluster = [];
if ($action === 'detail' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM periode_segmentasi WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $detail = $stmt->fetch();
    if (!$detail) {
        setFlash('error', 'Data tidak ditemukan.');
        redirect('periode_segmentasi.php');
    }

    // Get clusters
    $stmt = $db->prepare("
        SELECT c.*, ks.nama_kelas, ks.kelas_recency, ks.kelas_frequency, ks.kelas_monetary, ks.deskripsi as kelas_deskripsi
        FROM cluster c
        LEFT JOIN kelas_segmentasi ks ON c.kelas_segmentasi_id = ks.id
        WHERE c.periode_segmentasi_id = :pid
        ORDER BY c.nomor_cluster ASC
    ");
    $stmt->execute([':pid' => $id]);
    $clusters = $stmt->fetchAll();
}

// ============================================
// QUERY: List Data
// ============================================
$listData = [];
if ($action === 'list') {
    $stmt = $db->query("SELECT * FROM periode_segmentasi ORDER BY created_at DESC");
    $listData = $stmt->fetchAll();
}

$statusLabels = [
    'pending' => ['label' => 'Pending', 'color' => 'secondary'],
    'proses' => ['label' => 'Sedang Proses', 'color' => 'warning'],
    'selesai' => ['label' => 'Selesai', 'color' => 'success'],
    'gagal' => ['label' => 'Gagal', 'color' => 'danger'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - <?= APP_NAME ?></title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="app-wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <div class="content-area">
                <!-- Flash Message -->
                <?php $flash = getFlash(); ?>
                <?php if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-flash alert-dismissible fade show mb-4">
                        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                        <?= e($flash['message']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'list'): ?>
                <!-- ============================================ -->
                <!-- LIST VIEW -->
                <!-- ============================================ -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="bi bi-calendar-range-fill"></i> Periode Segmentasi
                        </div>
                        <a href="periode_segmentasi.php?action=create" class="btn btn-sm btn-primary" style="border-radius:8px;">
                            <i class="bi bi-plus-lg"></i> Buat Periode Baru
                        </a>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($listData)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox" style="font-size:48px;"></i>
                            <p class="mt-3">Belum ada periode segmentasi. Buat periode baru untuk memulai.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table-modern">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th>Nama Periode</th>
                                        <th>Periode Transaksi</th>
                                        <th style="text-align:center;">Pelanggan</th>
                                        <th style="text-align:center;">Cluster</th>
                                        <th style="text-align:center;">Status</th>
                                        <th>Tanggal Proses</th>
                                        <th style="width:150px; text-align:center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($listData as $i => $row): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td style="font-weight:600;"><?= e($row['nama_periode']) ?></td>
                                        <td>
                                            <small><?= date('d/m/Y', strtotime($row['tanggal_transaksi_mulai'])) ?> - <?= date('d/m/Y', strtotime($row['tanggal_transaksi_selesai'])) ?></small>
                                        </td>
                                        <td style="text-align:center; font-weight:600;">
                                            <?= $row['jumlah_pelanggan'] ?? '-' ?>
                                        </td>
                                        <td style="text-align:center; font-weight:600;">
                                            <?= $row['jumlah_cluster'] ?? '-' ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <?php $st = $statusLabels[$row['status']] ?? ['label' => $row['status'], 'color' => 'secondary']; ?>
                                            <span class="badge bg-<?= $st['color'] ?>"><?= $st['label'] ?></span>
                                        </td>
                                        <td>
                                            <small><?= date('d/m/Y H:i', strtotime($row['tanggal_proses'])) ?></small>
                                        </td>
                                        <td style="text-align:center;">
                                            <a href="periode_segmentasi.php?action=detail&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary" title="Detail">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                                onclick="confirmDelete(<?= $row['id'] ?>, '<?= e($row['nama_periode']) ?>')">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($action === 'create'): ?>
                <!-- ============================================ -->
                <!-- FORM CREATE -->
                <!-- ============================================ -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="bi bi-plus-circle"></i> Buat Periode Segmentasi Baru
                        </div>
                        <a href="periode_segmentasi.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                    </div>
                    <div class="panel-body">
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger mb-4" style="border-radius:10px;">
                            <i class="bi bi-exclamation-triangle-fill"></i> <strong>Terdapat kesalahan:</strong>
                            <ul class="mb-0 mt-2">
                                <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="periode_segmentasi.php?action=create">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="nama_periode" class="form-label fw-semibold">Nama Periode <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nama_periode" name="nama_periode"
                                        value="<?= e($formData['nama_periode']) ?>" placeholder="Contoh: Segmentasi Semester 1 2025" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="tanggal_proses" class="form-label fw-semibold">Tanggal Hitung</label>
                                    <input type="datetime-local" class="form-control" id="tanggal_proses" name="tanggal_proses"
                                        value="<?= date('Y-m-d\TH:i', strtotime($formData['tanggal_proses'])) ?>">
                                    <div class="form-text">Tanggal acuan untuk menghitung Recency (default: sekarang)</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="tanggal_transaksi_mulai" class="form-label fw-semibold">Tanggal Transaksi Mulai <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="tanggal_transaksi_mulai" name="tanggal_transaksi_mulai"
                                        value="<?= e($formData['tanggal_transaksi_mulai']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="tanggal_transaksi_selesai" class="form-label fw-semibold">Tanggal Transaksi Selesai <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="tanggal_transaksi_selesai" name="tanggal_transaksi_selesai"
                                        value="<?= e($formData['tanggal_transaksi_selesai']) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label for="keterangan" class="form-label fw-semibold">Keterangan</label>
                                    <textarea class="form-control" id="keterangan" name="keterangan" rows="2"
                                        placeholder="Catatan tambahan (opsional)"><?= e($formData['keterangan']) ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Simpan Periode
                                </button>
                                <a href="periode_segmentasi.php" class="btn btn-outline-secondary">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php elseif ($action === 'detail' && $detail): ?>
                <!-- ============================================ -->
                <!-- DETAIL VIEW -->
                <!-- ============================================ -->
                <div class="panel mb-4">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="bi bi-info-circle-fill"></i> Detail Periode: <?= e($detail['nama_periode']) ?>
                        </div>
                        <a href="periode_segmentasi.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                    </div>
                    <div class="panel-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <strong>Nama Periode:</strong><br>
                                <?= e($detail['nama_periode']) ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Periode Transaksi:</strong><br>
                                <?= date('d/m/Y', strtotime($detail['tanggal_transaksi_mulai'])) ?> - <?= date('d/m/Y', strtotime($detail['tanggal_transaksi_selesai'])) ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Tanggal Proses:</strong><br>
                                <?= date('d/m/Y H:i', strtotime($detail['tanggal_proses'])) ?>
                            </div>
                            <div class="col-md-4">
                                <strong>Status:</strong><br>
                                <?php $st = $statusLabels[$detail['status']] ?? ['label' => $detail['status'], 'color' => 'secondary']; ?>
                                <span class="badge bg-<?= $st['color'] ?>" id="statusBadge"><?= $st['label'] ?></span>
                            </div>
                            <div class="col-md-4">
                                <strong>Jumlah Pelanggan:</strong><br>
                                <span id="jumlahPelanggan"><?= $detail['jumlah_pelanggan'] ?? '-' ?></span>
                            </div>
                            <div class="col-md-4">
                                <strong>Jumlah Cluster:</strong><br>
                                <span id="jumlahCluster"><?= $detail['jumlah_cluster'] ?? '-' ?></span>
                            </div>
                            <?php if ($detail['keterangan']): ?>
                            <div class="col-12">
                                <strong>Keterangan:</strong><br>
                                <span id="keterangan"><?= e($detail['keterangan']) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Action Button -->
                        <?php if (in_array($detail['status'], ['pending', 'gagal'])): ?>
                        <div class="mb-4">
                            <button type="button" class="btn btn-primary" id="btnProses" onclick="prosesSegmentasi(<?= $detail['id'] ?>)">
                                <i class="bi bi-play-fill"></i> Jalankan Proses Segmentasi
                            </button>
                        </div>
                        <!-- Progress -->
                        <div id="progressArea" style="display:none;" class="mb-4">
                            <div class="alert alert-info" style="border-radius:10px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <div>
                                        <strong>Sedang memproses segmentasi...</strong><br>
                                        <small id="progressText">Menghubungi server Python...</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Metrics -->
                        <?php if ($detail['status'] === 'selesai'): ?>
                        <h6 class="fw-bold mb-3" style="color:var(--gray-600);"><i class="bi bi-graph-up"></i> Metrik Evaluasi Clustering</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="stat-card" style="padding:15px;">
                                    <div class="stat-label">Inertia (SSE)</div>
                                    <div class="stat-value" style="font-size:16px;"><?= number_format($detail['inertia'], 4) ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card" style="padding:15px;">
                                    <div class="stat-label">Silhouette Score</div>
                                    <div class="stat-value" style="font-size:16px;"><?= number_format($detail['silhouette_score'], 4) ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card" style="padding:15px;">
                                    <div class="stat-label">Davies-Bouldin Index</div>
                                    <div class="stat-value" style="font-size:16px;"><?= number_format($detail['davies_bouldin_index'], 4) ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card" style="padding:15px;">
                                    <div class="stat-label">Calinski-Harabasz</div>
                                    <div class="stat-value" style="font-size:16px;"><?= number_format($detail['calinski_harabasz_index'], 2) ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Clusters Table -->
                <?php if (!empty($clusters)): ?>
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="bi bi-pie-chart-fill"></i> Hasil Cluster
                        </div>
                    </div>
                    <div class="panel-body p-0">
                        <div class="table-responsive">
                            <table class="table-modern">
                                <thead>
                                    <tr>
                                        <th style="text-align:center;">Cluster</th>
                                        <th>Kelas Segmentasi</th>
                                        <th style="text-align:center;">Centroid R</th>
                                        <th style="text-align:center;">Centroid F</th>
                                        <th style="text-align:center;">Centroid M</th>
                                        <th style="text-align:center;">Jumlah Anggota</th>
                                        <th>Deskripsi Kelas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clusters as $cl): ?>
                                    <tr>
                                        <td style="text-align:center;">
                                            <span style="width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;font-weight:700;background:var(--primary-light);color:var(--primary);">
                                                <?= $cl['nomor_cluster'] ?>
                                            </span>
                                        </td>
                                        <td style="font-weight:600;">
                                            <?= $cl['nama_kelas'] ? e($cl['nama_kelas']) : '<span class="text-muted">-</span>' ?>
                                        </td>
                                        <td style="text-align:center;"><?= number_format($cl['centroid_recency'], 1) ?></td>
                                        <td style="text-align:center;"><?= number_format($cl['centroid_frequency'], 1) ?></td>
                                        <td style="text-align:center;"><?= formatRupiah($cl['centroid_monetary']) ?></td>
                                        <td style="text-align:center; font-weight:700;"><?= $cl['jumlah_anggota'] ?></td>
                                        <td style="font-size:13px; color:var(--gray-600);">
                                            <?= $cl['kelas_deskripsi'] ? e($cl['kelas_deskripsi']) : '-' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- LLM Business Insights per Cluster -->
                <?php foreach ($clusters as $cl): ?>
                <?php if (!empty($cl['llm_rekomendasi_bisnis'])): ?>
                <div class="panel mt-4">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="bi bi-robot"></i> AI Insight — Cluster <?= $cl['nomor_cluster'] ?>
                            <?php if ($cl['nama_kelas']): ?>
                                <span class="badge bg-primary ms-2" style="font-size:11px;"><?= e($cl['nama_kelas']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="badge bg-light text-dark" style="font-size:11px;">
                            <i class="bi bi-people-fill"></i> <?= $cl['jumlah_anggota'] ?> pelanggan
                        </span>
                    </div>
                    <div class="panel-body">
                        <div class="llm-insight-content" style="font-size:14px; line-height:1.8; white-space:pre-wrap;"><?= nl2br(e($cl['llm_rekomendasi_bisnis'])) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>

                <?php endif; ?>

                <?php endif; ?>

            </div><!-- /.content-area -->
        </div><!-- /.main-content -->
    </div><!-- /.app-wrapper -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        function confirmDelete(id, nama) {
            if (confirm('Yakin ingin menghapus periode "' + nama + '"?\nSemua data cluster dan RFM pelanggan pada periode ini akan ikut terhapus.')) {
                window.location.href = 'periode_segmentasi.php?action=delete&id=' + id;
            }
        }

        function prosesSegmentasi(periodeId) {
            const btn = document.getElementById('btnProses');
            const progressArea = document.getElementById('progressArea');
            const progressText = document.getElementById('progressText');

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses...';
            progressArea.style.display = 'block';
            progressText.textContent = 'Mengirim request ke server Python (FastAPI)...';

            fetch('http://localhost:8000/api/segmentasi/proses/' + periodeId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => { throw new Error(data.detail || 'Gagal memproses'); });
                }
                return response.json();
            })
            .then(data => {
                progressText.textContent = 'Proses selesai! Memuat ulang halaman...';
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            })
            .catch(error => {
                progressArea.innerHTML = `
                    <div class="alert alert-danger" style="border-radius:10px;">
                        <i class="bi bi-exclamation-triangle-fill"></i> <strong>Gagal:</strong> ${error.message}
                        <br><small class="text-muted">Pastikan service Python (FastAPI) sudah berjalan di http://localhost:8000</small>
                    </div>
                `;
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-play-fill"></i> Jalankan Proses Segmentasi';
            });
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert-flash').forEach(el => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
