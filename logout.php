<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

if ($u = current_user()) {
    log_action($pdo, $u['id'], 'Logout', 'User logged out');
}
$_SESSION = [];
session_destroy();
redirect(BASE_URL . 'login.php');
