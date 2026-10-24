<?php
$pageTitle = 'Staff Targets';
$crumb = 'Set each officer\'s monthly disbursement target';
$active = 'staff_targets';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

$u = current_staff();
$isAdmin = is_admin_or_super($u);
$isBM = ($u['RoleName'] ?? '') === 'Branch Manager';
if (!$isAdmin && !$isBM) {
    echo '<div class="empty-state">Only Admin or Branch Manager can set staff targets.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$targetYear = (int)($_GET['ty'] ?? date('Y'));
$targetMonth = (int)($_GET['tm'] ?? date('n'));
if ($targetMonth < 1 || $targetMonth > 12) { $targetMonth = (int)date('n'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postYear = (int)($_POST['ty'] ?? $targetYear);
    $postMonth = (int)($_POST['tm'] ?? $targetMonth);
    $targets = $_POST['target'] ?? []; // [staff_id => amount]

    $upsert = $pdo->prepare("
        MERGE Staff_Target AS t
        USING (SELECT ? AS StaffID, ? AS TargetYear, ? AS TargetMonth) AS src
        ON t.StaffID = src.StaffID AND t.TargetYear = src.TargetYear AND t.TargetMonth = src.TargetMonth
        WHEN MATCHED THEN UPDATE SET DisbursementTarget = ?, UpdatedByStaffID = ?, UpdatedAt = GETDATE()
        WHEN NOT MATCHED THEN INSERT (StaffID, TargetYear, TargetMonth, DisbursementTarget, UpdatedByStaffID)
            VALUES (src.StaffID, src.TargetYear, src.TargetMonth, ?, ?);
    ");
    foreach ($targets as $staffId => $amount) {
        $staffId = (int)$staffId;
        $amount = (float)$amount;
        if ($staffId <= 0) continue;
        $upsert->execute([$staffId, $postYear, $postMonth, $amount, (int)$u['StaffID'], $amount, (int)$u['StaffID']]);
    }
    header('Location: staff_targets.php?ty=' . $postYear . '&tm=' . $postMonth . '&saved=1');
    exit;
}

$branchFilter = (int)($_GET['branch'] ?? 0);
$branches = $isAdmin
    ? $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll()
    : [];

$where = ["r.RoleName IN ('Loan Officer', 'Field Officer')", 's.IsActive = 1'];
$params = [];
if ($isBM) {
    $where[] = 's.BranchID = ?';
    $params[] = $u['BranchID'];
} elseif ($branchFilter > 0) {
    $where[] = 's.BranchID = ?';
    $params[] = $branchFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$officersStmt = $pdo->prepare("
    SELECT s.StaffID, s.FullName, s.EmployeeCode, b.BranchName,
           COALESCE(st.DisbursementTarget, 0) AS DisbursementTarget,
           (SELECT COALESCE(SUM(COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied)), 0)
            FROM Loan l
            WHERE l.LoanOfficerStaffID = s.StaffID
              AND YEAR(l.DisbursementDate) = ? AND MONTH(l.DisbursementDate) = ?) AS DisbursedSoFar
    FROM Staff s
    JOIN Role_Master r ON s.RoleID = r.RoleID
    LEFT JOIN Branch b ON s.BranchID = b.BranchID
    LEFT JOIN Staff_Target st ON st.StaffID = s.StaffID AND st.TargetYear = ? AND st.TargetMonth = ?
    $whereSql
    ORDER BY b.BranchName, s.FullName
");
$officersStmt->execute(array_merge([$targetYear, $targetMonth, $targetYear, $targetMonth], $params));
$officers = $officersStmt->fetchAll();

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
?>
<?php if (isset($_GET['saved'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Targets saved successfully.</div>
<?php endif; ?>

<div class="card-x mb-3">
  <form method="GET" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Month</label>
      <select class="form-select" name="tm" onchange="this.form.submit()">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $m === $targetMonth ? 'selected' : '' ?>><?= $monthNames[$m] ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Year</label>
      <select class="form-select" name="ty" onchange="this.form.submit()">
        <?php for ($y = (int)date('Y') - 1; $y <= (int)date('Y') + 1; $y++): ?>
          <option value="<?= $y ?>" <?= $y === $targetYear ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <?php if ($isAdmin): ?>
    <div class="col-md-4">
      <label class="form-label">Branch</label>
      <select class="form-select" name="branch" onchange="this.form.submit()">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['BranchID'] ?>" <?= $branchFilter == $b['BranchID'] ? 'selected' : '' ?>><?= htmlspecialchars($b['BranchName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
  </form>
</div>

<form method="POST">
  <input type="hidden" name="ty" value="<?= $targetYear ?>">
  <input type="hidden" name="tm" value="<?= $targetMonth ?>">
  <div class="card-x">
    <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <span><?= $monthNames[$targetMonth] ?> <?= $targetYear ?> -- Disbursement Targets</span>
      <button class="btn-navy" type="submit"><i class="bi bi-save"></i> Save All</button>
    </div>
    <?php if ($officers): ?>
    <div class="table-scroll">
      <table class="table-x">
        <tr><th>Officer</th><th>Branch</th><th>Disbursed So Far</th><th>Monthly Target</th></tr>
        <?php foreach ($officers as $o): ?>
        <tr>
          <td><?= htmlspecialchars($o['FullName']) ?><br><span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($o['EmployeeCode']) ?></span></td>
          <td><?= htmlspecialchars($o['BranchName'] ?? '-') ?></td>
          <td><?= money($o['DisbursedSoFar']) ?></td>
          <td>
            <input type="number" step="0.01" min="0" class="form-control" style="max-width:180px;"
                   name="target[<?= $o['StaffID'] ?>]" value="<?= $o['DisbursementTarget'] ?>">
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php else: ?>
      <div class="empty-state">No officers found for this filter.</div>
    <?php endif; ?>
  </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
