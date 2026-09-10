<?php
$pageTitle = 'CGT';
$crumb = 'Center Group Training verification records';
$active = 'cgt';
require_once __DIR__ . '/includes/header.php';

$dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();

$branchFilter = $_GET['branch'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');

$conditions = ['c.IsActive = 1'];
$params = [];
if ($branchFilter) { $conditions[] = 'b.BranchID = ?'; $params[] = $branchFilter; }
if ($statusFilter === 'done') { $conditions[] = 'lc.CGTID IS NOT NULL'; }
elseif ($statusFilter === 'pending') { $conditions[] = 'lc.CGTID IS NULL'; }
if ($q !== '') {
    $conditions[] = '(c.CenterCode LIKE ? OR c.CenterName LIKE ? OR b.BranchName LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
$whereSql = 'WHERE ' . implode(' AND ', $conditions);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$cteSql = "
  WITH LatestCGT AS (
      SELECT ccd.*,
             ROW_NUMBER() OVER (PARTITION BY ccd.CenterID ORDER BY ccd.CGTDate DESC, ccd.CGTID DESC) AS rn
      FROM Center_CGT_Details ccd
  )
";

$countStmt = $pdo->prepare("
  $cteSql
  SELECT COUNT(*)
  FROM Branch b
  JOIN Center c ON c.BranchID = b.BranchID
  LEFT JOIN LatestCGT lc ON lc.CenterID = c.CenterID AND lc.rn = 1
  $whereSql
");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
  $cteSql
  SELECT
      b.BranchID,
      b.BranchName,
      c.CenterID,
      c.CenterCode,
      c.CenterName,
      c.CenterPlace,
      c.ContactPerson,
      c.ContactNo,
      c.MeetingDay,
      c.MeetingTime,
      lc.CGTID,
      lc.CGTDate,
      st.FullName AS ConductedBy,
      lc.DocumentFilePath AS CGTImage,
      lc.Remarks,
      CASE WHEN lc.CGTID IS NULL THEN 'Pending' ELSE 'Done' END AS CGTStatus
  FROM Branch b
  JOIN Center c ON c.BranchID = b.BranchID
  LEFT JOIN LatestCGT lc ON lc.CenterID = c.CenterID AND lc.rn = 1
  LEFT JOIN Staff st ON lc.ConductedByStaffID = st.StaffID
  $whereSql
  ORDER BY b.BranchName, c.CenterName
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
");
// SQL Server's OFFSET/FETCH row counts must be bound as actual integers —
// a plain execute([...]) sends every value as a string and SQL Server rejects that.
$paramIndex = 1;
foreach ($params as $val) { $stmt->bindValue($paramIndex++, $val); }
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$cgtRows = $stmt->fetchAll();

$totalCentersAll = (int)$pdo->query("SELECT COUNT(*) FROM Center WHERE IsActive = 1")->fetchColumn();
$doneAll = (int)$pdo->query("
  $cteSql
  SELECT COUNT(*)
  FROM Center c
  JOIN LatestCGT lc ON lc.CenterID = c.CenterID AND lc.rn = 1
  WHERE c.IsActive = 1
")->fetchColumn();
$pendingAll = $totalCentersAll - $doneAll;

function cgtStatusBadge($status) {
    $color = $status === 'Done' ? 'success' : 'warning';
    return "<span class=\"badge badge-{$color}\">" . htmlspecialchars($status) . "</span>";
}

function cgtLink($overrides) {
    global $branchFilter, $statusFilter, $q;
    $base = ['branch' => $branchFilter, 'status' => $statusFilter, 'q' => $q];
    return '?' . http_build_query(array_merge($base, $overrides));
}
$hasActiveFilter = ($q || $branchFilter || $statusFilter);
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-4">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy">🏘️</div>
      <div class="stat-body">
        <div class="num"><?= number_format($totalCentersAll) ?></div>
        <div class="lbl">Total Active Centers</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal">✅</div>
      <div class="stat-body">
        <div class="num"><?= number_format($doneAll) ?></div>
        <div class="lbl">CGT Done</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold">⏳</div>
      <div class="stat-body">
        <div class="num"><?= number_format($pendingAll) ?></div>
        <div class="lbl">CGT Pending</div>
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
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Center code, name or branch...">
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
        <option value="done" <?= $statusFilter==='done'?'selected':'' ?>>Done</option>
        <option value="pending" <?= $statusFilter==='pending'?'selected':'' ?>>Pending</option>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn-navy" type="submit" style="flex:1;">Search</button>
      <?php if ($hasActiveFilter): ?><a href="cgt.php" class="btn-outline-x" style="color:var(--danger);">Clear</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card-x">
  <div class="table-scroll">
  <table class="table-x">
    <tr><th>Branch</th><th>Center</th><th>Contact</th><th>Meeting</th><th>CGT Date</th><th>Conducted By</th><th>Status</th><th>Document</th></tr>
    <?php foreach ($cgtRows as $c): ?>
    <tr>
      <td><?= htmlspecialchars($c['BranchName']) ?></td>
      <td>
        <div style="font-weight:600;"><?= htmlspecialchars($c['CenterName']) ?></div>
        <div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($c['CenterCode']) ?><?php if ($c['CenterPlace']): ?> · <?= htmlspecialchars($c['CenterPlace']) ?><?php endif; ?></div>
      </td>
      <td>
        <div><?= htmlspecialchars($c['ContactPerson'] ?: '-') ?></div>
        <div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($c['ContactNo'] ?: '-') ?></div>
      </td>
      <td><?= htmlspecialchars($dayNames[$c['MeetingDay']] ?? ($c['MeetingDay'] ?: '-')) ?><div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($c['MeetingTime'] ?: '') ?></div></td>
      <td><?= fdate($c['CGTDate']) ?></td>
      <td><?= htmlspecialchars($c['ConductedBy'] ?? 'Unassigned') ?></td>
      <td><?= cgtStatusBadge($c['CGTStatus']) ?></td>
      <td><?php if ($c['CGTImage']): ?><a href="<?= htmlspecialchars($c['CGTImage']) ?>" target="_blank" rel="noopener" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a><?php else: ?>-<?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$cgtRows): ?><tr><td colspan="8"><div class="empty-state">No centers match your filters.</div></td></tr><?php endif; ?>
  </table>
  </div>

  <div class="pagination-bar">
    <div class="info">Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalFiltered) ?> of <?= number_format($totalFiltered) ?> centers</div>
    <div class="pager">
      <a class="<?= $page<=1?'disabled':'' ?>" href="<?= cgtLink(['page'=>$page-1]) ?>">‹ Prev</a>
      <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>
      <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= cgtLink(['page'=>$page+1]) ?>">Next ›</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
