<?php
$pageTitle = 'Staff';
$crumb = 'Branch managers, loan officers & field team';
$active = 'staff';
require_once __DIR__ . '/includes/header.php';

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$roles = $pdo->query("SELECT RoleID, RoleName FROM Role_Master ORDER BY RoleName")->fetchAll();

$branchFilter = $_GET['branch'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');

$conditions = [];
$params = [];
if ($branchFilter) { $conditions[] = 's.BranchID = ?'; $params[] = $branchFilter; }
if ($roleFilter) { $conditions[] = 's.RoleID = ?'; $params[] = $roleFilter; }
if ($statusFilter === 'active') { $conditions[] = 's.IsActive = 1'; }
elseif ($statusFilter === 'inactive') { $conditions[] = 's.IsActive = 0'; }
if ($q !== '') {
    $conditions[] = '(s.FullName LIKE ? OR s.EmployeeCode LIKE ? OR s.Phone LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Staff s $whereSql");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
  SELECT s.*, r.RoleName, d.DesignationName, b.BranchName,
    (SELECT COUNT(*) FROM Member m WHERE m.AssignedToStaffID = s.StaffID) AS lead_count,
    (SELECT COUNT(*) FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID=stg.StageID WHERE l.LoanOfficerStaffID = s.StaffID AND stg.StageName='Active') AS active_loans
  FROM Staff s
  LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
  LEFT JOIN Designation_Master d ON s.DesignationID = d.DesignationID
  LEFT JOIN Branch b ON s.BranchID = b.BranchID
  $whereSql
  ORDER BY b.BranchName, r.RoleID, s.FullName
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
");
// SQL Server's OFFSET/FETCH row counts must be bound as actual integers —
// a plain execute([...]) sends every value as a string and SQL Server rejects that.
$paramIndex = 1;
foreach ($params as $val) { $stmt->bindValue($paramIndex++, $val); }
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$staff = $stmt->fetchAll();

$totalStaffAll = (int)$pdo->query("SELECT COUNT(*) FROM Staff")->fetchColumn();
$activeStaffAll = (int)$pdo->query("SELECT COUNT(*) FROM Staff WHERE IsActive=1")->fetchColumn();
$branchesCoveredAll = (int)$pdo->query("SELECT COUNT(DISTINCT BranchID) FROM Staff WHERE BranchID IS NOT NULL")->fetchColumn();
$activeLoansManagedAll = (int)$pdo->query("
  SELECT COUNT(*) FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID=stg.StageID
  WHERE stg.StageName='Active' AND l.LoanOfficerStaffID IS NOT NULL
")->fetchColumn();

$roleColors = [
    'Admin' => 'var(--navy)', 'Branch Manager' => 'var(--gold)', 'Loan Officer' => 'var(--teal)',
    'Field Officer' => '#0f8b8d', 'Accountant' => '#33409e', 'HR' => '#c8952c',
];

$viewMode = ($_GET['view'] ?? 'card') === 'table' ? 'table' : 'card';

function staffLink($overrides) {
    global $branchFilter, $roleFilter, $statusFilter, $q, $viewMode;
    $base = ['branch' => $branchFilter, 'role' => $roleFilter, 'status' => $statusFilter, 'q' => $q, 'view' => $viewMode];
    return '?' . http_build_query(array_merge($base, $overrides));
}
$hasActiveFilter = ($q || $branchFilter || $roleFilter || $statusFilter);
?>
<style>
.staff-card { display: flex; flex-direction: column; height: 100%; }
.staff-card-top { display: flex; align-items: center; gap: 12px; }
.staff-name { font-weight: 700; font-size: 15px; }
.staff-code { font-size: 11.5px; color: var(--text-muted); }
.staff-info-row { font-size: 12.5px; color: var(--text-muted); margin-top: 8px; display: flex; align-items: center; gap: 7px; }
.staff-metrics { display: flex; gap: 22px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border); }
.staff-metrics .m-num { font-size: 17px; font-weight: 800; }
.staff-metrics .m-lbl { font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
.staff-joined { margin-top: 12px; font-size: 11px; color: var(--text-muted); }
.view-toggle { display: inline-flex; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 3px; gap: 2px; }
.view-toggle-btn { display: inline-block; padding: 7px 14px; font-size: 13px; font-weight: 600; color: var(--text-muted); border-radius: 7px; }
.view-toggle-btn.active { background: var(--card-bg); color: var(--navy); box-shadow: var(--shadow-sm); }
.view-toggle-btn:hover { color: var(--navy); }
</style>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy"><i class="bi bi-person-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($totalStaffAll) ?></div>
        <div class="lbl">Total Staff</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal"><i class="bi bi-check-circle-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($activeStaffAll) ?></div>
        <div class="lbl">Active Staff</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold"><i class="bi bi-building"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($branchesCoveredAll) ?></div>
        <div class="lbl">Branches Covered</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft"><i class="bi bi-briefcase-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($activeLoansManagedAll) ?></div>
        <div class="lbl">Active Loans Managed</div>
      </div>
    </div>
  </div>
</div>

<?php if (isset($_GET['created'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Employee added successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Employee updated successfully.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="view-toggle">
    <a href="<?= staffLink(['view' => 'card']) ?>" class="view-toggle-btn <?= $viewMode==='card'?'active':'' ?>"><i class="bi bi-grid-3x3-gap-fill"></i> Card</a>
    <a href="<?= staffLink(['view' => 'table']) ?>" class="view-toggle-btn <?= $viewMode==='table'?'active':'' ?>"><i class="bi bi-table"></i> Table</a>
  </div>
  <a href="staff_add.php" class="btn-navy">+ Add Employee</a>
</div>

<div class="card-x mb-3">
  <form method="GET" class="row g-3 align-items-end">
    <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
    <div class="col-md-4 col-lg-4">
      <label class="form-label">Search</label>
      <div class="search-box" style="width:100%;">
        <span class="ic"><i class="bi bi-search"></i></span>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Name, employee code or phone...">
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
      <label class="form-label">Role</label>
      <select class="form-select" name="role" onchange="this.form.submit()">
        <option value="">All Roles</option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= $r['RoleID'] ?>" <?= $roleFilter==$r['RoleID']?'selected':'' ?>><?= htmlspecialchars($r['RoleName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2 col-lg-2">
      <label class="form-label">Status</label>
      <select class="form-select" name="status" onchange="this.form.submit()">
        <option value="">Any Status</option>
        <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
      </select>
    </div>
    <div class="col-6 col-md-2 col-lg-2 d-flex gap-2">
      <button class="btn-navy" type="submit" style="flex:1;">Search</button>
      <?php if ($hasActiveFilter): ?><a href="staff.php" class="btn-outline-x" style="color:var(--danger);">Clear</a><?php endif; ?>
    </div>
  </form>
</div>

<?php if ($staff): ?>
<?php if ($viewMode === 'table'): ?>
<div class="card-x">
  <div class="table-scroll">
    <table class="table-x">
      <tr>
        <th>Staff</th><th>Designation</th><th>Branch</th><th>Phone</th><th>Email</th>
        <th>Status</th><th>Leads</th><th>Active Loans</th><th>Joined</th><th></th>
      </tr>
      <?php foreach ($staff as $s):
        $roleName = $s['RoleName'] ?? 'Staff';
        $avatarColor = $roleColors[$roleName] ?? 'var(--navy)';
      ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px;">
            <div class="avatar" style="background:<?= $avatarColor ?>;width:32px;height:32px;font-size:12px;"><?= initials($s['FullName']) ?></div>
            <div>
              <div style="font-weight:700;"><?= htmlspecialchars($s['FullName']) ?></div>
              <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($s['EmployeeCode']) ?></div>
            </div>
          </div>
        </td>
        <td><?= htmlspecialchars($s['DesignationName'] ?? $roleName) ?></td>
        <td><?= htmlspecialchars($s['BranchName'] ?? 'Unassigned') ?></td>
        <td><?= htmlspecialchars($s['Phone'] ?: '-') ?></td>
        <td><?= htmlspecialchars($s['Email'] ?: '-') ?></td>
        <td><span class="badge badge-<?= $s['IsActive']?'success':'secondary' ?>"><?= $s['IsActive']?'Active':'Inactive' ?></span></td>
        <td><?= (int)$s['lead_count'] ?></td>
        <td><?= (int)$s['active_loans'] ?></td>
        <td><?= $s['DateOfJoining'] ? fdate($s['DateOfJoining']) : '-' ?></td>
        <td><a href="staff_edit.php?id=<?= $s['StaffID'] ?>" class="btn-outline-x" style="padding:4px 10px;font-size:12px;white-space:nowrap;"><i class="bi bi-pencil-fill"></i> Edit</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($staff as $s):
    $roleName = $s['RoleName'] ?? 'Staff';
    $avatarColor = $roleColors[$roleName] ?? 'var(--navy)';
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="card-x staff-card">
      <div class="staff-card-top">
        <div class="avatar" style="background:<?= $avatarColor ?>;"><?= initials($s['FullName']) ?></div>
        <div style="flex:1;min-width:0;">
          <div class="staff-name"><?= htmlspecialchars($s['FullName']) ?></div>
          <div class="staff-code"><?= htmlspecialchars($s['EmployeeCode']) ?></div>
        </div>
        <span class="badge badge-<?= $s['IsActive']?'success':'secondary' ?>"><?= $s['IsActive']?'Active':'Inactive' ?></span>
        <a href="staff_edit.php?id=<?= $s['StaffID'] ?>" class="btn-outline-x" style="padding:4px 10px;font-size:12px;white-space:nowrap;"><i class="bi bi-pencil-fill"></i> Edit</a>
      </div>
      <div class="tag-row" style="margin:12px 0 0;">
        <span class="badge badge-secondary"><?= htmlspecialchars($s['DesignationName'] ?? $roleName) ?></span>
        <span class="badge badge-info"><?= htmlspecialchars($s['BranchName'] ?? 'Unassigned') ?></span>
      </div>
      <div class="staff-info-row"><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars($s['Phone'] ?: '-') ?></div>
      <?php if ($s['Email']): ?><div class="staff-info-row"><i class="bi bi-envelope-fill"></i> <?= htmlspecialchars($s['Email']) ?></div><?php endif; ?>
      <div class="staff-metrics">
        <div><div class="m-num"><?= (int)$s['lead_count'] ?></div><div class="m-lbl">Leads Assigned</div></div>
        <div><div class="m-num"><?= (int)$s['active_loans'] ?></div><div class="m-lbl">Active Loans</div></div>
      </div>
      <?php if ($s['DateOfJoining']): ?><div class="staff-joined">Joined <?= fdate($s['DateOfJoining']) ?></div><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="pagination-bar">
  <div class="info">Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalFiltered) ?> of <?= number_format($totalFiltered) ?> staff</div>
  <div class="pager">
    <a class="<?= $page<=1?'disabled':'' ?>" href="<?= staffLink(['page'=>$page-1]) ?>">‹ Prev</a>
    <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>
    <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= staffLink(['page'=>$page+1]) ?>">Next ›</a>
  </div>
</div>
<?php else: ?>
  <div class="card-x empty-state">No staff match your filters.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
