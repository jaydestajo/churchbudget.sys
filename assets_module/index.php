<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_role('treasurer');

$pageTitle = 'Assets';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['asset_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $purchaseDate = $_POST['purchase_date'] ?: null;
        $cost = (float)($_POST['cost'] ?? 0);
        $currentValue = (float)($_POST['current_value'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        $condition = trim($_POST['condition_status'] ?? '');
        $serial = trim($_POST['serial_number'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($name === '' || $category === '') {
            flash('error', 'Please provide an asset name and category.');
        } elseif ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO assets (asset_name, category, description, purchase_date, cost, current_value, location, condition_status, serial_number, remarks)
                                    VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$name, $category, $description, $purchaseDate, $cost, $currentValue, $location, $condition, $serial, $remarks]);
            flash('success', 'Asset added.');
        } else {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE assets SET asset_name=?, category=?, description=?, purchase_date=?, cost=?, current_value=?, location=?, condition_status=?, serial_number=?, remarks=? WHERE id=?");
            $stmt->execute([$name, $category, $description, $purchaseDate, $cost, $currentValue, $location, $condition, $serial, $remarks, $id]);
            flash('success', 'Asset updated.');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM assets WHERE id=?")->execute([$id]);
        flash('success', 'Asset deleted.');
    }
    redirect(BASE_URL . 'assets_module/index.php');
}

$assets = $pdo->query("SELECT * FROM assets ORDER BY id DESC")->fetchAll();
$totalCost = array_sum(array_column($assets, 'cost'));
$totalCurrentValue = array_sum(array_column($assets, 'current_value'));
$categories = fixed_list('asset_categories');
$conditions = fixed_list('asset_conditions');

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-building text-success"></i> Assets</h4>
  <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#addAssetModal"><i class="bi bi-plus-lg"></i> Add Asset</button>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card kpi-card"><div class="card-body"><div class="text-muted small">Total Cost</div><div class="fw-bold fs-5"><?= money($totalCost) ?></div></div></div>
  </div>
  <div class="col-md-6">
    <div class="card kpi-card"><div class="card-body"><div class="text-muted small">Total Current Value</div><div class="fw-bold fs-5"><?= money($totalCurrentValue) ?></div></div></div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr><th>Asset</th><th>Category</th><th>Purchase Date</th><th>Cost</th><th>Current Value</th><th>Condition</th><th>Location</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($assets as $a): ?>
        <tr>
          <td><?= h($a['asset_name']) ?></td>
          <td><?= h($a['category']) ?></td>
          <td><?= h($a['purchase_date']) ?></td>
          <td><?= money($a['cost']) ?></td>
          <td><?= money($a['current_value']) ?></td>
          <td><?= h($a['condition_status']) ?></td>
          <td><?= h($a['location']) ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editAssetModal<?= $a['id'] ?>"><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this asset?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $a['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>

        <div class="modal fade" id="editAssetModal<?= $a['id'] ?>" tabindex="-1">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Edit Asset</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body row g-2">
                  <div class="col-md-6"><label class="form-label">Asset Name</label><input type="text" name="asset_name" class="form-control" value="<?= h($a['asset_name']) ?>" required></div>
                  <div class="col-md-6">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                      <?php foreach ($categories as $c): ?><option value="<?= h($c) ?>" <?= $c===$a['category']?'selected':'' ?>><?= h($c) ?></option><?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= h($a['description']) ?></textarea></div>
                  <div class="col-md-4"><label class="form-label">Purchase Date</label><input type="date" name="purchase_date" class="form-control" value="<?= h($a['purchase_date']) ?>"></div>
                  <div class="col-md-4"><label class="form-label">Cost</label><input type="number" step="0.01" name="cost" class="form-control" value="<?= $a['cost'] ?>"></div>
                  <div class="col-md-4"><label class="form-label">Current Value</label><input type="number" step="0.01" name="current_value" class="form-control" value="<?= $a['current_value'] ?>"></div>
                  <div class="col-md-6"><label class="form-label">Location</label><input type="text" name="location" class="form-control" value="<?= h($a['location']) ?>"></div>
                  <div class="col-md-6">
                    <label class="form-label">Condition</label>
                    <select name="condition_status" class="form-select">
                      <?php foreach ($conditions as $c): ?><option value="<?= h($c) ?>" <?= $c===$a['condition_status']?'selected':'' ?>><?= h($c) ?></option><?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6"><label class="form-label">Serial Number</label><input type="text" name="serial_number" class="form-control" value="<?= h($a['serial_number']) ?>"></div>
                  <div class="col-md-6"><label class="form-label">Remarks</label><input type="text" name="remarks" class="form-control" value="<?= h($a['remarks']) ?>"></div>
                </div>
                <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Save Changes</button></div>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($assets)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No assets recorded yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="addAssetModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Asset</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-2">
          <div class="col-md-6"><label class="form-label">Asset Name</label><input type="text" name="asset_name" class="form-control" required></div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
              <?php foreach ($categories as $c): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
          <div class="col-md-4"><label class="form-label">Purchase Date</label><input type="date" name="purchase_date" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Cost</label><input type="number" step="0.01" name="cost" class="form-control"></div>
          <div class="col-md-4"><label class="form-label">Current Value</label><input type="number" step="0.01" name="current_value" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Location</label><input type="text" name="location" class="form-control"></div>
          <div class="col-md-6">
            <label class="form-label">Condition</label>
            <select name="condition_status" class="form-select">
              <?php foreach ($conditions as $c): ?><option value="<?= h($c) ?>"><?= h($c) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6"><label class="form-label">Serial Number</label><input type="text" name="serial_number" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Remarks</label><input type="text" name="remarks" class="form-control"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Add Asset</button></div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
