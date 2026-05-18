<?php
/**
 * Index - Entry Point
 * Redirects to login or dashboard
 */

require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
