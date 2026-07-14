<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_role('treasurer');

$pageTitle = 'Expenses';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $date = $_POST['expense_date'] ?? date('Y-m-d');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $receipt = trim($_POST['receipt_no'] ?? '');
        $payment = trim($_POST['payment_method'] ?? '');
        $fund = trim($_POST['fund_source'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($amount <= 0 || $category === '') {
            flash('error', 'Please provide a valid category and amount.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO expenses (expense_date, category, description, amount, receipt_no, payment_method, fund_source, entered_by, remarks, status)
                                    VALUES (?,?,?,?,?,?,?,?,?, 'Pending')");
            $stmt->execute([$date, $category, $description, $amount, $receipt, $payment, $fund, current_user()['id'], $remarks]);
            log_action($pdo, current_user()['id'], 'Add Expense', "Submitted {$category} expense of " . money($amount) . " for approval");
            flash('success', 'Expense recorded and routed for approval.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT status FROM expenses WHERE id=?");
        $stmt->execute([$id]);
        $st = $stmt->fetchColumn();
        if ($st === 'Approved') {
            flash('error', 'Approved expenses cannot be deleted.');
        } else {
            $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([$id]);
            flash('success', 'Expense deleted.');
        }
    }
    redirect(BASE_URL . 'expenses/index.php' . (!empty($_GET['period']) ? '?period=' . urlencode($_GET['period']) : ''));
}

$period = $_GET['period'] ?? 'monthly';
[$start, $end] = period_range($period);

$stmt = $pdo->prepare("SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC, id DESC");
$stmt->execute([$start, $end]);
$entries = $stmt->fetchAll();
$periodTotal = array_sum(array_column($entries, 'amount'));
$approvedTotal = array_sum(array_map(fn($e) => $e['status']==='Approved' ? $e['amount'] : 0, $entries));

function expense_summary($pdo, $period) {
    $rows = [];
    if ($period === 'weekly') {
        $stmt = $pdo->query("SELECT YEARWEEK(expense_date,3) yw, MIN(expense_date) wk_start, SUM(amount) total FROM expenses WHERE status='Approved' GROUP BY yw ORDER BY yw DESC LIMIT 8");
        foreach ($stmt as $r) $rows[] = ['label' => 'Week of ' . date('M j, Y', strtotime($r['wk_start'])), 'total' => $r['total']];
    } elseif ($period === 'monthly') {
        $stmt = $pdo->query("SELECT DATE_FORMAT(expense_date,'%Y-%m') ym, SUM(amount) total FROM expenses WHERE status='Approved' GROUP BY ym ORDER BY ym DESC LIMIT 12");
        foreach ($stmt as $r) $rows[] = ['label' => date('F Y', strtotime($r['ym'].'-01')), 'total' => $r['total']];
    } elseif ($period === 'quarterly') {
        $stmt = $pdo->query("SELECT YEAR(expense_date) yr, QUARTER(expense_date) q, SUM(amount) total FROM expenses WHERE status='Approved' GROUP BY yr, q ORDER BY yr DESC, q DESC LIMIT 8");
        foreach ($stmt as $r) $rows[] = ['label' => 'Q' . $r['q'] . ' ' . $r['yr'], 'total' => $r['total']];
    } else {
        $stmt = $pdo->query("SELECT YEAR(expense_date) yr, SUM(amount) total FROM expenses WHERE status='Approved' GROUP BY yr ORDER BY yr DESC LIMIT 6");
        foreach ($stmt as $r) $rows[] = ['label' => $r['yr'], 'total' => $r['total']];
    }
    return $rows;
}
$summary = expense_summary($pdo, $period);
$categories = fixed_list('expense_categories');
$paymentMethods = fixed_list('payment_methods');
$fundSources = $pdo->query("SELECT item_name FROM budget_allocations WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-receipt text-success"></i> Expenses</h4>
  <div>
    <a href="approve.php" class="btn btn-outline-secondary me-2"><i class="bi bi-check2-square"></i> Approvals Queue</a>
    <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#addExpenseModal"><i class="bi bi-plus-lg"></i> Record Expense</button>
  </div>
</div>

<ul class="nav nav-pills mb-3">
  <?php foreach (['weekly'=>'Weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'] as $key => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $period===$key?'active btn-brand':'' ?>" href="?period=<?= $key ?>"><?= $label ?></a></li>
  <?php endforeach; ?>
</ul>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>Entries for <?= h(ucfirst($period)) ?> Period (<?= h($start) ?> to <?= h($end) ?>)</span>
        <span>Total: <b><?= money($periodTotal) ?></b> &nbsp;|&nbsp; Approved: <b class="text-success"><?= money($approvedTotal) ?></b></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($entries as $e): ?>
            <tr>
              <td><?= h($e['expense_date']) ?></td>
              <td><?= h($e['category']) ?></td>
              <td><?= h($e['description']) ?></td>
              <td><?= money($e['amount']) ?></td>
              <td><span class="badge bg-<?= $e['status']==='Approved'?'success':($e['status']==='Rejected'?'danger':'warning text-dark') ?>"><?= h($e['status']) ?></span></td>
              <td class="text-end">
                <?php if ($e['status'] !== 'Approved'): ?>
                <form method="post" onsubmit="return confirm('Delete this expense?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $e['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($entries)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No expense entries for this period.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Approved Expense Summary (<?= h(ucfirst($period)) ?>)</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Period</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($summary as $s): ?>
            <tr><td><?= h($s['label']) ?></td><td><?= money($s['total']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (empty($summary)): ?>
            <tr><td colspan="2" class="text-center text-muted py-3">No data yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Record Expense</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-2">
          <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category" class="form-select" required>
              <?php foreach ($categories as $c): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-12"><label class="form-label">Description</label><input type="text" name="description" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Receipt No.</label><input type="text" name="receipt_no" class="form-control"></div>
          <div class="col-md-6">
            <label class="form-label">Payment Method</label>
            <select name="payment_method" class="form-select">
              <?php foreach ($paymentMethods as $p): ?><option value="<?= h($p) ?>"><?= h($p) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Fund Source</label>
            <select name="fund_source" class="form-select">
              <?php foreach ($fundSources as $f): ?><option value="<?= h($f) ?>"><?= h($f) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Submit for Approval</button></div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
