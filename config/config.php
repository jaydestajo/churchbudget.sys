<?php
/**
 * Global configuration & database connection.
 * Edit the DB_* constants below to match your MySQL setup.
 */

// ---- Database settings ----
// Reads from environment variables first (needed for Vercel / any host where
// you can't edit this file directly, e.g. Railway, Render, PlanetScale-style
// setups). Falls back to the literal values below for local XAMPP/MAMP use.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'budget_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

// ---- App settings ----
define('APP_NAME', 'Budgeting & Expense Management System');
define('BASE_URL', getenv('BASE_URL') ?: '/budget-system/'); // change if the app lives in a sub-folder, e.g. '/budget-system/'
define('CURRENCY', '₱');
define('APP_ENV', getenv('APP_ENV') ?: 'local'); // 'local' | 'production'

if (APP_ENV === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // never show raw errors/stack traces to visitors in production
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    if (APP_ENV === 'production') {
        die('Database connection failed. Please check server configuration.');
    }
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
