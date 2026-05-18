<?php
/**
 * Application Configuration
 * POS Application
 */

define('APP_NAME', 'POS System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/POS');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database config
require_once __DIR__ . '/database.php';

/**
 * Helper: Check if user is logged in
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Helper: Redirect to URL
 */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/**
 * Helper: Set flash message
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Helper: Get and clear flash message
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Helper: Format currency (Rupiah)
 */
function formatRupiah(float $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Helper: Sanitize output
 */
function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Helper: Get current page name from URL
 */
function currentPage(): string
{
    $page = basename($_SERVER['PHP_SELF'], '.php');
    return $page;
}

/**
 * Helper: Check if menu is active
 */
function isActive(string|array $pages): string
{
    $current = currentPage();
    if (is_array($pages)) {
        return in_array($current, $pages) ? 'active' : '';
    }
    return $current === $pages ? 'active' : '';
}
