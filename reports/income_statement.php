<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Income Statement';

$period = $_GET['period'] ?? 'monthly';
[$start, $end] = period_range($period);

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM income WHERE income_date BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$totalIncome = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status='Approved' AND expense_date BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$totalExpenses = (float)$stmt->fetchColumn();

$netIncome = $totalIncome - $totalExpenses;

// Breakdown
$stmt = $pdo->prepare("SELECT source, SUM(amount) total FROM income WHERE income_date BETWEEN ? AND ? GROUP BY source ORDER BY total DESC");
$stmt->execute([$start, $end]);
$incomeBySource = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT category, SUM(amount) total FROM expenses WHERE status='Approved' AND expense_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
$stmt->execute([$start, $end]);
$expenseByCategory = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-file-earmark-text text-success"></i> Income Statement</h4>

<ul class="nav nav-pills mb-3">
  <?php foreach (['weekly'=>'Weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'] as $key => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $period===$key?'active btn-brand':'' ?>" href="?period=<?= $key ?>"><?= $label ?></a></li>
  <?php endforeach; ?>
</ul>
<p class="text-muted">Period: <?= h($start) ?> to <?= h($end) ?></p>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header bg-white fw-semibold">Income by Source</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <tbody>
          <?php foreach ($incomeBySource as $s): ?>
            <tr><td><?= h($s['source']) ?></td><td class="text-end"><?= money($s['total']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (empty($incomeBySource)): ?><tr><td class="text-muted">No income recorded.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header bg-white fw-semibold">Expenses by Category</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <tbody>
          <?php foreach ($expenseByCategory as $c): ?>
            <tr><td><?= h($c['category']) ?></td><td class="text-end"><?= money($c['total']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (empty($expenseByCategory)): ?><tr><td class="text-muted">No approved expenses.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header bg-white fw-semibold">Summary</div>
      <div class="card-body">
        <table class="table table-borderless mb-0">
          <tr><td>Total Income</td><td class="text-end fw-semibold"><?= money($totalIncome) ?></td></tr>
          <tr><td>(-) Total Expenses</td><td class="text-end fw-semibold text-danger">(<?= money($totalExpenses) ?>)</td></tr>
          <tr><td colspan="2"><hr></td></tr>
          <tr class="fs-5">
            <td class="fw-bold">Net Income</td>
            <td class="text-end fw-bold <?= $netIncome >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($netIncome) ?></td>
          </tr>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
