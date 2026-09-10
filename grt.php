<?php
$pageTitle = 'GRT';
$crumb = 'Group Recognition Test verification records';
$active = 'grt';
require_once __DIR__ . '/includes/header.php';

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$statuses = $pdo->query("SELECT GRTStatusID, GRTStatusName FROM GRT_Status_Master ORDER BY GRTStatusName")->fetchAll();

$branchFilter = $_GET['branch'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');

$conditions = [];
$params = [];
if ($branchFilter) { $conditions[] = 'l.BranchID = ?'; $params[] = $branchFilter; }
if ($statusFilter) { $conditions[] = 'gs.GRTStatusID = ?'; $params[] = $statusFilter; }
if ($q !== '') {
    $conditions[] = '(l.LoanNo LIKE ? OR m.FirstName LIKE ? OR m.LastName LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$countStmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM GRT_Details g
  JOIN Loan l ON g.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  JOIN GRT_Status_Master gs ON g.GRTStatusID = gs.GRTStatusID
  $whereSql
");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
  SELECT
      g.GRTID,
      g.LoanID,
      l.LoanNo,
      b.BranchName,
      CONCAT(m.FirstName, ' ', ISNULL(m.MiddleName + ' ', ''), m.LastName) AS Client_Name,
      (SELECT TOP 1 CONCAT(cb.FirstName, ' ', ISNULL(cb.MiddleName + ' ', ''), cb.LastName)
         FROM Co_Borrower cb WHERE cb.MemberID = m.MemberID) AS Co_Borrower_Name,
      g.FinanceAmountVerified AS FinanceAmt,
      gs.GRTStatusName AS Status,
      st.FullName AS ConductedBy,
      g.GRTDate AS EntryDate,
      g.EnrollmentGRTFileName AS GRTFileName
  FROM GRT_Details g
  JOIN Loan l ON g.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  JOIN GRT_Status_Master gs ON g.GRTStatusID = gs.GRTStatusID
  LEFT JOIN Staff st ON g.ConductedByStaffID = st.StaffID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  $whereSql
  ORDER BY g.GRTDate DESC
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
");
// SQL Server's OFFSET/FETCH row counts must be bound as actual integers —
// a plain execute([...]) sends every value as a string and SQL Server rejects that.
$paramIndex = 1;
foreach ($params as $val) { $stmt->bindValue($paramIndex++, $val); }
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$grtRows = $stmt->fetchAll();

$totalGRTAll = (int)$pdo->query("SELECT COUNT(*) FROM GRT_Details")->fetchColumn();
$passedAll = (int)$pdo->query("SELECT COUNT(*) FROM GRT_Details g JOIN GRT_Status_Master gs ON g.GRTStatusID=gs.GRTStatusID WHERE gs.GRTStatusName='P'")->fetchColumn();
$failedAll = (int)$pdo->query("SELECT COUNT(*) FROM GRT_Details g JOIN GRT_Status_Master gs ON g.GRTStatusID=gs.GRTStatusID WHERE gs.GRTStatusName='F'")->fetchColumn();
$financeVerifiedAll = (float)$pdo->query("SELECT COALESCE(SUM(FinanceAmountVerified),0) FROM GRT_Details")->fetchColumn();

function grtStatusBadge($status) {
    $map = ['P' => 'success', 'F' => 'danger'];
    $color = $map[$status] ?? 'secondary';
    $label = $status === 'P' ? 'Passed' : ($status === 'F' ? 'Failed' : $status);
    return "<span class=\"badge badge-{$color}\">" . htmlspecialchars($label) . "</span>";
}

function grtLink($overrides) {
    global $branchFilter, $statusFilter, $q;
    $base = ['branch' => $branchFilter, 'status' => $statusFilter, 'q' => $q];
    return '?' . http_build_query(array_merge($base, $overrides));
}
$hasActiveFilter = ($q || $branchFilter || $statusFilter);
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy">📋</div>
      <div class="stat-body">
        <div class="num"><?= number_format($totalGRTAll) ?></div>
        <div class="lbl">Total GRTs</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal">✅</div>
      <div class="stat-body">
        <div class="num"><?= number_format($passedAll) ?></div>
        <div class="lbl">Passed</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft">❌</div>
      <div class="stat-body">
        <div class="num"><?= number_format($failedAll) ?></div>
        <div class="lbl">Failed</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold">💰</div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= money($financeVerifiedAll) ?></div>
        <div class="lbl">Finance Verified</div>
      </div>
    </div>
  </div>
</div>

<div class="card-x mb-3">
  <form method="GET" class="row g-3 align-items-end">
    <div class="col-md-4 col-lg-4">
      <label class="form-label">Search</label>
      <div class="search-box" style="width:100%;">
        <span class="ic">🔍</span>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Loan no. or client name...">
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg-3">
      <label class="form-label">Branch</label>
      <select class="form-select" name="branch" onchange="this.form.submit()">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['BranchID'] ?>" <?= $branchFilter==$b['BranchID']?'selected':'' ?>><?= htmlspecialchars($b['BranchName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3 col-lg-3">
      <label class="form-label">Status</label>
      <select class="form-select" name="status" onchange="this.form.submit()">
        <option value="">Any Status</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s['GRTStatusID'] ?>" <?= $statusFilter==$s['GRTStatusID']?'selected':'' ?>><?= htmlspecialchars($s['GRTStatusName'] === 'P' ? 'Passed' : ($s['GRTStatusName'] === 'F' ? 'Failed' : $s['GRTStatusName'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn-navy" type="submit" style="flex:1;">Search</button>
      <?php if ($hasActiveFilter): ?><a href="grt.php" class="btn-outline-x" style="color:var(--danger);">Clear</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card-x">
  <div class="table-scroll">
  <table class="table-x">
    <tr><th>Loan No</th><th>Client</th><th>Co-Borrower</th><th>Branch</th><th>Finance Verified</th><th>Status</th><th>Conducted By</th><th>GRT Date</th><th>Document</th></tr>
    <?php foreach ($grtRows as $g): ?>
    <tr>
      <td><a href="loan_view.php?id=<?= $g['LoanID'] ?>"><?= htmlspecialchars($g['LoanNo']) ?></a></td>
      <td><?= htmlspecialchars($g['Client_Name']) ?></td>
      <td><?= htmlspecialchars($g['Co_Borrower_Name'] ?? '-') ?></td>
      <td><?= htmlspecialchars($g['BranchName'] ?? '-') ?></td>
      <td style="font-weight:700;color:var(--navy);"><?= money($g['FinanceAmt']) ?></td>
      <td><?= grtStatusBadge($g['Status']) ?></td>
      <td><?= htmlspecialchars($g['ConductedBy'] ?? 'Unassigned') ?></td>
      <td><?= fdate($g['EntryDate']) ?></td>
      <td><?php if ($g['GRTFileName']): ?><a href="<?= htmlspecialchars($g['GRTFileName']) ?>" target="_blank" rel="noopener" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a><?php else: ?>-<?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$grtRows): ?><tr><td colspan="9"><div class="empty-state">No GRT records match your filters.</div></td></tr><?php endif; ?>
  </table>
  </div>

  <div class="pagination-bar">
    <div class="info">Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalFiltered) ?> of <?= number_format($totalFiltered) ?> GRT records</div>
    <div class="pager">
      <a class="<?= $page<=1?'disabled':'' ?>" href="<?= grtLink(['page'=>$page-1]) ?>">‹ Prev</a>
      <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>
      <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= grtLink(['page'=>$page+1]) ?>">Next ›</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
