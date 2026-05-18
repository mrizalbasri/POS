<?php
/**
 * Logout Handler
 * POS Application
 */

require_once __DIR__ . '/config/app.php';

// Destroy session
session_unset();
session_destroy();

// Start new session for flash message
session_start();
setFlash('success', 'Anda telah berhasil logout.');
redirect('login.php');
