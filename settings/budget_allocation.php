<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$pageTitle = 'Budget Allocation Settings';

// ---- Handle actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $itemName = trim($_POST['item_name'] ?? '');
        $percentage = (float)($_POST['percentage'] ?? 0);

        if ($itemName === '' || $percentage <= 0) {
            flash('error', 'Please provide a valid item name and percentage.');
        } else {
            // Validate total doesn't exceed 100% (excluding current row if editing)
            $excludeId = $action === 'edit' ? (int)$_POST['id'] : 0;
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(percentage),0) FROM budget_allocations WHERE is_active=1 AND id != ?");
            $stmt->execute([$excludeId]);
            $existingTotal = (float)$stmt->fetchColumn();

            if ($existingTotal + $percentage > 100.0001) {
                flash('error', "Total allocation would exceed 100%. Currently allocated: {$existingTotal}%. Max you can add: " . (100 - $existingTotal) . "%.");
            } else {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO budget_allocations (item_name, percentage) VALUES (?, ?)");
                    $stmt->execute([$itemName, $percentage]);
                    flash('success', 'Budget allocation item added.');
                } else {
                    $id = (int)$_POST['id'];
                    $stmt = $pdo->prepare("UPDATE budget_allocations SET item_name=?, percentage=? WHERE id=?");
                    $stmt->execute([$itemName, $percentage, $id]);
                    flash('success', 'Budget allocation item updated.');
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM budget_allocations WHERE id=?");
        $stmt->execute([$id]);
        flash('success', 'Budget allocation item deleted.');
    }
    redirect(BASE_URL . 'settings/budget_allocation.php');
}

$items = $pdo->query("SELECT * FROM budget_allocations WHERE is_active=1 ORDER BY percentage DESC")->fetchAll();
$totalPct = array_sum(array_column($items, 'percentage'));
$totalIncome = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM income")->fetchColumn();

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-sliders text-success"></i> Budget Allocation Settings</h4>
  <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus-lg"></i> Add Allocation</button>
</div>

<div class="alert <?= abs($totalPct-100) < 0.01 ? 'alert-success' : 'alert-warning' ?>">
  Total allocated: <b><?= number_format($totalPct,2) ?>%</b>
  <?= abs($totalPct-100) < 0.01 ? '(fully allocated)' : '(' . number_format(100-$totalPct,2) . '% remaining to reach 100%)' ?>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr><th>Budget Item</th><th>Percentage</th><th>Amount (Auto)</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($items as $item): $amt = $totalIncome * ($item['percentage']/100); ?>
        <tr>
          <td><?= h($item['item_name']) ?></td>
          <td><?= number_format($item['percentage'],2) ?>%</td>
          <td><?= money($amt) ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= $item['id'] ?>"><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this allocation item?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $item['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= $item['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Edit Allocation</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <div class="mb-3"><label class="form-label">Budget Item</label><input type="text" name="item_name" class="form-control" value="<?= h($item['item_name']) ?>" required></div>
                  <div class="mb-3"><label class="form-label">Percentage</label><input type="number" step="0.01" min="0.01" max="100" name="percentage" class="form-control" value="<?= $item['percentage'] ?>" required></div>
                </div>
                <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Save Changes</button></div>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($items)): ?>
        <tr><td colspan="4" class="text-center text-muted py-4">No budget allocation items yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Budget Allocation</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Budget Item</label><input type="text" name="item_name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Percentage</label><input type="number" step="0.01" min="0.01" max="100" name="percentage" class="form-control" required></div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Add</button></div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
