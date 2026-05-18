<?php
/**
 * Top Header / Navbar Component
 * POS Application
 */
?>
<!-- Top Header -->
<header class="topbar">
    <div class="topbar-left">
        <button class="btn-toggle-sidebar" onclick="toggleSidebar()" id="btnToggleSidebar">
            <i class="bi bi-list"></i>
        </button>
        <div class="page-title">
            <h1><?= $pageTitle ?? 'Dashboard' ?></h1>
        </div>
    </div>
    <div class="topbar-right">
        <div class="topbar-item">
            <span class="topbar-date">
                <i class="bi bi-calendar3"></i>
                <?= date('l, d F Y') ?>
            </span>
        </div>
        <div class="topbar-divider"></div>
        <div class="topbar-item dropdown">
            <a href="#" class="topbar-user" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="topbar-user-avatar">
                    <i class="bi bi-person-circle"></i>
                </div>
                <span class="topbar-user-name d-none d-md-inline"><?= e($currentUser['nama_lengkap']) ?></span>
                <i class="bi bi-chevron-down ms-1"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <div class="dropdown-header">
                        <strong><?= e($currentUser['nama_lengkap']) ?></strong>
                        <small class="d-block text-muted"><?= ucfirst(e($currentUser['role'])) ?></small>
                    </div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="profil.php">
                        <i class="bi bi-person me-2"></i>Profil Saya
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
