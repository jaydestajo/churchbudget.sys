<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Dashboard';

// ---- KPI: Total Income / Expenses / Net Income (all-time) ----
$totalIncome = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM income")->fetchColumn();
$totalExpenses = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status='Approved'")->fetchColumn();
$netIncome = $totalIncome - $totalExpenses;

// ---- Cash on hand from latest denomination entries ----
$cashOnHand = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM denomination_entries")->fetchColumn();

// ---- Bank balance from balance_sheet_items ----
$bankBalance = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM balance_sheet_items WHERE item_type='Bank'")->fetchColumn();

// ---- Assets value ----
$assetsValue = (float)$pdo->query("SELECT COALESCE(SUM(current_value),0) FROM assets")->fetchColumn();

// ---- Budget utilization ----
$totalAllocationPct = (float)$pdo->query("SELECT COALESCE(SUM(percentage),0) FROM budget_allocations WHERE is_active=1")->fetchColumn();
$budgetPool = $totalIncome; // 100% of income is the pool being allocated
$allocatedAmount = $budgetPool * ($totalAllocationPct / 100);
$budgetUtilization = $allocatedAmount > 0 ? min(100, ($totalExpenses / $allocatedAmount) * 100) : 0;
$remainingBudget = max(0, $allocatedAmount - $totalExpenses);

// ---- Monthly income vs expenses (last 6 months) ----
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $m = new DateTime("first day of -$i months");
    $months[] = $m->format('Y-m');
}
$monthlyIncome = array_fill_keys($months, 0);
$monthlyExpense = array_fill_keys($months, 0);

$stmt = $pdo->query("SELECT DATE_FORMAT(income_date,'%Y-%m') ym, SUM(amount) total FROM income WHERE income_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym");
foreach ($stmt as $row) { if (isset($monthlyIncome[$row['ym']])) $monthlyIncome[$row['ym']] = (float)$row['total']; }

$stmt = $pdo->query("SELECT DATE_FORMAT(expense_date,'%Y-%m') ym, SUM(amount) total FROM expenses WHERE status='Approved' AND expense_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym");
foreach ($stmt as $row) { if (isset($monthlyExpense[$row['ym']])) $monthlyExpense[$row['ym']] = (float)$row['total']; }

// ---- Expense by category (pie) ----
$stmt = $pdo->query("SELECT category, SUM(amount) total FROM expenses WHERE status='Approved' GROUP BY category ORDER BY total DESC");
$expenseByCategory = $stmt->fetchAll();

// ---- Budget allocation (for chart) ----
$stmt = $pdo->query("SELECT item_name, percentage FROM budget_allocations WHERE is_active=1 ORDER BY percentage DESC");
$allocations = $stmt->fetchAll();

// ---- Cash flow trend (last 6 months net) ----
$cashFlowTrend = [];
foreach ($months as $ym) {
    $cashFlowTrend[] = round($monthlyIncome[$ym] - $monthlyExpense[$ym], 2);
}

// ---- Income trend / expense trend reuse monthlyIncome/monthlyExpense ----

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0"><i class="bi bi-speedometer2 text-success"></i> Dashboard</h4>
  <span class="text-muted small"><?= date('l, F j, Y') ?></span>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <?php
  $kpis = [
    ['label'=>'Total Income','value'=>money($totalIncome),'icon'=>'bi-cash-coin','color'=>'#2f9e44'],
    ['label'=>'Total Expenses','value'=>money($totalExpenses),'icon'=>'bi-receipt','color'=>'#e8590c'],
    ['label'=>'Net Income','value'=>money($netIncome),'icon'=>'bi-graph-up-arrow','color'=>'#1971c2'],
    ['label'=>'Cash on Hand','value'=>money($cashOnHand),'icon'=>'bi-cash-stack','color'=>'#c99a3c'],
    ['label'=>'Bank Balance','value'=>money($bankBalance),'icon'=>'bi-bank','color'=>'#495057'],
    ['label'=>'Assets Value','value'=>money($assetsValue),'icon'=>'bi-building','color'=>'#5f3dc4'],
    ['label'=>'Budget Utilization','value'=>round($budgetUtilization,1).'%','icon'=>'bi-speedometer','color'=>'#e64980'],
    ['label'=>'Remaining Budget','value'=>money($remainingBudget),'icon'=>'bi-piggy-bank','color'=>'#2f5d50'],
  ];
  foreach ($kpis as $k): ?>
  <div class="col-6 col-md-3">
    <div class="card kpi-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="kpi-icon" style="background:<?= $k['color'] ?>"><i class="bi <?= $k['icon'] ?>"></i></div>
        <div>
          <div class="text-muted small"><?= $k['label'] ?></div>
          <div class="fw-bold fs-5"><?= $k['value'] ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Monthly Income vs Expenses</div>
      <div class="card-body"><canvas id="incomeExpenseChart" height="110"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Expense by Category</div>
      <div class="card-body"><canvas id="categoryPieChart" height="110"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Budget Allocation</div>
      <div class="card-body"><canvas id="allocationChart" height="110"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Cash Flow Trend</div>
      <div class="card-body"><canvas id="cashFlowChart" height="110"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-semibold">Income Trend</div>
      <div class="card-body"><canvas id="incomeTrendChart" height="110"></canvas></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const months = <?= json_encode(array_map(fn($m) => date('M Y', strtotime($m.'-01')), $months)) ?>;
const incomeData = <?= json_encode(array_values($monthlyIncome)) ?>;
const expenseData = <?= json_encode(array_values($monthlyExpense)) ?>;
const cashFlowData = <?= json_encode($cashFlowTrend) ?>;

new Chart(document.getElementById('incomeExpenseChart'), {
  type: 'bar',
  data: {
    labels: months,
    datasets: [
      { label: 'Income', data: incomeData, backgroundColor: '#2f9e44' },
      { label: 'Expenses', data: expenseData, backgroundColor: '#e8590c' }
    ]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('categoryPieChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($expenseByCategory, 'category')) ?>,
    datasets: [{ data: <?= json_encode(array_map('floatval', array_column($expenseByCategory, 'total'))) ?>,
      backgroundColor: ['#2f5d50','#c99a3c','#1971c2','#e8590c','#5f3dc4','#e64980','#495057','#2f9e44','#f08c00'] }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('allocationChart'), {
  type: 'pie',
  data: {
    labels: <?= json_encode(array_column($allocations, 'item_name')) ?>,
    datasets: [{ data: <?= json_encode(array_map('floatval', array_column($allocations, 'percentage'))) ?>,
      backgroundColor: ['#2f5d50','#c99a3c','#1971c2','#e8590c','#5f3dc4','#e64980'] }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('cashFlowChart'), {
  type: 'line',
  data: { labels: months, datasets: [{ label: 'Net Cash Flow', data: cashFlowData, borderColor: '#1971c2', backgroundColor:'rgba(25,113,194,0.15)', fill: true, tension: 0.3 }] },
  options: { responsive: true, plugins: { legend: { display: false } } }
});

new Chart(document.getElementById('incomeTrendChart'), {
  type: 'line',
  data: { labels: months, datasets: [{ label: 'Income', data: incomeData, borderColor: '#2f9e44', backgroundColor:'rgba(47,158,68,0.15)', fill: true, tension: 0.3 }] },
  options: { responsive: true, plugins: { legend: { display: false } } }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
