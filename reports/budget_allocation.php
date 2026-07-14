<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Budget Allocation Report';

$totalIncome = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM income")->fetchColumn();
$allocations = $pdo->query("SELECT * FROM budget_allocations WHERE is_active=1 ORDER BY percentage DESC")->fetchAll();

// Approved expenses per fund_source (to compute remaining balance per allocation)
$stmt = $pdo->query("SELECT fund_source, SUM(amount) total FROM expenses WHERE status='Approved' GROUP BY fund_source");
$expenseByFund = [];
foreach ($stmt as $row) { $expenseByFund[$row['fund_source']] = (float)$row['total']; }

require __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-pie-chart text-success"></i> Budget Allocation Report</h4>
<p class="text-muted">Total Income to date: <b><?= money($totalIncome) ?></b></p>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Budget Item</th><th>Percentage</th><th>Allocated Amount</th><th>Spent (Approved)</th><th>Remaining Balance</th></tr></thead>
      <tbody>
      <?php $totalAllocated = 0; $totalSpent = 0; foreach ($allocations as $a):
          $allocated = $totalIncome * $a['percentage']/100;
          $spent = $expenseByFund[$a['item_name']] ?? 0;
          $remaining = $allocated - $spent;
          $totalAllocated += $allocated; $totalSpent += $spent;
      ?>
        <tr>
          <td><?= h($a['item_name']) ?></td>
          <td><?= number_format($a['percentage'],2) ?>%</td>
          <td><?= money($allocated) ?></td>
          <td><?= money($spent) ?></td>
          <td class="<?= $remaining < 0 ? 'text-danger fw-bold' : '' ?>"><?= money($remaining) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light fw-bold">
        <tr><td>Total</td><td><?= number_format(array_sum(array_column($allocations,'percentage')),2) ?>%</td><td><?= money($totalAllocated) ?></td><td><?= money($totalSpent) ?></td><td><?= money($totalAllocated - $totalSpent) ?></td></tr>
      </tfoot>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
