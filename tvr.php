<?php
$pageTitle = 'TVR Records';
$crumb = 'Tele-Verification Reports — pre-disbursement calls';
$active = 'tvr';
require_once __DIR__ . '/includes/header.php';

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$statuses = $pdo->query("SELECT TVRStatusID, TVRStatusName FROM TVR_Status_Master ORDER BY TVRStatusID")->fetchAll();

$branchFilter = $_GET['branch'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');

$conditions = [];
$params = [];
if ($branchFilter) { $conditions[] = 'l.BranchID = ?'; $params[] = $branchFilter; }
if ($statusFilter) { $conditions[] = 'tv.TVRStatusID = ?'; $params[] = $statusFilter; }
if ($q !== '') {
    $conditions[] = '(l.LoanNo LIKE ? OR m.FirstName LIKE ? OR m.LastName LIKE ? OR tv.ContactPersonName LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$countStmt = $pdo->prepare("
  SELECT COUNT(*)
  FROM TVR_Details tv
  JOIN Loan l ON tv.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  JOIN TVR_Status_Master ts ON tv.TVRStatusID = ts.TVRStatusID
  $whereSql
");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
  SELECT
      tv.TVRID, l.LoanID, l.LoanNo, b.BranchName, c.CenterName,
      CONCAT(m.FirstName, ' ', ISNULL(m.MiddleName + ' ', ''), m.LastName) AS ClientName,
      m.MobileNo, tv.ContactPersonName, tv.TVRDate, st.FullName AS ConductedBy,
      ts.TVRStatusName AS Status, tv.VerificationNotes, l.LoanAmountSanctioned AS LoanAmount
  FROM TVR_Details tv
  JOIN Loan l ON tv.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Staff st ON tv.ConductedByStaffID = st.StaffID
  JOIN TVR_Status_Master ts ON tv.TVRStatusID = ts.TVRStatusID
  $whereSql
  ORDER BY tv.TVRDate DESC
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
");
$paramIndex = 1;
foreach ($params as $val) { $stmt->bindValue($paramIndex++, $val); }
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$totalTVRAll = (int)$pdo->query("SELECT COUNT(*) FROM TVR_Details")->fetchColumn();
$approvedAll = (int)$pdo->query("SELECT COUNT(*) FROM TVR_Details tv JOIN TVR_Status_Master ts ON tv.TVRStatusID=ts.TVRStatusID WHERE ts.TVRStatusName='Approved'")->fetchColumn();
$rejectedAll = (int)$pdo->query("SELECT COUNT(*) FROM TVR_Details tv JOIN TVR_Status_Master ts ON tv.TVRStatusID=ts.TVRStatusID WHERE ts.TVRStatusName='Rejected'")->fetchColumn();
$pendingAll = (int)$pdo->query("SELECT COUNT(*) FROM TVR_Details tv JOIN TVR_Status_Master ts ON tv.TVRStatusID=ts.TVRStatusID WHERE ts.TVRStatusName='Pending'")->fetchColumn();

function tvrStatusBadge($status) {
    $map = ['Approved' => 'success', 'Rejected' => 'danger', 'Pending' => 'secondary'];
    $color = $map[$status] ?? 'warning';
    return "<span class=\"badge badge-{$color}\">" . htmlspecialchars($status) . "</span>";
}

function tvrLink($overrides) {
    global $branchFilter, $statusFilter, $q;
    $base = ['branch' => $branchFilter, 'status' => $statusFilter, 'q' => $q];
    return '?' . http_build_query(array_merge($base, $overrides));
}
$hasActiveFilter = ($q || $branchFilter || $statusFilter);
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy"><i class="bi bi-telephone-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($totalTVRAll) ?></div>
        <div class="lbl">Total TVRs</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal"><i class="bi bi-check-circle-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($approvedAll) ?></div>
        <div class="lbl">Approved</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft"><i class="bi bi-x-circle-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($rejectedAll) ?></div>
        <div class="lbl">Rejected</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($pendingAll) ?></div>
        <div class="lbl">Pending</div>
      </div>
    </div>
  </div>
</div>

<div class="card-x mb-3">
  <form method="GET" class="row g-3 align-items-end">
    <div class="col-md-4 col-lg-4">
      <label class="form-label">Search</label>
      <div class="search-box" style="width:100%;">
        <span class="ic"><i class="bi bi-search"></i></span>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Loan no., client or contact person...">
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
          <option value="<?= $s['TVRStatusID'] ?>" <?= $statusFilter==$s['TVRStatusID']?'selected':'' ?>><?= htmlspecialchars($s['TVRStatusName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn-navy" type="submit" style="flex:1;">Search</button>
      <?php if ($hasActiveFilter): ?><a href="tvr.php" class="btn-outline-x" style="color:var(--danger);">Clear</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card-x">
  <div class="table-scroll">
  <table class="table-x">
    <tr><th>Loan No</th><th>Branch</th><th>Center</th><th>Client</th><th>Contact Person</th><th>TVR Date</th><th>Conducted By</th><th>Status</th><th>Notes</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
      <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['ClientName']) ?> <span style="color:var(--text-muted);font-size:11px;"><?= htmlspecialchars($r['MobileNo']) ?></span></td>
      <td><?= htmlspecialchars($r['ContactPersonName'] ?? '-') ?></td>
      <td><?= $r['TVRDate'] ? fdate($r['TVRDate']) : '-' ?></td>
      <td><?= htmlspecialchars($r['ConductedBy'] ?? 'Unassigned') ?></td>
      <td><?= tvrStatusBadge($r['Status']) ?></td>
      <td>
        <?php if ($r['VerificationNotes']): ?>
          <span title="<?= htmlspecialchars($r['VerificationNotes']) ?>" style="cursor:help;"><i class="bi bi-pencil-square"></i></span>
        <?php else: ?>-<?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9"><div class="empty-state">No TVR records match your filters.</div></td></tr><?php endif; ?>
  </table>
  </div>

  <div class="pagination-bar">
    <div class="info">Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalFiltered) ?> of <?= number_format($totalFiltered) ?> TVR records</div>
    <div class="pager">
      <a class="<?= $page<=1?'disabled':'' ?>" href="<?= tvrLink(['page'=>$page-1]) ?>">‹ Prev</a>
      <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>
      <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= tvrLink(['page'=>$page+1]) ?>">Next ›</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
