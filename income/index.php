<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_role('treasurer');

$pageTitle = 'Income';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $date = $_POST['income_date'] ?? date('Y-m-d');
        $source = trim($_POST['source'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $ref = trim($_POST['reference_number'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($amount <= 0 || $source === '') {
            flash('error', 'Please provide a valid source and amount.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO income (income_date, source, amount, reference_number, remarks, entered_by) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$date, $source, $amount, $ref, $remarks, current_user()['id']]);
            log_action($pdo, current_user()['id'], 'Add Income', "Recorded {$source} income of " . money($amount));
            flash('success', 'Income entry recorded.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM income WHERE id=?")->execute([$id]);
        flash('success', 'Income entry deleted.');
    }
    redirect(BASE_URL . 'income/index.php' . (!empty($_GET['period']) ? '?period=' . urlencode($_GET['period']) : ''));
}

// ---- Period filter for report ----
$period = $_GET['period'] ?? 'monthly';
[$start, $end] = period_range($period);

$stmt = $pdo->prepare("SELECT * FROM income WHERE income_date BETWEEN ? AND ? ORDER BY income_date DESC, id DESC");
$stmt->execute([$start, $end]);
$entries = $stmt->fetchAll();
$periodTotal = array_sum(array_column($entries, 'amount'));

// All-time summaries by period type (for the quick summary table)
function income_summary($pdo, $period) {
    $rows = [];
    if ($period === 'weekly') {
        $stmt = $pdo->query("SELECT YEARWEEK(income_date,3) yw, MIN(income_date) wk_start, SUM(amount) total FROM income GROUP BY yw ORDER BY yw DESC LIMIT 8");
        foreach ($stmt as $r) $rows[] = ['label' => 'Week of ' . date('M j, Y', strtotime($r['wk_start'])), 'total' => $r['total']];
    } elseif ($period === 'monthly') {
        $stmt = $pdo->query("SELECT DATE_FORMAT(income_date,'%Y-%m') ym, SUM(amount) total FROM income GROUP BY ym ORDER BY ym DESC LIMIT 12");
        foreach ($stmt as $r) $rows[] = ['label' => date('F Y', strtotime($r['ym'].'-01')), 'total' => $r['total']];
    } elseif ($period === 'quarterly') {
        $stmt = $pdo->query("SELECT YEAR(income_date) yr, QUARTER(income_date) q, SUM(amount) total FROM income GROUP BY yr, q ORDER BY yr DESC, q DESC LIMIT 8");
        foreach ($stmt as $r) $rows[] = ['label' => 'Q' . $r['q'] . ' ' . $r['yr'], 'total' => $r['total']];
    } else {
        $stmt = $pdo->query("SELECT YEAR(income_date) yr, SUM(amount) total FROM income GROUP BY yr ORDER BY yr DESC LIMIT 6");
        foreach ($stmt as $r) $rows[] = ['label' => $r['yr'], 'total' => $r['total']];
    }
    return $rows;
}
$summary = income_summary($pdo, $period);
$sources = fixed_list('income_sources');

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-cash-coin text-success"></i> Income</h4>
  <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#addIncomeModal"><i class="bi bi-plus-lg"></i> Record Income</button>
</div>

<ul class="nav nav-pills mb-3">
  <?php foreach (['weekly'=>'Weekly','monthly'=>'Monthly','quarterly'=>'Quarterly','yearly'=>'Yearly'] as $key => $label): ?>
    <li class="nav-item"><a class="nav-link <?= $period===$key?'active btn-brand':'' ?>" href="?period=<?= $key ?>"><?= $label ?></a></li>
  <?php endforeach; ?>
</ul>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>Entries for <?= h(ucfirst($period)) ?> Period (<?= h($start) ?> to <?= h($end) ?>)</span>
        <span class="badge bg-success"><?= money($periodTotal) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Date</th><th>Source</th><th>Amount</th><th>Reference</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($entries as $e): ?>
            <tr>
              <td><?= h($e['income_date']) ?></td>
              <td><?= h($e['source']) ?></td>
              <td><?= money($e['amount']) ?></td>
              <td><?= h($e['reference_number']) ?></td>
              <td class="text-end">
                <form method="post" onsubmit="return confirm('Delete this entry?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $e['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($entries)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No income entries for this period.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Income Summary (<?= h(ucfirst($period)) ?>)</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Period</th><th>Income</th></tr></thead>
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

<div class="modal fade" id="addIncomeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Record Income</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Date</label><input type="date" name="income_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
          <div class="mb-2">
            <label class="form-label">Income Source</label>
            <select name="source" class="form-select" required>
              <?php foreach ($sources as $s): ?><option value="<?= h($s) ?>"><?= h($s) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Reference Number</label><input type="text" name="reference_number" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Save</button></div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
