<?php
/**
 * Shared helper functions.
 */

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function money($amount) {
    return CURRENCY . number_format((float)$amount, 2);
}

function redirect($path) {
    header("Location: " . $path);
    exit;
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        die('Invalid CSRF token. Please go back and try again.');
    }
}

function log_action($pdo, $userId, $action, $details = '') {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $action, $details]);
}

/**
 * Compute date range boundaries for a given period filter.
 * $period: 'weekly' | 'monthly' | 'quarterly' | 'yearly' | 'custom'
 */
function period_range($period, $refDate = null, $customStart = null, $customEnd = null) {
    $refDate = $refDate ? new DateTime($refDate) : new DateTime();
    switch ($period) {
        case 'weekly':
            $start = clone $refDate;
            $start->modify('monday this week');
            $end = clone $start;
            $end->modify('+6 days');
            break;
        case 'monthly':
            $start = new DateTime($refDate->format('Y-m-01'));
            $end = clone $start;
            $end->modify('last day of this month');
            break;
        case 'quarterly':
            $month = (int)$refDate->format('n');
            $quarterStartMonth = floor(($month - 1) / 3) * 3 + 1;
            $start = new DateTime($refDate->format('Y') . '-' . str_pad($quarterStartMonth, 2, '0', STR_PAD_LEFT) . '-01');
            $end = clone $start;
            $end->modify('+2 months')->modify('last day of this month');
            break;
        case 'yearly':
            $start = new DateTime($refDate->format('Y') . '-01-01');
            $end = new DateTime($refDate->format('Y') . '-12-31');
            break;
        case 'custom':
            $start = new DateTime($customStart ?: $refDate->format('Y-m-01'));
            $end = new DateTime($customEnd ?: $refDate->format('Y-m-t'));
            break;
        default:
            $start = new DateTime($refDate->format('Y-m-01'));
            $end = clone $start;
            $end->modify('last day of this month');
    }
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function fixed_list($name) {
    $lists = [
        'income_sources' => ['Tithes', 'Offering', 'Donation', 'Fund Raising', 'Rental', 'Other Income'],
        'expense_categories' => ['Utilities', 'Office Supplies', 'Transportation', 'Repairs', 'Salaries', 'Ministry', 'Outreach', 'Building', 'Others'],
        'asset_categories' => ['Land', 'Building', 'Vehicle', 'Furniture', 'Equipment', 'Computers', 'Appliances', 'Others'],
        'payment_methods' => ['Cash', 'Bank Transfer', 'Check', 'GCash', 'Others'],
        'asset_conditions' => ['New', 'Good', 'Fair', 'Poor', 'Disposed'],
    ];
    return $lists[$name] ?? [];
}
