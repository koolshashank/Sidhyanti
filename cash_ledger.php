<?php
$pageTitle = 'Cash Ledger';
$crumb = 'Daily DCR closing balance, branch-wise running view';
$active = 'cash_ledger';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

$u = current_staff();
$isAdmin = is_admin_or_super($u);
$isBM = ($u['RoleName'] ?? '') === 'Branch Manager';
if (!$isAdmin && !$isBM) {
    echo '<div class="empty-state">Only Admin or Branch Manager can view the cash ledger.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$branchFilter = $isAdmin ? (int)($_GET['branch'] ?? ($branches[0]['BranchID'] ?? 0)) : (int)$u['BranchID'];
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$rows = [];
if ($branchFilter) {
    $stmt = $pdo->prepare("
      SELECT ClosingDate, OpeningBalance, TotalInflow, TotalOutflow, GrandTotal
      FROM Day_Closing_Report
      WHERE BranchID = ? AND ClosingDate BETWEEN ? AND ?
      ORDER BY ClosingDate
    ");
    $stmt->execute([$branchFilter, $dateFrom, $dateTo]);
    $rows = $stmt->fetchAll();
}

$chartLabels = array_map(fn($r) => date('d M', strtotime($r['ClosingDate'])), $rows);
$chartData = array_map(fn($r) => (float)$r['GrandTotal'], $rows);
?>

<div class="card-x" style="margin-bottom:16px;">
  <form method="GET" class="row g-2 align-items-end">
    <?php if ($isAdmin): ?>
    <div class="col-auto">
      <label class="form-label" style="font-size:12px;">Branch</label>
      <select name="branch" class="form-select form-select-sm">
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['BranchID'] ?>" <?= $branchFilter==$b['BranchID']?'selected':'' ?>><?= htmlspecialchars($b['BranchName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-auto">
      <label class="form-label" style="font-size:12px;">From</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
      <label class="form-label" style="font-size:12px;">To</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-navy btn-sm">Filter</button></div>
  </form>
</div>

<div class="card-x" style="margin-bottom:16px;">
  <div class="section-title">Closing Balance Trend</div>
  <?php if (!$rows): ?>
    <div class="empty-state">No DCR submissions in this range.</div>
  <?php else: ?>
    <canvas id="cashLedgerChart" height="90"></canvas>
  <?php endif; ?>
</div>

<div class="card-x">
  <?php if ($rows): ?>
  <table class="table-x">
    <tr><th>Date</th><th>Opening Balance</th><th>Total Inflow</th><th>Total Outflow</th><th>Closing Balance (Grand Total)</th></tr>
    <?php foreach (array_reverse($rows) as $r): ?>
    <tr>
      <td><?= htmlspecialchars(date('d M Y', strtotime($r['ClosingDate']))) ?></td>
      <td><?= money($r['OpeningBalance']) ?></td>
      <td><?= money($r['TotalInflow']) ?></td>
      <td><?= money($r['TotalOutflow']) ?></td>
      <td><strong><?= money($r['GrandTotal']) ?></strong></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<?php if ($rows): ?>
<script>
new Chart(document.getElementById('cashLedgerChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Closing Balance',
      data: <?= json_encode($chartData) ?>,
      borderColor: '#14A9AB',
      backgroundColor: 'rgba(20,169,171,0.12)',
      fill: true,
      tension: 0.3,
    }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: false } } }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
