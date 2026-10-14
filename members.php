<?php
$pageTitle = 'Members / Leads';
$crumb = 'All prospective and existing microfinance members';
$active = 'members';
require_once __DIR__ . '/includes/header.php';

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$stages = $pdo->query("SELECT StageID, StageName FROM Lead_Stage_Master ORDER BY SortOrder")->fetchAll();
$staffList = $pdo->query("SELECT StaffID, FullName FROM Staff WHERE IsActive=1 ORDER BY FullName")->fetchAll();

$branchFilter = $_GET['branch'] ?? '';
$stageFilter = $_GET['stage'] ?? '';
$officerFilter = $_GET['officer'] ?? '';
$centerFilter = $_GET['center'] ?? '';
$q = trim($_GET['q'] ?? '');

$where = [];
$params = [];
if ($branchFilter) { $where[] = 'm.BranchID = ?'; $params[] = $branchFilter; }
if ($stageFilter) { $where[] = 'm.CurrentStageID = ?'; $params[] = $stageFilter; }
if ($officerFilter) { $where[] = 'm.AssignedToStaffID = ?'; $params[] = $officerFilter; }
if ($centerFilter) { $where[] = 'm.CenterID = ?'; $params[] = $centerFilter; }
if ($q !== '') {
    $where[] = '(m.FirstName LIKE ? OR m.LastName LIKE ? OR m.MobileNo LIKE ? OR m.EnrollmentNo LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

if (($_GET['export'] ?? '') === 'csv') {
    require_once __DIR__ . '/includes/csv_export_helper.php';
    $exportStmt = $pdo->prepare("
      SELECT m.EnrollmentNo, m.FirstName, m.LastName, m.MobileNo,
             b.BranchName, c.CenterName, ls.StageName AS lead_stage, st.FullName AS assignee
      FROM Member m
      LEFT JOIN Branch b ON m.BranchID = b.BranchID
      LEFT JOIN Center c ON m.CenterID = c.CenterID
      LEFT JOIN Lead_Stage_Master ls ON m.CurrentStageID = ls.StageID
      LEFT JOIN Staff st ON m.AssignedToStaffID = st.StaffID
      $whereSql
      ORDER BY m.MemberID DESC
    ");
    $exportStmt->execute($params);
    export_csv_and_exit(
        'members.csv',
        ['Enrollment No', 'Name', 'Mobile', 'Branch', 'Center', 'Stage', 'Assigned To'],
        array_map(fn($r) => [
            $r['EnrollmentNo'], trim($r['FirstName'] . ' ' . $r['LastName']), $r['MobileNo'],
            $r['BranchName'], $r['CenterName'], $r['lead_stage'], $r['assignee'] ?? 'Unassigned',
        ], $exportStmt->fetchAll())
    );
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Member m $whereSql");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
  SELECT m.MemberID, m.EnrollmentNo, m.FirstName, m.LastName, m.MobileNo,
         b.BranchName, c.CenterName, ls.StageName AS lead_stage, st.FullName AS assignee
  FROM Member m
  LEFT JOIN Branch b ON m.BranchID = b.BranchID
  LEFT JOIN Center c ON m.CenterID = c.CenterID
  LEFT JOIN Lead_Stage_Master ls ON m.CurrentStageID = ls.StageID
  LEFT JOIN Staff st ON m.AssignedToStaffID = st.StaffID
  $whereSql
  ORDER BY m.MemberID DESC
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";
$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $p) { $stmt->bindValue($i++, $p); }
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$members = $stmt->fetchAll();

$totalMembersAll = (int)$pdo->query("SELECT COUNT(*) FROM Member")->fetchColumn();
$newLeadsAll = (int)$pdo->query("SELECT COUNT(*) FROM Member m JOIN Lead_Stage_Master s ON m.CurrentStageID=s.StageID WHERE s.StageName='New Lead'")->fetchColumn();
$convertedAll = (int)$pdo->query("SELECT COUNT(*) FROM Member m JOIN Lead_Stage_Master s ON m.CurrentStageID=s.StageID WHERE s.StageName='Converted'")->fetchColumn();
$conversionRate = $totalMembersAll ? round($convertedAll / $totalMembersAll * 100, 1) : 0;

function membersLink($overrides) {
    global $branchFilter, $stageFilter, $officerFilter, $centerFilter, $q;
    $base = ['branch' => $branchFilter, 'stage' => $stageFilter, 'officer' => $officerFilter, 'center' => $centerFilter, 'q' => $q];
    return '?' . http_build_query(array_merge($base, $overrides));
}
$hasActiveFilter = ($q || $branchFilter || $stageFilter || $officerFilter || $centerFilter);

$centerFilterName = null;
if ($centerFilter) {
    $centerNameStmt = $pdo->prepare("SELECT CenterName FROM Center WHERE CenterID = ?");
    $centerNameStmt->execute([$centerFilter]);
    $centerFilterName = $centerNameStmt->fetchColumn();
}
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy"><i class="bi bi-people-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($totalMembersAll) ?></div>
        <div class="lbl">Total Members</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold"><i class="bi bi-flower2"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($newLeadsAll) ?></div>
        <div class="lbl">New Leads</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--success);">
      <div class="icon-box bg-teal"><i class="bi bi-check-circle-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($convertedAll) ?></div>
        <div class="lbl">Converted</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="stat-body">
        <div class="num"><?= $conversionRate ?>%</div>
        <div class="lbl">Conversion Rate</div>
      </div>
    </div>
  </div>
</div>

<?php if ($centerFilterName): ?>
  <div class="badge badge-info" style="display:flex;align-items:center;gap:8px;width:fit-content;padding:8px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">
    Showing members of center: <?= htmlspecialchars($centerFilterName) ?>
    <a href="<?= membersLink(['center' => 0]) ?>" style="color:inherit;"><i class="bi bi-x-lg"></i></a>
  </div>
<?php endif; ?>

<div class="card-x mb-3">
  <form method="GET" class="row g-3 align-items-end">
    <input type="hidden" name="center" value="<?= htmlspecialchars($centerFilter) ?>">
    <div class="col-md-4 col-lg-4">
      <label class="form-label">Search</label>
      <div class="search-box" style="width:100%;">
        <span class="ic"><i class="bi bi-search"></i></span>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Name, mobile or enrollment no...">
      </div>
    </div>
    <div class="col-6 col-md-2 col-lg-2">
      <label class="form-label">Branch</label>
      <select class="form-select" name="branch" onchange="this.form.submit()">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['BranchID'] ?>" <?= $branchFilter==$b['BranchID']?'selected':'' ?>><?= htmlspecialchars($b['BranchName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2 col-lg-2">
      <label class="form-label">Stage</label>
      <select class="form-select" name="stage" onchange="this.form.submit()">
        <option value="">All Stages</option>
        <?php foreach ($stages as $s): ?>
          <option value="<?= $s['StageID'] ?>" <?= $stageFilter==$s['StageID']?'selected':'' ?>><?= htmlspecialchars($s['StageName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2 col-lg-2">
      <label class="form-label">Officer</label>
      <select class="form-select" name="officer" onchange="this.form.submit()">
        <option value="">All Officers</option>
        <?php foreach ($staffList as $s): ?>
          <option value="<?= $s['StaffID'] ?>" <?= $officerFilter==$s['StaffID']?'selected':'' ?>><?= htmlspecialchars($s['FullName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2 col-lg-2 d-flex gap-2">
      <button class="btn-navy" type="submit" style="flex:1;">Search</button>
      <?php if ($hasActiveFilter): ?><a href="members.php" class="btn-outline-x" style="color:var(--danger);">Clear</a><?php endif; ?>
    </div>
  </form>
  <div style="text-align:right;margin-top:12px;">
    <a href="<?= membersLink(['export' => 'csv']) ?>" class="btn-outline-x"><i class="bi bi-download"></i> Export CSV</a>
  </div>
</div>

<div class="card-x">
  <div class="table-scroll">
  <table class="table-x">
    <tr><th>Enrollment No</th><th>Name</th><th>Mobile</th><th>Branch</th><th>Center</th><th>Stage</th><th>Assigned To</th><th></th></tr>
    <?php foreach ($members as $m): ?>
    <tr>
      <td><?= htmlspecialchars($m['EnrollmentNo']) ?></td>
      <td>
        <div class="d-flex align-items-center gap-2">
          <div class="avatar sm" style="background:var(--navy);"><?= initials($m['FirstName'].' '.$m['LastName']) ?></div>
          <a href="member_view.php?id=<?= $m['MemberID'] ?>"><?= htmlspecialchars($m['FirstName'].' '.$m['LastName']) ?></a>
        </div>
      </td>
      <td><?= htmlspecialchars($m['MobileNo']) ?></td>
      <td><?= htmlspecialchars($m['BranchName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($m['CenterName'] ?? '-') ?></td>
      <td><?= stage_badge($m['lead_stage']) ?></td>
      <td><?= htmlspecialchars($m['assignee'] ?? 'Unassigned') ?></td>
      <td><a href="member_view.php?id=<?= $m['MemberID'] ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$members): ?><tr><td colspan="8"><div class="empty-state">No members match your filters.</div></td></tr><?php endif; ?>
  </table>
  </div>

  <div class="pagination-bar">
    <div class="info">Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalFiltered) ?> of <?= number_format($totalFiltered) ?> members</div>
    <div class="pager">
      <a class="<?= $page<=1?'disabled':'' ?>" href="<?= membersLink(['page'=>$page-1]) ?>">‹ Prev</a>
      <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>
      <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= membersLink(['page'=>$page+1]) ?>">Next ›</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
