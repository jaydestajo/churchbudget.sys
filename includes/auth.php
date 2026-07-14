<?php
/**
 * Authentication & role-based access control.
 * Include this AFTER config.php and functions.php on every protected page.
 */

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (!current_user()) {
        redirect(BASE_URL . 'login.php');
    }
}

/**
 * Roles allowed to fully manage a module.
 * super_admin always has full access to everything.
 */
function has_role(...$roles) {
    $user = current_user();
    if (!$user) return false;
    if ($user['role'] === 'super_admin') return true;
    return in_array($user['role'], $roles, true);
}

function require_role(...$roles) {
    require_login();
    if (!has_role(...$roles)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
            <h2>403 - Access Denied</h2>
            <p>You do not have permission to view this page.</p>
            <a href="' . BASE_URL . 'dashboard.php">&larr; Back to Dashboard</a></div>');
    }
}

/** Is this user one of the 4 approvers? Returns level (1-4) or false */
function approver_level() {
    $user = current_user();
    if (!$user) return false;
    if (preg_match('/^approver_(\d)$/', $user['role'], $m)) {
        return (int)$m[1];
    }
    return false;
}
