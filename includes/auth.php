<?php
/**
 * Authentication Guard
 * Include this file in pages that require authentication
 */

require_once __DIR__ . '/../config/app.php';

if (!isLoggedIn()) {
    setFlash('warning', 'Silakan login terlebih dahulu.');
    redirect('login.php');
}

// Get current user data
$db = getDB();
$stmt = $db->prepare("SELECT id, nama_lengkap, username, email, role, foto FROM users WHERE id = ? AND status = 'aktif'");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    session_destroy();
    redirect('login.php');
}
