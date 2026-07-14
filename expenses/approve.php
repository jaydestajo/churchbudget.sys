<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';
require_login();

$myLevel = approver_level(); // 1-4 or false
$isSuperAdmin = has_role('super_admin');

if (!$myLevel && !$isSuperAdmin) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;"><h2>403 - Access Denied</h2><p>Only designated approvers can access this page.</p><a href="' . BASE_URL . 'dashboard.php">&larr; Back to Dashboard</a></div>');
}

$pageTitle = 'Approve Expenses';

// Map: level -> status this level acts on, and status to set on approval
$statusForLevel = [
    1 => ['awaits' => 'Pending',          'onApprove' => 'Approved by L1'],
    2 => ['awaits' => 'Approved by L1',   'onApprove' => 'Approved by L2'],
    3 => ['awaits' => 'Approved by L2',   'onApprove' => 'Approved by L3'],
    4 => ['awaits' => 'Approved by L3',   'onApprove' => 'Approved'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)$_POST['id'];
    $decision = $_POST['decision'] ?? ''; // approve | reject
    $actLevel = $isSuperAdmin ? (int)$_POST['act_level'] : $myLevel;

    if (!isset($statusForLevel[$actLevel])) {
        flash('error', 'Invalid approval level.');
        redirect(BASE_URL . 'expenses/approve.php');
    }

    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id=?");
    $stmt->execute([$id]);
    $expense = $stmt->fetch();

    if (!$expense) {
        flash('error', 'Expense not found.');
    } elseif ($expense['status'] !== $statusForLevel[$actLevel]['awaits']) {
        flash('error', 'This expense is not awaiting your approval level (it may have already been processed).');
    } else {
        $col = "approver{$actLevel}_id";
        $actionCol = "approver{$actLevel}_action";
        $dateCol = "approver{$actLevel}_date";

        if ($decision === 'approve') {
            $newStatus = $statusForLevel[$actLevel]['onApprove'];
            $stmt = $pdo->prepare("UPDATE expenses SET status=?, {$col}=?, {$actionCol}='Approved', {$dateCol}=NOW() WHERE id=?");
            $stmt->execute([$newStatus, current_user()['id'], $id]);
            log_action($pdo, current_user()['id'], "Approve Expense L{$actLevel}", "Expense #{$id} approved at level {$actLevel}");
            flash('success', "Expense #{$id} approved" . ($newStatus === 'Approved' ? ' (final approval granted).' : ', moved to next approval level.'));
        } elseif ($decision === 'reject') {
            $stmt = $pdo->prepare("UPDATE expenses SET status='Rejected', {$col}=?, {$actionCol}='Rejected', {$dateCol}=NOW() WHERE id=?");
            $stmt->execute([current_user()['id'], $id]);
            log_action($pdo, current_user()['id'], "Reject Expense L{$actLevel}", "Expense #{$id} rejected at level {$actLevel}");
            flash('success', "Expense #{$id} rejected.");
        }
    }
    redirect(BASE_URL . 'expenses/approve.php');
}

// Fetch expenses awaiting action
if ($isSuperAdmin) {
    $pending = $pdo->query("SELECT e.*, u.name AS entered_by_name FROM expenses e LEFT JOIN users u ON u.id=e.entered_by WHERE e.status IN ('Pending','Approved by L1','Approved by L2','Approved by L3') ORDER BY e.expense_date DESC")->fetchAll();
} else {
    $awaits = $statusForLevel[$myLevel]['awaits'];
    $stmt = $pdo->prepare("SELECT e.*, u.name AS entered_by_name FROM expenses e LEFT JOIN users u ON u.id=e.entered_by WHERE e.status=? ORDER BY e.expense_date DESC");
    $stmt->execute([$awaits]);
    $pending = $stmt->fetchAll();
}

// Recent decisions (history)
$history = $pdo->query("SELECT e.* FROM expenses e WHERE e.status IN ('Approved','Rejected') ORDER BY e.id DESC LIMIT 15")->fetchAll();

require __DIR__ . '/../includes/header.php';

function levelForStatus($status) {
    $map = ['Pending'=>1,'Approved by L1'=>2,'Approved by L2'=>3,'Approved by L3'=>4];
    return $map[$status] ?? null;
}
?>
<h4 class="mb-3"><i class="bi bi-check2-square text-success"></i> Approve Expenses</h4>
<p class="text-muted">
  <?= $isSuperAdmin ? 'As Super Admin, you can act on behalf of any approval level.' : 'You are Approver Level ' . $myLevel . ($myLevel==4 ? ' (Final Approval)' : '.') ?>
</p>

<div class="card mb-4">
  <div class="card-header bg-white fw-semibold">Pending Your Action</div>
  <div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
      <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Entered By</th><th>Status</th><th class="text-end">Action</th></tr></thead>
      <tbody>
      <?php foreach ($pending as $p):
          $lvl = $isSuperAdmin ? levelForStatus($p['status']) : $myLevel; ?>
        <tr>
          <td><?= h($p['expense_date']) ?></td>
          <td><?= h($p['category']) ?></td>
          <td><?= h($p['description']) ?></td>
          <td><?= money($p['amount']) ?></td>
          <td><?= h($p['entered_by_name']) ?></td>
          <td><span class="badge bg-warning text-dark"><?= h($p['status']) ?></span></td>
          <td class="text-end">
            <form method="post" class="d-inline" onsubmit="return confirm('Approve this expense?');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="hidden" name="act_level" value="<?= $lvl ?>">
              <input type="hidden" name="decision" value="approve">
              <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Approve</button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('Reject this expense?');">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="hidden" name="act_level" value="<?= $lvl ?>">
              <input type="hidden" name="decision" value="reject">
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Reject</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($pending)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No expenses awaiting your approval. 🎉</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header bg-white fw-semibold">Recent Final Decisions</div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead><tr><th>Date</th><th>Category</th><th>Amount</th><th>Final Status</th></tr></thead>
      <tbody>
      <?php foreach ($history as $hrow): ?>
        <tr>
          <td><?= h($hrow['expense_date']) ?></td>
          <td><?= h($hrow['category']) ?></td>
          <td><?= money($hrow['amount']) ?></td>
          <td><span class="badge bg-<?= $hrow['status']==='Approved'?'success':'danger' ?>"><?= h($hrow['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($history)): ?>
        <tr><td colspan="4" class="text-center text-muted py-3">No decisions yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
