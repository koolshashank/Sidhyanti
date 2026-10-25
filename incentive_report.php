<?php
$pageTitle = 'Incentive Report';
$crumb = 'Monthly incentive estimate per officer, from the Incentive Slabs';
$active = 'incentive_report';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/incentive_helper.php';

$u = current_staff();
$isAdmin = is_admin_or_super($u);
$isBM = ($u['RoleName'] ?? '') === 'Branch Manager';
if (!$isAdmin && !$isBM) {
    echo '<div class="empty-state">Only Admin or Branch Manager can view the incentive report.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$year = (int)($_GET['ty'] ?? date('Y'));
$month = (int)($_GET['tm'] ?? date('n'));
if ($month < 1 || $month > 12) { $month = (int)date('n'); }
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$branchFilter = $_GET['branch'] ?? '';

$branches = $isAdmin ? $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll() : [];

$conditions = ["r.RoleName IN ('Loan Officer', 'Field Officer', 'Center Manager')", 's.IsActive = 1'];
$params = [];
if (!$isAdmin) {
    $conditions[] = 's.BranchID = ?';
    $params[] = $u['BranchID'];
} elseif ($branchFilter) {
    $conditions[] = 's.BranchID = ?';
    $params[] = $branchFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $conditions);

$staffRows = $pdo->prepare("
  SELECT s.StaffID, s.FullName, b.BranchName
  FROM Staff s
  JOIN Role_Master r ON s.RoleID = r.RoleID
  LEFT JOIN Branch b ON s.BranchID = b.BranchID
  $whereSql
  ORDER BY b.BranchName, s.FullName
");
$staffRows->execute($params);
$staffRows = $staffRows->fetchAll();

$disbursementSlabs = incentive_slabs($pdo, 'Disbursement');
$collectionSlabs = incentive_slabs($pdo, 'Collection');

$disbStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(LoanAmountSanctioned, LoanAmountApplied)),0) FROM Loan WHERE LoanOfficerStaffID = ? AND DisbursementDate >= ? AND DisbursementDate < DATEADD(MONTH, 1, ?)");
$targetStmt = $pdo->prepare("SELECT DisbursementTarget FROM Staff_Target WHERE StaffID = ? AND TargetYear = ? AND TargetMonth = ?");
$collectedStmt = $pdo->prepare("SELECT COALESCE(SUM(TotalCollected),0) FROM Collection WHERE CollectedByStaffID = ? AND CollectionDate >= ? AND CollectionDate < DATEADD(MONTH, 1, ?)");
$dueStmt = $pdo->prepare("SELECT COALESCE(SUM(EMIAmount),0) FROM Daily_Collection_Sheet WHERE OfficerStaffID = ? AND CollectionDueDate >= ? AND CollectionDueDate < DATEADD(MONTH, 1, ?)");

$report = [];
$totalIncentive = 0;
foreach ($staffRows as $s) {
    $staffId = (int)$s['StaffID'];

    $disbStmt->execute([$staffId, $monthStart, $monthStart]);
    $amountDisbursed = (float)$disbStmt->fetchColumn();
    $targetStmt->execute([$staffId, $year, $month]);
    $target = (float)($targetStmt->fetchColumn() ?: 0);
    $disbPercent = $target > 0 ? round(($amountDisbursed / $target) * 100, 2) : 0;
    $disbIncentive = incentive_amount_for($disbursementSlabs, $disbPercent);

    $collectedStmt->execute([$staffId, $monthStart, $monthStart]);
    $collected = (float)$collectedStmt->fetchColumn();
    $dueStmt->execute([$staffId, $monthStart, $monthStart]);
    $due = (float)$dueStmt->fetchColumn();
    $collPercent = $due > 0 ? round(($collected / $due) * 100, 2) : 0;
    $collIncentive = incentive_amount_for($collectionSlabs, $collPercent);

    $rowTotal = $disbIncentive + $collIncentive;
    $totalIncentive += $rowTotal;

    $report[] = [
        'name' => $s['FullName'], 'branch' => $s['BranchName'],
        'disb_amount' => $amountDisbursed, 'disb_target' => $target, 'disb_percent' => $disbPercent, 'disb_incentive' => $disbIncentive,
        'collected' => $collected, 'due' => $due, 'coll_percent' => $collPercent, 'coll_incentive' => $collIncentive,
        'total' => $rowTotal,
    ];
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="incentive_report_' . $year . '_' . $month . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Staff', 'Branch', 'Disbursed', 'Disb Target', 'Disb %', 'Disb Incentive', 'Collected', 'Collection Due', 'Coll %', 'Coll Incentive', 'Total Incentive']);
    foreach ($report as $r) {
        fputcsv($out, [$r['name'], $r['branch'], $r['disb_amount'], $r['disb_target'], $r['disb_percent'], $r['disb_incentive'], $r['collected'], $r['due'], $r['coll_percent'], $r['coll_incentive'], $r['total']]);
    }
    fclose($out);
    exit;
}

$monthNames = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
?>

<div class="card-x" style="margin-bottom:16px;">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-auto">
      <label class="form-label" style="font-size:12px;">Month</label>
      <select name="tm" class="form-select form-select-sm">
        <?php foreach ($monthNames as $num => $name): ?>
          <option value="<?= $num ?>" <?= $num===$month?'selected':'' ?>><?= $name ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label" style="font-size:12px;">Year</label>
      <input type="number" name="ty" value="<?= $year ?>" class="form-control form-control-sm" style="width:90px;">
    </div>
    <?php if ($isAdmin): ?>
    <div class="col-auto">
      <label class="form-label" style="font-size:12px;">Branch</label>
      <select name="branch" class="form-select form-select-sm">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['BranchID'] ?>" <?= (string)$branchFilter===(string)$b['BranchID']?'selected':'' ?>><?= htmlspecialchars($b['BranchName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="col-auto"><button type="submit" class="btn btn-navy btn-sm">Filter</button></div>
    <div class="col-auto"><a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn-outline-x" style="font-size:12px;padding:6px 12px;"><i class="bi bi-download"></i> Export CSV</a></div>
  </form>
</div>

<div class="card-x">
  <div class="section-title">Total Incentive Payout: <?= money($totalIncentive) ?></div>
  <table class="table-x">
    <tr><th>Staff</th><th>Branch</th><th>Disbursed / Target</th><th>Disb %</th><th>Disb Incentive</th><th>Collected / Due</th><th>Coll %</th><th>Coll Incentive</th><th>Total</th></tr>
    <?php foreach ($report as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td><?= htmlspecialchars($r['branch'] ?? '-') ?></td>
      <td><?= money($r['disb_amount']) ?> / <?= money($r['disb_target']) ?></td>
      <td><?= $r['disb_percent'] ?>%</td>
      <td><?= money($r['disb_incentive']) ?></td>
      <td><?= money($r['collected']) ?> / <?= money($r['due']) ?></td>
      <td><?= $r['coll_percent'] ?>%</td>
      <td><?= money($r['coll_incentive']) ?></td>
      <td><strong><?= money($r['total']) ?></strong></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$report): ?><tr><td colspan="9" class="text-muted">No staff found.</td></tr><?php endif; ?>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
