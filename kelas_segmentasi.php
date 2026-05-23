<?php
/**
 * CRUD Kelas Segmentasi
 * POS Application
 */

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Kelas Segmentasi';
$db = getDB();

// ============================================
// HANDLE ACTIONS
// ============================================

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$formData = [
    'nama_kelas' => '',
    'kelas_recency' => '',
    'kelas_frequency' => '',
    'kelas_monetary' => '',
    'deskripsi' => '',
];

// Handle POST (Create / Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'nama_kelas' => trim($_POST['nama_kelas'] ?? ''),
        'kelas_recency' => (int)($_POST['kelas_recency'] ?? 0),
        'kelas_frequency' => (int)($_POST['kelas_frequency'] ?? 0),
        'kelas_monetary' => (int)($_POST['kelas_monetary'] ?? 0),
        'deskripsi' => trim($_POST['deskripsi'] ?? ''),
    ];

    // Validation
    if (empty($formData['nama_kelas'])) {
        $errors[] = 'Nama kelas wajib diisi.';
    }
    if (!in_array($formData['kelas_recency'], [1, 2, 3])) {
        $errors[] = 'Kelas Recency harus bernilai 1, 2, atau 3.';
    }
    if (!in_array($formData['kelas_frequency'], [1, 2, 3])) {
        $errors[] = 'Kelas Frequency harus bernilai 1, 2, atau 3.';
    }
    if (!in_array($formData['kelas_monetary'], [1, 2, 3])) {
        $errors[] = 'Kelas Monetary harus bernilai 1, 2, atau 3.';
    }

    // Check unique combination
    if (empty($errors)) {
        $checkSql = "SELECT id FROM kelas_segmentasi WHERE kelas_recency = :r AND kelas_frequency = :f AND kelas_monetary = :m";
        $params = [':r' => $formData['kelas_recency'], ':f' => $formData['kelas_frequency'], ':m' => $formData['kelas_monetary']];

        if ($action === 'edit' && $id > 0) {
            $checkSql .= " AND id != :id";
            $params[':id'] = $id;
        }

        $stmt = $db->prepare($checkSql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $errors[] = 'Kombinasi R-F-M ini sudah ada.';
        }
    }

    if (empty($errors)) {
        if ($action === 'create') {
            $stmt = $db->prepare("
                INSERT INTO kelas_segmentasi (nama_kelas, kelas_recency, kelas_frequency, kelas_monetary, deskripsi)
                VALUES (:nama, :r, :f, :m, :desk)
            ");
            $stmt->execute([
                ':nama' => $formData['nama_kelas'],
                ':r' => $formData['kelas_recency'],
                ':f' => $formData['kelas_frequency'],
                ':m' => $formData['kelas_monetary'],
                ':desk' => $formData['deskripsi'] ?: null,
            ]);
            setFlash('success', 'Kelas segmentasi berhasil ditambahkan.');
            redirect('kelas_segmentasi.php');
        } elseif ($action === 'edit' && $id > 0) {
            $stmt = $db->prepare("
                UPDATE kelas_segmentasi
                SET nama_kelas = :nama, kelas_recency = :r, kelas_frequency = :f, kelas_monetary = :m, deskripsi = :desk
                WHERE id = :id
            ");
            $stmt->execute([
                ':nama' => $formData['nama_kelas'],
                ':r' => $formData['kelas_recency'],
                ':f' => $formData['kelas_frequency'],
                ':m' => $formData['kelas_monetary'],
                ':desk' => $formData['deskripsi'] ?: null,
                ':id' => $id,
            ]);
            setFlash('success', 'Kelas segmentasi berhasil diperbarui.');
            redirect('kelas_segmentasi.php');
        }
    }
}

// Handle Delete
if ($action === 'delete' && $id > 0) {
    // Check if used in cluster
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM cluster WHERE kelas_segmentasi_id = :id");
    $stmt->execute([':id' => $id]);
    $used = $stmt->fetch()['total'];

    if ($used > 0) {
        setFlash('error', 'Kelas segmentasi tidak dapat dihapus karena sedang digunakan oleh data cluster.');
    } else {
        $stmt = $db->prepare("DELETE FROM kelas_segmentasi WHERE id = :id");
        $stmt->execute([':id' => $id]);
        setFlash('success', 'Kelas segmentasi berhasil dihapus.');
    }
    redirect('kelas_segmentasi.php');
}

// Load data for edit
if ($action === 'edit' && $id > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $db->prepare("SELECT * FROM kelas_segmentasi WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        setFlash('error', 'Data tidak ditemukan.');
        redirect('kelas_segmentasi.php');
    }
    $formData = [
        'nama_kelas' => $row['nama_kelas'],
        'kelas_recency' => $row['kelas_recency'],
        'kelas_frequency' => $row['kelas_frequency'],
        'kelas_monetary' => $row['kelas_monetary'],
        'deskripsi' => $row['deskripsi'] ?? '',
    ];
}

// ============================================
// QUERY: List Data
// ============================================
$search = trim($_GET['search'] ?? '');
$listSql = "SELECT * FROM kelas_segmentasi";
$listParams = [];

if ($search !== '') {
    $listSql .= " WHERE nama_kelas LIKE :search OR deskripsi LIKE :search";
    $listParams[':search'] = "%$search%";
}
$listSql .= " ORDER BY kelas_recency ASC, kelas_frequency ASC, kelas_monetary ASC";

$stmt = $db->prepare($listSql);
$stmt->execute($listParams);
$kelasSegmentasi = $stmt->fetchAll();

// Labels
$kelasLabels = [1 => 'Rendah', 2 => 'Sedang', 3 => 'Tinggi'];
$kelasColors = [1 => 'success', 2 => 'warning', 3 => 'danger'];
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
                            <i class="bi bi-diagram-3-fill"></i> Kelas Segmentasi Pelanggan
                        </div>
                        <a href="kelas_segmentasi.php?action=create" class="btn btn-sm btn-primary" style="border-radius:8px;">
                            <i class="bi bi-plus-lg"></i> Tambah Kelas
                        </a>
                    </div>
                    <div class="panel-body">
                        <!-- Search -->
                        <form method="GET" class="mb-4">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" name="search" class="form-control" placeholder="Cari nama kelas atau deskripsi..." value="<?= e($search) ?>">
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary">Cari</button>
                                    <?php if ($search !== ''): ?>
                                        <a href="kelas_segmentasi.php" class="btn btn-outline-secondary">Reset</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <!-- Info -->
                        <div class="alert alert-info mb-4" style="font-size:13px; border-radius:10px;">
                            <i class="bi bi-info-circle"></i>
                            <strong>27 Kelas Segmentasi</strong> dari kombinasi 3 level Recency x 3 level Frequency x 3 level Monetary.
                            <br>Recency: <span class="badge bg-success">1=Rendah (Aktif)</span> <span class="badge bg-warning text-dark">2=Sedang</span> <span class="badge bg-danger">3=Tinggi (Tidak Aktif)</span>
                            <br>Frequency & Monetary: <span class="badge bg-success">3=Tinggi (Baik)</span> <span class="badge bg-warning text-dark">2=Sedang</span> <span class="badge bg-danger">1=Rendah</span>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table-modern">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">#</th>
                                        <th>Nama Kelas</th>
                                        <th style="text-align:center;">Recency</th>
                                        <th style="text-align:center;">Frequency</th>
                                        <th style="text-align:center;">Monetary</th>
                                        <th>Deskripsi</th>
                                        <th style="width:120px; text-align:center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($kelasSegmentasi)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox" style="font-size:24px;"></i>
                                            <p class="mt-2 mb-0">Belum ada data kelas segmentasi</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($kelasSegmentasi as $i => $kelas): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td style="font-weight:600;"><?= e($kelas['nama_kelas']) ?></td>
                                        <td style="text-align:center;">
                                            <span class="badge bg-<?= $kelasColors[$kelas['kelas_recency']] ?>">
                                                <?= $kelas['kelas_recency'] ?> - <?= $kelasLabels[$kelas['kelas_recency']] ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="badge bg-<?= $kelasColors[$kelas['kelas_frequency']] ?>">
                                                <?= $kelas['kelas_frequency'] ?> - <?= $kelasLabels[$kelas['kelas_frequency']] ?>
                                            </span>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="badge bg-<?= $kelasColors[$kelas['kelas_monetary']] ?>">
                                                <?= $kelas['kelas_monetary'] ?> - <?= $kelasLabels[$kelas['kelas_monetary']] ?>
                                            </span>
                                        </td>
                                        <td style="font-size:13px; color:var(--gray-600);">
                                            <?= e($kelas['deskripsi'] ?? '-') ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <a href="kelas_segmentasi.php?action=edit&id=<?= $kelas['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                                onclick="confirmDelete(<?= $kelas['id'] ?>, '<?= e($kelas['nama_kelas']) ?>')">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 text-muted" style="font-size:13px;">
                            Total: <strong><?= count($kelasSegmentasi) ?></strong> kelas segmentasi
                        </div>
                    </div>
                </div>

                <?php elseif ($action === 'create' || $action === 'edit'): ?>
                <!-- ============================================ -->
                <!-- FORM CREATE / EDIT -->
                <!-- ============================================ -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="bi bi-<?= $action === 'create' ? 'plus-circle' : 'pencil-square' ?>"></i>
                            <?= $action === 'create' ? 'Tambah' : 'Edit' ?> Kelas Segmentasi
                        </div>
                        <a href="kelas_segmentasi.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                    </div>
                    <div class="panel-body">
                        <!-- Validation Errors -->
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

                        <form method="POST" action="kelas_segmentasi.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
                            <div class="row g-3">
                                <!-- Nama Kelas -->
                                <div class="col-md-6">
                                    <label for="nama_kelas" class="form-label fw-semibold">Nama Kelas <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nama_kelas" name="nama_kelas"
                                        value="<?= e($formData['nama_kelas']) ?>" placeholder="Contoh: R1-F3-M3" required>
                                    <div class="form-text">Format yang disarankan: R[1-3]-F[1-3]-M[1-3]</div>
                                </div>

                                <!-- Kelas Recency -->
                                <div class="col-md-2">
                                    <label for="kelas_recency" class="form-label fw-semibold">Recency <span class="text-danger">*</span></label>
                                    <select class="form-select" id="kelas_recency" name="kelas_recency" required>
                                        <option value="">Pilih</option>
                                        <option value="1" <?= $formData['kelas_recency'] == 1 ? 'selected' : '' ?>>1 - Rendah (Aktif)</option>
                                        <option value="2" <?= $formData['kelas_recency'] == 2 ? 'selected' : '' ?>>2 - Sedang</option>
                                        <option value="3" <?= $formData['kelas_recency'] == 3 ? 'selected' : '' ?>>3 - Tinggi (Tidak Aktif)</option>
                                    </select>
                                </div>

                                <!-- Kelas Frequency -->
                                <div class="col-md-2">
                                    <label for="kelas_frequency" class="form-label fw-semibold">Frequency <span class="text-danger">*</span></label>
                                    <select class="form-select" id="kelas_frequency" name="kelas_frequency" required>
                                        <option value="">Pilih</option>
                                        <option value="1" <?= $formData['kelas_frequency'] == 1 ? 'selected' : '' ?>>1 - Rendah</option>
                                        <option value="2" <?= $formData['kelas_frequency'] == 2 ? 'selected' : '' ?>>2 - Sedang</option>
                                        <option value="3" <?= $formData['kelas_frequency'] == 3 ? 'selected' : '' ?>>3 - Tinggi</option>
                                    </select>
                                </div>

                                <!-- Kelas Monetary -->
                                <div class="col-md-2">
                                    <label for="kelas_monetary" class="form-label fw-semibold">Monetary <span class="text-danger">*</span></label>
                                    <select class="form-select" id="kelas_monetary" name="kelas_monetary" required>
                                        <option value="">Pilih</option>
                                        <option value="1" <?= $formData['kelas_monetary'] == 1 ? 'selected' : '' ?>>1 - Rendah</option>
                                        <option value="2" <?= $formData['kelas_monetary'] == 2 ? 'selected' : '' ?>>2 - Sedang</option>
                                        <option value="3" <?= $formData['kelas_monetary'] == 3 ? 'selected' : '' ?>>3 - Tinggi</option>
                                    </select>
                                </div>

                                <!-- Deskripsi -->
                                <div class="col-12">
                                    <label for="deskripsi" class="form-label fw-semibold">Deskripsi</label>
                                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"
                                        placeholder="Deskripsi karakteristik pelanggan pada kelas ini..."><?= e($formData['deskripsi']) ?></textarea>
                                </div>
                            </div>

                            <!-- Auto-generate name hint -->
                            <div class="alert alert-light mt-3 mb-4" style="font-size:13px; border-radius:10px;">
                                <i class="bi bi-lightbulb"></i> <strong>Tips:</strong> Nama kelas akan otomatis di-generate jika dikosongkan saat memilih nilai R, F, M.
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-<?= $action === 'create' ? 'plus-lg' : 'check-lg' ?>"></i>
                                    <?= $action === 'create' ? 'Simpan' : 'Perbarui' ?>
                                </button>
                                <a href="kelas_segmentasi.php" class="btn btn-outline-secondary">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /.content-area -->
        </div><!-- /.main-content -->
    </div><!-- /.app-wrapper -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle Sidebar (Mobile)
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }

        // Confirm Delete
        function confirmDelete(id, nama) {
            if (confirm('Yakin ingin menghapus kelas "' + nama + '"?\nData yang sudah dihapus tidak dapat dikembalikan.')) {
                window.location.href = 'kelas_segmentasi.php?action=delete&id=' + id;
            }
        }

        // Auto-generate nama kelas
        const recencyEl = document.getElementById('kelas_recency');
        const frequencyEl = document.getElementById('kelas_frequency');
        const monetaryEl = document.getElementById('kelas_monetary');
        const namaEl = document.getElementById('nama_kelas');

        function autoGenerateName() {
            if (!recencyEl || !frequencyEl || !monetaryEl || !namaEl) return;
            const r = recencyEl.value;
            const f = frequencyEl.value;
            const m = monetaryEl.value;
            if (r && f && m) {
                const current = namaEl.value.trim();
                const pattern = /^R[1-3]-F[1-3]-M[1-3]$/;
                if (current === '' || pattern.test(current)) {
                    namaEl.value = 'R' + r + '-F' + f + '-M' + m;
                }
            }
        }

        if (recencyEl) {
            recencyEl.addEventListener('change', autoGenerateName);
            frequencyEl.addEventListener('change', autoGenerateName);
            monetaryEl.addEventListener('change', autoGenerateName);
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
