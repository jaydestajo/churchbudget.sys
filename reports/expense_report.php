<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Expense Report';

$period = $_GET['period'] ?? 'monthly';
$category = $_GET['category'] ?? '';
$customStart = $_GET['start'] ?? '';
$customEnd = $_GET['end'] ?? '';

if ($period === 'custom' && $customStart && $customEnd) {
    [$start, $end] = period_range('custom', null, $customStart, $customEnd);
} else {
    [$start, $end] = period_range($period);
}

$sql = "SELECT * FROM expenses WHERE status='Approved' AND expense_date BETWEEN ? AND ?";
$params = [$start, $end];
if ($category) { $sql .= " AND category = ?"; $params[] = $category; }
$sql .= " ORDER BY expense_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$grandTotal = array_sum(array_column($rows, 'amount'));

// Totals per category (within selected date range, ignoring category filter)
$stmt2 = $pdo->prepare("SELECT category, SUM(amount) total FROM expenses WHERE status='Approved' AND expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
$stmt2->execute([$start, $end]);
$byCategory = $stmt2->fetchAll();

$categories = fixed_list('expense_categories');

require __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-file-earmark-bar-graph text-success"></i> Expense Report</h4>

<form method="get" class="row g-2 mb-3 align-items-end">
  <div class="col-auto">
    <label class="form-label small">Period</label>
    <select name="period" class="form-select form-select-sm" onchange="this.form.submit()">
      <?php foreach (['weekly'=>'Weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly','custom'=>'Custom Range'] as $k=>$l): ?>
        <option value="<?= $k ?>" <?= $period===$k?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php if ($period === 'custom'): ?>
  <div class="col-auto"><label class="form-label small">Start</label><input type="date" name="start" class="form-control form-control-sm" value="<?= h($customStart) ?>"></div>
  <div class="col-auto"><label class="form-label small">End</label><input type="date" name="end" class="form-control form-control-sm" value="<?= h($customEnd) ?>"></div>
  <?php endif; ?>
  <div class="col-auto">
    <label class="form-label small">Category</label>
    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?><option value="<?= h($c) ?>" <?= $category===$c?'selected':'' ?>><?= h($c) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-brand btn-sm">Filter</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span><?= h(ucfirst($period)) ?> (<?= h($start) ?> to <?= h($end) ?>)<?= $category ? ' — ' . h($category) : '' ?></span>
        <span>Grand Total: <b><?= money($grandTotal) ?></b></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Payment Method</th><th>Fund Source</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h($r['expense_date']) ?></td>
              <td><?= h($r['category']) ?></td>
              <td><?= h($r['description']) ?></td>
              <td><?= money($r['amount']) ?></td>
              <td><?= h($r['payment_method']) ?></td>
              <td><?= h($r['fund_source']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No approved expenses in this range.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Totals Per Category</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Category</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($byCategory as $b): ?>
            <tr><td><?= h($b['category']) ?></td><td><?= money($b['total']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
