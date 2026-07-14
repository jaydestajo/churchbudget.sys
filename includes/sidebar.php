<?php
$user = current_user();
$role = $user['role'] ?? '';
$isSuperAdmin = $role === 'super_admin';
$isTreasurer = $role === 'treasurer';
$isApprover = (bool)approver_level();
$current = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function nav_active($dir, $file, $currentDir, $current) {
    return ($currentDir === $dir && $current === $file) ? 'active' : '';
}
?>
<div class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebarOffcanvas">
  <div class="offcanvas-body p-0">
    <ul class="nav flex-column py-2">

      <li class="nav-item">
        <a class="nav-link <?= ($current==='dashboard.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>dashboard.php">
          <i class="bi bi-speedometer2"></i> Dashboard
        </a>
      </li>

      <?php if ($isSuperAdmin || $isTreasurer): ?>
      <li class="nav-item"><div class="nav-section">Operations</div></li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('income','index.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>income/index.php">
          <i class="bi bi-cash-coin"></i> Income
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('expenses','index.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>expenses/index.php">
          <i class="bi bi-receipt"></i> Expenses
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('denomination','index.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>denomination/index.php">
          <i class="bi bi-cash-stack"></i> Denomination
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('assets_module','index.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>assets_module/index.php">
          <i class="bi bi-building"></i> Assets
        </a>
      </li>
      <?php endif; ?>

      <?php if ($isApprover || $isSuperAdmin): ?>
      <li class="nav-item"><div class="nav-section">Approvals</div></li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('expenses','approve.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>expenses/approve.php">
          <i class="bi bi-check2-square"></i> Approve Expenses
        </a>
      </li>
      <?php endif; ?>

      <li class="nav-item"><div class="nav-section">Reports</div></li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('reports','budget_allocation.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>reports/budget_allocation.php">
          <i class="bi bi-pie-chart"></i> Budget Allocation
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('reports','expense_report.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>reports/expense_report.php">
          <i class="bi bi-file-earmark-bar-graph"></i> Expense Report
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('reports','income_statement.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>reports/income_statement.php">
          <i class="bi bi-file-earmark-text"></i> Income Statement
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('reports','balance_sheet.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>reports/balance_sheet.php">
          <i class="bi bi-bank"></i> Balance Sheet
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('reports','cash_flow.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>reports/cash_flow.php">
          <i class="bi bi-arrow-left-right"></i> Cash Flow
        </a>
      </li>

      <?php if ($isSuperAdmin): ?>
      <li class="nav-item"><div class="nav-section">Settings</div></li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('settings','budget_allocation.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>settings/budget_allocation.php">
          <i class="bi bi-sliders"></i> Budget Allocation
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('settings','approvers.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>settings/approvers.php">
          <i class="bi bi-person-check"></i> Approvers
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('settings','treasurer.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>settings/treasurer.php">
          <i class="bi bi-person-badge"></i> Treasurer
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= nav_active('settings','users.php',$currentDir,$current) ?>" href="<?= BASE_URL ?>settings/users.php">
          <i class="bi bi-people"></i> Users
        </a>
      </li>
      <?php endif; ?>

    </ul>
  </div>
</div>
