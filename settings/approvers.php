<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$pageTitle = 'Approvers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $level = (int)$_POST['level'];
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $status = $_POST['status'] === 'Active' ? 'Active' : 'Inactive';

    $stmt = $pdo->prepare("UPDATE approvers SET name=?, position=?, email=?, contact_number=?, status=? WHERE level=?");
    $stmt->execute([$name, $position, $email, $contact, $status, $level]);
    flash('success', "Approver Level {$level} updated.");
    redirect(BASE_URL . 'settings/approvers.php');
}

$approvers = $pdo->query("SELECT * FROM approvers ORDER BY level")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-person-check text-success"></i> Approvers</h4>
<p class="text-muted">Configure the four sequential approval levels for expense requests.</p>

<div class="row g-3">
<?php foreach ($approvers as $a): ?>
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>Approver Level <?= $a['level'] ?><?= $a['level']==4 ? ' (Final Approval)' : '' ?></span>
        <span class="badge <?= $a['status']==='Active' ? 'bg-success' : 'bg-secondary' ?>"><?= $a['status'] ?></span>
      </div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="level" value="<?= $a['level'] ?>">
          <div class="mb-2"><label class="form-label small">Name</label><input type="text" name="name" class="form-control" value="<?= h($a['name']) ?>" required></div>
          <div class="mb-2"><label class="form-label small">Position</label><input type="text" name="position" class="form-control" value="<?= h($a['position']) ?>"></div>
          <div class="mb-2"><label class="form-label small">Email</label><input type="email" name="email" class="form-control" value="<?= h($a['email']) ?>"></div>
          <div class="mb-2"><label class="form-label small">Contact Number</label><input type="text" name="contact_number" class="form-control" value="<?= h($a['contact_number']) ?>"></div>
          <div class="mb-3">
            <label class="form-label small">Status</label>
            <select name="status" class="form-select">
              <option value="Active" <?= $a['status']==='Active'?'selected':'' ?>>Active</option>
              <option value="Inactive" <?= $a['status']==='Inactive'?'selected':'' ?>>Inactive</option>
            </select>
          </div>
          <button class="btn btn-brand btn-sm"><i class="bi bi-save"></i> Save</button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
