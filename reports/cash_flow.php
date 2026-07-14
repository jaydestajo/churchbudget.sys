<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Cash Flow Statement';

$period = $_GET['period'] ?? 'monthly';
[$start, $end] = period_range($period);

// Operating Activities
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM income WHERE income_date BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$incomeReceived = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status='Approved' AND expense_date BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$expensesPaid = (float)$stmt->fetchColumn();

$operatingNet = $incomeReceived - $expensesPaid;

// Investing Activities: asset purchases within period (using purchase_date & cost as proxy for cash out)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(cost),0) FROM assets WHERE purchase_date BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$assetPurchases = (float)$stmt->fetchColumn();
$assetSales = 0.0; // No dedicated asset-disposal tracking yet; kept as a placeholder for manual entry via remarks
$investingNet = $assetSales - $assetPurchases;

// Financing Activities: loans & donations recorded within period
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM balance_sheet_items WHERE item_type='Loan' AND as_of_date BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$loanProceeds = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM income WHERE source='Donation' AND income_date BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$donations = (float)$stmt->fetchColumn();
$financingNet = $loanProceeds; // Donations already counted in operating income above; shown here as memo only

// Beginning cash = all denomination totals recorded BEFORE the period start
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM denomination_entries WHERE entry_date < ?");
$stmt->execute([$start]);
$beginningCash = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM denomination_entries WHERE entry_date BETWEEN ? AND ?");
$stmt->execute([$start, $end]);
$cashInDuringPeriod = (float)$stmt->fetchColumn();

$endingCash = $beginningCash + $cashInDuringPeriod;

require __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-arrow-left-right text-success"></i> Cash Flow Statement</h4>

<ul class="nav nav-pills mb-3">
  <?php foreach (['weekly'=>'Weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'] as $key => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $period===$key?'active btn-brand':'' ?>" href="?period=<?= $key ?>"><?= $label ?></a></li>
  <?php endforeach; ?>
</ul>
<p class="text-muted">Period: <?= h($start) ?> to <?= h($end) ?></p>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header bg-white fw-semibold">Cash Flow Detail</div>
      <div class="card-body">
        <h6 class="text-muted">Operating Activities</h6>
        <table class="table table-sm">
          <tr><td>Income Received</td><td class="text-end"><?= money($incomeReceived) ?></td></tr>
          <tr><td>Expenses Paid</td><td class="text-end">(<?= money($expensesPaid) ?>)</td></tr>
          <tr class="fw-semibold"><td>Net Operating Cash Flow</td><td class="text-end"><?= money($operatingNet) ?></td></tr>
        </table>

        <h6 class="text-muted mt-3">Investing Activities</h6>
        <table class="table table-sm">
          <tr><td>Asset Purchases</td><td class="text-end">(<?= money($assetPurchases) ?>)</td></tr>
          <tr><td>Asset Sales</td><td class="text-end"><?= money($assetSales) ?></td></tr>
          <tr class="fw-semibold"><td>Net Investing Cash Flow</td><td class="text-end"><?= money($investingNet) ?></td></tr>
        </table>

        <h6 class="text-muted mt-3">Financing Activities</h6>
        <table class="table table-sm">
          <tr><td>Loan Proceeds</td><td class="text-end"><?= money($loanProceeds) ?></td></tr>
          <tr><td class="text-muted small">Donations (memo, included in Income above)</td><td class="text-end text-muted small"><?= money($donations) ?></td></tr>
          <tr class="fw-semibold"><td>Net Financing Cash Flow</td><td class="text-end"><?= money($financingNet) ?></td></tr>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header bg-white fw-semibold">Summary</div>
      <div class="card-body">
        <table class="table table-borderless mb-0">
          <tr><td>Beginning Balance</td><td class="text-end fw-semibold"><?= money($beginningCash) ?></td></tr>
          <tr><td>(+) Cash In (recorded this period)</td><td class="text-end fw-semibold text-success"><?= money($cashInDuringPeriod) ?></td></tr>
          <tr><td colspan="2"><hr></td></tr>
          <tr class="fs-5"><td class="fw-bold">Ending Balance</td><td class="text-end fw-bold"><?= money($endingCash) ?></td></tr>
        </table>
        <p class="text-muted small mt-2">Note: Cash In/Out figures are derived from recorded denomination entries. Operating, investing, and financing sections above reflect income, expenses, assets, and loan/donation ledger records for the selected period.</p>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
