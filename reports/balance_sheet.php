<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_login();

$pageTitle = 'Balance Sheet';
$canEdit = has_role('treasurer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) { http_response_code(403); die('Access denied.'); }
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $type = in_array($_POST['item_type'], ['Bank','Loan','Payable'], true) ? $_POST['item_type'] : 'Bank';
        $label = trim($_POST['label'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $asOf = $_POST['as_of_date'] ?? date('Y-m-d');
        if ($label !== '') {
            $stmt = $pdo->prepare("INSERT INTO balance_sheet_items (item_type, label, amount, as_of_date) VALUES (?,?,?,?)");
            $stmt->execute([$type, $label, $amount, $asOf]);
            flash('success', 'Balance sheet item added.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM balance_sheet_items WHERE id=?")->execute([$id]);
        flash('success', 'Item removed.');
    }
    redirect(BASE_URL . 'reports/balance_sheet.php');
}

// Assets
$cash = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM denomination_entries")->fetchColumn();
$bank = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM balance_sheet_items WHERE item_type='Bank'")->fetchColumn();

$stmt = $pdo->query("SELECT category, SUM(current_value) total FROM assets GROUP BY category");
$assetByCategory = [];
foreach ($stmt as $r) { $assetByCategory[$r['category']] = (float)$r['total']; }
$equipment = $assetByCategory['Equipment'] ?? 0;
$furniture = $assetByCategory['Furniture'] ?? 0;
$building = $assetByCategory['Building'] ?? 0;
$vehicles = $assetByCategory['Vehicle'] ?? 0;
$otherAssets = array_sum($assetByCategory) - $equipment - $furniture - $building - $vehicles;

$totalAssets = $cash + $bank + $equipment + $furniture + $building + $vehicles + $otherAssets;

// Liabilities
$loans = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM balance_sheet_items WHERE item_type='Loan'")->fetchColumn();
$payables = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM balance_sheet_items WHERE item_type='Payable'")->fetchColumn();
$totalLiabilities = $loans + $payables;

$equity = $totalAssets - $totalLiabilities;

$manualItems = $pdo->query("SELECT * FROM balance_sheet_items ORDER BY as_of_date DESC, id DESC")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-bank text-success"></i> Balance Sheet</h4>

<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Assets</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><td>Cash</td><td class="text-end"><?= money($cash) ?></td></tr>
          <tr><td>Bank</td><td class="text-end"><?= money($bank) ?></td></tr>
          <tr><td>Equipment</td><td class="text-end"><?= money($equipment) ?></td></tr>
          <tr><td>Furniture</td><td class="text-end"><?= money($furniture) ?></td></tr>
          <tr><td>Building</td><td class="text-end"><?= money($building) ?></td></tr>
          <tr><td>Vehicles</td><td class="text-end"><?= money($vehicles) ?></td></tr>
          <tr><td>Other Assets</td><td class="text-end"><?= money($otherAssets) ?></td></tr>
          <tr class="fw-bold border-top"><td>Total Assets</td><td class="text-end"><?= money($totalAssets) ?></td></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Liabilities</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><td>Loans</td><td class="text-end"><?= money($loans) ?></td></tr>
          <tr><td>Payables</td><td class="text-end"><?= money($payables) ?></td></tr>
          <tr class="fw-bold border-top"><td>Total Liabilities</td><td class="text-end"><?= money($totalLiabilities) ?></td></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Equity</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><td>Total Assets</td><td class="text-end"><?= money($totalAssets) ?></td></tr>
          <tr><td>(-) Total Liabilities</td><td class="text-end">(<?= money($totalLiabilities) ?>)</td></tr>
          <tr class="fw-bold border-top fs-5"><td>Net Worth (Equity)</td><td class="text-end <?= $equity>=0?'text-success':'text-danger' ?>"><?= money($equity) ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<?php if ($canEdit): ?>
<div class="card">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
    <span>Manage Bank Balance / Loans / Payables</span>
    <button class="btn btn-sm btn-brand" data-bs-toggle="modal" data-bs-target="#addBSItemModal"><i class="bi bi-plus-lg"></i> Add Item</button>
  </div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>Type</th><th>Label</th><th>Amount</th><th>As Of</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($manualItems as $m): ?>
        <tr>
          <td><span class="badge bg-secondary"><?= h($m['item_type']) ?></span></td>
          <td><?= h($m['label']) ?></td>
          <td><?= money($m['amount']) ?></td>
          <td><?= h($m['as_of_date']) ?></td>
          <td class="text-end">
            <form method="post" onsubmit="return confirm('Delete this item?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $m['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($manualItems)): ?>
        <tr><td colspan="5" class="text-center text-muted py-3">No manual items yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="addBSItemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Balance Sheet Item</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label">Type</label>
            <select name="item_type" class="form-select">
              <option value="Bank">Bank</option>
              <option value="Loan">Loan</option>
              <option value="Payable">Payable</option>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Label</label><input type="text" name="label" class="form-control" placeholder="e.g. BDO Savings Account" required></div>
          <div class="mb-2"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">As Of Date</label><input type="date" name="as_of_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Save</button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
