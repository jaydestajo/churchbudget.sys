<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_role('treasurer');

$pageTitle = 'Denomination';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
    $bills = $_POST['bill'] ?? []; // [bill_value => quantity]

    $pdo->beginTransaction();
    try {
        foreach ($bills as $billValue => $qty) {
            $qty = (int)$qty;
            if ($qty <= 0) continue;
            $stmt = $pdo->prepare("INSERT INTO denomination_entries (entry_date, bill_value, quantity, created_by) VALUES (?,?,?,?)");
            $stmt->execute([$entryDate, $billValue, $qty, current_user()['id']]);
        }
        $pdo->commit();
        flash('success', 'Denomination entry recorded.');
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Failed to save denomination entry: ' . $e->getMessage());
    }
    redirect(BASE_URL . 'denomination/index.php');
}

$billMaster = $pdo->query("SELECT * FROM denomination_master ORDER BY bill_value DESC")->fetchAll();

// Aggregate current totals per denomination (all-time running count)
$stmt = $pdo->query("SELECT bill_value, SUM(quantity) qty, SUM(total) total FROM denomination_entries GROUP BY bill_value ORDER BY bill_value DESC");
$totalsByBill = [];
foreach ($stmt as $row) { $totalsByBill[$row['bill_value']] = $row; }
$grandTotal = array_sum(array_column($totalsByBill, 'total'));

// Recent entries log
$recent = $pdo->query("SELECT de.*, u.name AS user_name FROM denomination_entries de LEFT JOIN users u ON u.id=de.created_by ORDER BY de.created_at DESC LIMIT 15")->fetchAll();

// Weekly income + budget allocation for the report section
$totalIncome = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM income")->fetchColumn();
$allocations = $pdo->query("SELECT * FROM budget_allocations WHERE is_active=1 ORDER BY percentage DESC")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-cash-stack text-success"></i> Denomination Module</h4>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Record Bill Denomination Count</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <table class="table table-sm">
            <thead><tr><th>Bill</th><th>Quantity</th><th>Total</th></tr></thead>
            <tbody>
              <?php foreach ($billMaster as $b): ?>
              <tr>
                <td><?= money($b['bill_value']) ?></td>
                <td><input type="number" min="0" name="bill[<?= $b['bill_value'] ?>]" class="form-control form-control-sm qty-input" data-value="<?= $b['bill_value'] ?>" value="0"></td>
                <td class="line-total" id="lt_<?= $b['bill_value'] ?>"><?= money(0) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="fw-bold"><td colspan="2" class="text-end">Grand Total</td><td id="grandTotalNew"><?= money(0) ?></td></tr>
            </tfoot>
          </table>
          <button class="btn btn-brand w-100"><i class="bi bi-save"></i> Save Entry</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Current Cash Denomination Summary</div>
      <div class="card-body">
        <table class="table table-sm">
          <thead><tr><th>Bill</th><th>Quantity</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($billMaster as $b):
              $row = $totalsByBill[$b['bill_value']] ?? ['qty'=>0,'total'=>0]; ?>
            <tr>
              <td><?= money($b['bill_value']) ?></td>
              <td><?= (int)$row['qty'] ?></td>
              <td><?= money($row['total']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold"><td colspan="2" class="text-end">Grand Total</td><td><?= money($grandTotal) ?></td></tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header bg-white fw-semibold">Denomination Allocation Report</div>
  <div class="card-body">
    <p class="text-muted">Based on total recorded income of <b><?= money($totalIncome) ?></b> and configured budget allocation percentages:</p>
    <div class="table-responsive">
      <table class="table table-sm">
        <thead><tr><th>Item</th><th>%</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach ($allocations as $a): ?>
          <tr><td><?= h($a['item_name']) ?></td><td><?= number_format($a['percentage'],2) ?>%</td><td><?= money($totalIncome * $a['percentage']/100) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header bg-white fw-semibold">Recent Denomination Entries</div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>Date</th><th>Bill</th><th>Qty</th><th>Total</th><th>Recorded By</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr>
          <td><?= h($r['entry_date']) ?></td>
          <td><?= money($r['bill_value']) ?></td>
          <td><?= (int)$r['quantity'] ?></td>
          <td><?= money($r['total']) ?></td>
          <td><?= h($r['user_name'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?>
        <tr><td colspan="5" class="text-center text-muted py-3">No entries yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.querySelectorAll('.qty-input').forEach(input => {
  input.addEventListener('input', () => {
    let grand = 0;
    document.querySelectorAll('.qty-input').forEach(i => {
      const val = parseFloat(i.dataset.value);
      const qty = parseInt(i.value) || 0;
      const lineTotal = val * qty;
      document.getElementById('lt_' + val).innerText = '<?= CURRENCY ?>' + lineTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
      grand += lineTotal;
    });
    document.getElementById('grandTotalNew').innerText = '<?= CURRENCY ?>' + grand.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
  });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
