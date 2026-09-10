<?php
$pageTitle = 'Loan Pipeline';
$crumb = 'Track every loan from application to closure';
$active = 'loans';
require_once __DIR__ . '/includes/header.php';

$branchFilter = $_GET['branch'] ?? '';
$q = trim($_GET['q'] ?? '');
$stageFilter = $_GET['stage'] ?? '';

$conditions = [];
$params = [];
if ($branchFilter) { $conditions[] = 'l.BranchID = ?'; $params[] = $branchFilter; }
if ($q !== '') {
    $conditions[] = "(l.LoanNo LIKE ? OR m.FirstName LIKE ? OR m.LastName LIKE ?)";
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stages = $pdo->query("SELECT StageName FROM Loan_Stage_Master ORDER BY SortOrder")->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, l.ApplicationDate, l.DisbursementDate,
         m.FirstName, m.LastName, b.BranchName, stg.StageName, p.PurposeName, officer.FullName AS OfficerName
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  LEFT JOIN Loan_Purpose_Master p ON l.PurposeID = p.PurposeID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  $whereSql
  ORDER BY l.ApplicationDate DESC
");
$stmt->execute($params);
$allLoans = $stmt->fetchAll();

$byStage = array_fill_keys($stages, []);
foreach ($allLoans as $l) { $byStage[$l['StageName']][] = $l; }

// A loan is "Disbursed" once it actually has a disbursement date — in practice
// the workflow moves CurrentStageID straight on to Active/Closed/Preclosed, so
// the "Disbursed" stage tab must be driven by the date, not the current stage.
if (array_key_exists('Disbursed', $byStage)) {
    $byStage['Disbursed'] = array_values(array_filter($allLoans, fn($l) => !empty($l['DisbursementDate'])));
}

$displayLoans = $stageFilter !== '' ? ($byStage[$stageFilter] ?? []) : $allLoans;

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();

$totalLoans = count($allLoans);
$loanValue = fn($l) => (float)($l['LoanAmountSanctioned'] ?: $l['LoanAmountApplied']);
$totalAmount = array_sum(array_map($loanValue, $allLoans));
$activeCount = count($byStage['Active'] ?? []);
$avgTicket = $totalLoans ? $totalAmount / $totalLoans : 0;

$barColors = [
    'secondary' => '#8a93ab', 'info' => '#1a5da0', 'warning' => '#c8952c',
    'primary' => '#33409e', 'success' => '#1f9d55', 'danger' => '#d64545', 'dark' => '#384158',
];

$qsBase = 'branch=' . urlencode($branchFilter) . '&q=' . urlencode($q);
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box bg-navy">📄</div>
      <div class="num"><?= number_format($totalLoans) ?></div>
      <div class="lbl">Loans <?= ($branchFilter || $q) ? '(filtered)' : 'in Pipeline' ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box bg-gold">💰</div>
      <div class="num"><?= money($totalAmount) ?></div>
      <div class="lbl">Total Pipeline Value</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box bg-teal">✅</div>
      <div class="num"><?= number_format($activeCount) ?></div>
      <div class="lbl">Active Loans</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box bg-danger-soft">📊</div>
      <div class="num"><?= money($avgTicket) ?></div>
      <div class="lbl">Avg Ticket Size</div>
    </div>
  </div>
</div>

<div class="loans-toolbar">
  <form class="d-flex gap-2 flex-wrap align-items-center" method="GET" style="flex:1;">
    <input type="hidden" name="stage" value="<?= htmlspecialchars($stageFilter) ?>">
    <div class="search-box">
      <span class="ic">🔍</span>
      <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search loan no. or member name...">
    </div>
    <select name="branch" onchange="this.form.submit()">
      <option value="">All Branches</option>
      <?php foreach ($branches as $b): ?>
        <option value="<?= $b['BranchID'] ?>" <?= $branchFilter==$b['BranchID']?'selected':'' ?>><?= htmlspecialchars($b['BranchName']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn-outline-x" type="submit">Search</button>
    <?php if ($q || $branchFilter): ?><a href="?stage=<?= urlencode($stageFilter) ?>" class="btn-outline-x" style="color:var(--danger);">Clear</a><?php endif; ?>
  </form>
  <a href="loan_add.php" class="btn-navy">+ New Loan</a>
</div>

<div class="stage-tabs">
  <a class="stage-tab <?= $stageFilter===''?'active':'' ?>" href="?stage=&<?= $qsBase ?>">
    All <span class="count">&nbsp;<?= $totalLoans ?></span>
  </a>
  <?php foreach ($stages as $s): $color = stage_color($s); ?>
  <a class="stage-tab <?= $stageFilter===$s?'active':'' ?>" style="<?= $stageFilter===$s ? '--tab-color:'.$barColors[$color].';' : '' ?>" href="?stage=<?= urlencode($s) ?>&<?= $qsBase ?>">
    <?= htmlspecialchars($s) ?> <span class="count"><?= count($byStage[$s]) ?></span>
  </a>
  <?php endforeach; ?>
</div>

<?php if (!$displayLoans): ?>
  <div class="card-x empty-state">
    <?= $allLoans ? 'No loans in this stage.' : 'No loans match your search. Try a different keyword or clear the filters.' ?>
  </div>
<?php else: ?>
<div class="card-x">
  <table class="table-x">
    <tr><th>Loan No</th><th>Member</th><th>Branch</th><th>Purpose</th><th>Officer</th><th>Amount</th><th>Stage</th><th>Applied</th><th></th></tr>
    <?php foreach ($displayLoans as $l): ?>
    <tr>
      <td><?= htmlspecialchars($l['LoanNo']) ?></td>
      <td>
        <div class="d-flex align-items-center gap-2">
          <div class="avatar sm" style="background:var(--navy);"><?= initials($l['FirstName'].' '.$l['LastName']) ?></div>
          <?= htmlspecialchars($l['FirstName'].' '.$l['LastName']) ?>
        </div>
      </td>
      <td><?= htmlspecialchars($l['BranchName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($l['PurposeName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($l['OfficerName'] ?? 'Unassigned') ?></td>
      <td style="font-weight:700;color:var(--navy);"><?= money($loanValue($l)) ?></td>
      <td><?= stage_badge($l['StageName']) ?></td>
      <td><?= fdate($l['ApplicationDate']) ?></td>
      <td><a href="loan_view.php?id=<?= $l['LoanID'] ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
