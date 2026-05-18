<?php
/**
 * Sidebar Navigation Component
 * POS Application
 */
?>
<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="bi bi-shop"></i>
        </div>
        <div class="brand-text">
            <span class="brand-name">POS System</span>
            <span class="brand-sub">Point of Sale</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?= isActive('dashboard') ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Data Master -->
            <li class="nav-section">
                <span class="nav-section-title">DATA MASTER</span>
            </li>
            <li class="nav-item">
                <a href="pengguna.php" class="nav-link <?= isActive('pengguna') ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Pengguna</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="pelanggan.php" class="nav-link <?= isActive('pelanggan') ?>">
                    <i class="bi bi-person-vcard-fill"></i>
                    <span>Pelanggan</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="produk.php" class="nav-link <?= isActive('produk') ?>">
                    <i class="bi bi-box-seam-fill"></i>
                    <span>Produk</span>
                </a>
            </li>

            <!-- Transaksi -->
            <li class="nav-section">
                <span class="nav-section-title">TRANSAKSI</span>
            </li>
            <li class="nav-item">
                <a href="transaksi.php" class="nav-link <?= isActive('transaksi') ?>">
                    <i class="bi bi-cart-check-fill"></i>
                    <span>Transaksi</span>
                </a>
            </li>

            <!-- Laporan -->
            <li class="nav-section">
                <span class="nav-section-title">LAPORAN</span>
            </li>
            <li class="nav-item">
                <a href="laporan_pelanggan.php" class="nav-link <?= isActive('laporan_pelanggan') ?>">
                    <i class="bi bi-file-person-fill"></i>
                    <span>Laporan Pelanggan</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="laporan_produk.php" class="nav-link <?= isActive('laporan_produk') ?>">
                    <i class="bi bi-file-bar-graph-fill"></i>
                    <span>Laporan Produk</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="laporan_transaksi.php" class="nav-link <?= isActive('laporan_transaksi') ?>">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span>Laporan Transaksi</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="bi bi-person-circle"></i>
            </div>
            <div class="user-details">
                <span class="user-name"><?= e($currentUser['nama_lengkap']) ?></span>
                <span class="user-role"><?= ucfirst(e($currentUser['role'])) ?></span>
            </div>
        </div>
    </div>
</aside>
