<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_role('super_admin');

$pageTitle = 'Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $roleId = (int)$_POST['role_id'];
        $password = $_POST['password'] ?? '';

        if ($name === '' || $username === '' || $password === '') {
            flash('error', 'Name, username, and password are required.');
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username=?");
            $check->execute([$username]);
            if ($check->fetchColumn() > 0) {
                flash('error', 'Username already exists.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password_hash, role_id, status) VALUES (?,?,?,?,?, 'Active')");
                $stmt->execute([$name, $username, $email, $hash, $roleId]);
                flash('success', 'User created.');
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $roleId = (int)$_POST['role_id'];
        $status = $_POST['status'] === 'Active' ? 'Active' : 'Inactive';
        $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role_id=?, status=? WHERE id=?");
        $stmt->execute([$name, $email, $roleId, $status, $id]);

        if (!empty($_POST['new_password'])) {
            $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $id]);
        }
        flash('success', 'User updated.');
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === (int)($_SESSION['user']['id'] ?? 0)) {
            flash('error', 'You cannot delete your own account.');
        } else {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            flash('success', 'User deleted.');
        }
    }
    redirect(BASE_URL . 'settings/users.php');
}

$users = $pdo->query("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.id")->fetchAll();
$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-people text-success"></i> Users</h4>
  <button class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-lg"></i> Add User</button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0 align-middle">
      <thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= h($u['name']) ?></td>
          <td><?= h($u['username']) ?></td>
          <td><?= h($u['email']) ?></td>
          <td><span class="badge bg-secondary"><?= h(str_replace('_',' ',ucwords($u['role_name'],'_'))) ?></span></td>
          <td><span class="badge <?= $u['status']==='Active'?'bg-success':'bg-secondary' ?>"><?= $u['status'] ?></span></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>"><i class="bi bi-pencil"></i></button>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>

        <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content">
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <div class="modal-header"><h5 class="modal-title">Edit User</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <div class="mb-2"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="<?= h($u['name']) ?>" required></div>
                  <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= h($u['email']) ?>"></div>
                  <div class="mb-2">
                    <label class="form-label">Role</label>
                    <select name="role_id" class="form-select">
                      <?php foreach ($roles as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= $r['id']==$u['role_id']?'selected':'' ?>><?= h(str_replace('_',' ',ucwords($r['name'],'_'))) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="mb-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                      <option value="Active" <?= $u['status']==='Active'?'selected':'' ?>>Active</option>
                      <option value="Inactive" <?= $u['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                    </select>
                  </div>
                  <div class="mb-2"><label class="form-label">New Password (optional)</label><input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current"></div>
                </div>
                <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Save Changes</button></div>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add User</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
          <div class="mb-2">
            <label class="form-label">Role</label>
            <select name="role_id" class="form-select">
              <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>"><?= h(str_replace('_',' ',ucwords($r['name'],'_'))) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Create</button></div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
