<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$pageTitle = 'Treasurer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $existingId = $pdo->query("SELECT id FROM treasurer_profile LIMIT 1")->fetchColumn();
    if ($existingId) {
        $stmt = $pdo->prepare("UPDATE treasurer_profile SET name=?, position=?, contact_number=?, email=? WHERE id=?");
        $stmt->execute([$name, $position, $contact, $email, $existingId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO treasurer_profile (name, position, contact_number, email) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $position, $contact, $email]);
    }
    flash('success', 'Treasurer profile updated.');
    redirect(BASE_URL . 'settings/treasurer.php');
}

$profile = $pdo->query("SELECT * FROM treasurer_profile LIMIT 1")->fetch() ?: [];

require __DIR__ . '/../includes/header.php';
?>
<h4 class="mb-3"><i class="bi bi-person-badge text-success"></i> Treasurer Profile</h4>
<div class="card" style="max-width:600px;">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="<?= h($profile['name'] ?? '') ?>" required></div>
      <div class="mb-3"><label class="form-label">Position</label><input type="text" name="position" class="form-control" value="<?= h($profile['position'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label">Contact Number</label><input type="text" name="contact_number" class="form-control" value="<?= h($profile['contact_number'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= h($profile['email'] ?? '') ?>"></div>
      <button class="btn btn-brand"><i class="bi bi-save"></i> Save</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
