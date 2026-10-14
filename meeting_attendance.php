<?php
$pageTitle = 'Meeting Attendance';
$crumb = 'Center meeting attendance marked by field staff before collection';
$active = 'meeting_attendance';
require_once __DIR__ . '/includes/header.php';

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();

$branchFilter = $_GET['branch'] ?? '';
$centerFilter = $_GET['center'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$q = trim($_GET['q'] ?? '');

$conditions = ['a.AttendanceDate BETWEEN ? AND ?'];
$params = [$dateFrom, $dateTo];
if ($branchFilter) { $conditions[] = 'c.BranchID = ?'; $params[] = $branchFilter; }
if ($centerFilter) { $conditions[] = 'a.CenterID = ?'; $params[] = $centerFilter; }
if ($statusFilter) { $conditions[] = 'a.Status = ?'; $params[] = $statusFilter; }
if ($q !== '') { $conditions[] = 'a.BorrowerName LIKE ?'; $params[] = "%$q%"; }
$whereSql = 'WHERE ' . implode(' AND ', $conditions);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Center_Meeting_Attendance a LEFT JOIN Center c ON a.CenterID = c.CenterID $whereSql");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
  SELECT a.*, c.CenterName, b.BranchName, l.LoanNo, s.FullName AS MarkedByName
  FROM Center_Meeting_Attendance a
  LEFT JOIN Center c ON a.CenterID = c.CenterID
  LEFT JOIN Branch b ON c.BranchID = b.BranchID
  LEFT JOIN Loan l ON a.LoanID = l.LoanID
  LEFT JOIN Staff s ON a.MarkedByStaffID = s.StaffID
  $whereSql
  ORDER BY a.AttendanceDate DESC, c.CenterName, a.BorrowerName
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
");
$paramIndex = 1;
foreach ($params as $val) { $stmt->bindValue($paramIndex++, $val); }
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$statsStmt = $pdo->prepare("
  SELECT
    COUNT(*) AS Total,
    SUM(CASE WHEN a.Status = 'Present' THEN 1 ELSE 0 END) AS PresentCount,
    SUM(CASE WHEN a.Status = 'Absent' THEN 1 ELSE 0 END) AS AbsentCount,
    COUNT(DISTINCT a.CenterID) AS CentersCovered
  FROM Center_Meeting_Attendance a
  LEFT JOIN Center c ON a.CenterID = c.CenterID
  $whereSql
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch();

$centersForFilter = $pdo->query("SELECT CenterID, CenterName FROM Center WHERE IsActive = 1 ORDER BY CenterName")->fetchAll();

function attendanceLink($overrides) {
    global $branchFilter, $centerFilter, $statusFilter, $dateFrom, $dateTo, $q;
    $base = ['branch' => $branchFilter, 'center' => $centerFilter, 'status' => $statusFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'q' => $q];
    return '?' . http_build_query(array_merge($base, $overrides));
}
$hasActiveFilter = ($q || $branchFilter || $centerFilter || $statusFilter);
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy"><i class="bi bi-receipt"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format((int)$stats['Total']) ?></div>
        <div class="lbl">Total Marked</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal"><i class="bi bi-check-circle-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format((int)$stats['PresentCount']) ?></div>
        <div class="lbl">Present</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft"><i class="bi bi-x-circle-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format((int)$stats['AbsentCount']) ?></div>
        <div class="lbl">Absent</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold"><i class="bi bi-houses-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format((int)$stats['CentersCovered']) ?></div>
        <div class="lbl">Centers Covered</div>
      </div>
    </div>
  </div>
</div>

<div class="card-x mb-3">
  <form method="GET" class="row g-3 align-items-end">
    <div class="col-md-3 col-lg-3">
      <label class="form-label">Search Borrower</label>
      <div class="search-box" style="width:100%;">
        <span class="ic"><i class="bi bi-search"></i></span>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Borrower name...">
      </div>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">From</label>
      <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">To</label>
      <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Branch</label>
      <select class="form-select" name="branch" onchange="this.form.submit()">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['BranchID'] ?>" <?= $branchFilter==$b['BranchID']?'selected':'' ?>><?= htmlspecialchars($b['BranchName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Center</label>
      <select class="form-select" name="center" onchange="this.form.submit()">
        <option value="">All Centers</option>
        <?php foreach ($centersForFilter as $c): ?>
          <option value="<?= $c['CenterID'] ?>" <?= $centerFilter==$c['CenterID']?'selected':'' ?>><?= htmlspecialchars($c['CenterName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Status</label>
      <select class="form-select" name="status" onchange="this.form.submit()">
        <option value="">Any Status</option>
        <option value="Present" <?= $statusFilter==='Present'?'selected':'' ?>>Present</option>
        <option value="Absent" <?= $statusFilter==='Absent'?'selected':'' ?>>Absent</option>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn-navy" type="submit" style="flex:1;">Search</button>
      <?php if ($hasActiveFilter): ?><a href="meeting_attendance.php" class="btn-outline-x" style="color:var(--danger);">Clear</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card-x">
  <div class="table-scroll">
  <table class="table-x">
    <tr><th>Date</th><th>Center</th><th>Branch</th><th>Borrower</th><th>Loan No</th><th>Status</th><th>Marked By</th><th>Marked At</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= fdate($r['AttendanceDate']) ?></td>
      <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['BorrowerName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['LoanNo'] ?? '-') ?></td>
      <td><span class="badge badge-<?= $r['Status']==='Present'?'success':'danger' ?>"><?= htmlspecialchars($r['Status']) ?></span></td>
      <td><?= htmlspecialchars($r['MarkedByName'] ?? '-') ?></td>
      <td><?= date('d M Y, h:i A', strtotime($r['MarkedAt'])) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8"><div class="empty-state">No attendance records match your filters.</div></td></tr><?php endif; ?>
  </table>
  </div>

  <div class="pagination-bar">
    <div class="info">Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalFiltered) ?> of <?= number_format($totalFiltered) ?> records</div>
    <div class="pager">
      <a class="<?= $page<=1?'disabled':'' ?>" href="<?= attendanceLink(['page'=>$page-1]) ?>">‹ Prev</a>
      <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>
      <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= attendanceLink(['page'=>$page+1]) ?>">Next ›</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
