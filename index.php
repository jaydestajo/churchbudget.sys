<?php
/**
 * Front controller / entry point.
 * Keeps index.php working as the default document while the actual
 * dashboard logic lives in dashboard.php.
 */
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';

redirect(BASE_URL . (current_user() ? 'dashboard.php' : 'login.php'));
