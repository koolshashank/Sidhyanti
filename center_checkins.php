<?php
$pageTitle = 'Meeting Check-in Compliance';
$crumb = 'Field officer GPS check-in at each center\'s scheduled meeting';
$active = 'center_checkins';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

$u = current_staff();
if (!is_admin_or_super($u) && ($u['RoleName'] ?? '') !== 'Branch Manager') {
    echo '<div class="empty-state">Only Admin or Branch Manager can view meeting check-in compliance.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$branchFilter = $_GET['branch'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-6 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$conditions = ['CAST(ci.CheckinAt AS DATE) BETWEEN ? AND ?'];
$params = [$dateFrom, $dateTo];
if (!is_admin_or_super($u)) {
    $conditions[] = 'c.BranchID = ?';
    $params[] = $u['BranchID'];
} elseif ($branchFilter) {
    $conditions[] = 'c.BranchID = ?';
    $params[] = $branchFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $conditions);

$rows = $pdo->prepare("
  SELECT ci.CheckinID, ci.CheckinAt, ci.DistanceMeters, ci.WithinGeofence,
         c.CenterName, b.BranchName, s.FullName AS StaffName
  FROM Center_Visit_Checkin ci
  LEFT JOIN Center c ON ci.CenterID = c.CenterID
  LEFT JOIN Branch b ON c.BranchID = b.BranchID
  LEFT JOIN Staff s ON ci.StaffID = s.StaffID
  $whereSql
  ORDER BY ci.CheckinAt DESC
");
$rows->execute($params);
$rows = $rows->fetchAll();

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
?>

<div class="card-x" style="margin-bottom:16px;">
  <form method="GET" class="row g-2 align-items-end">
    <?php if (is_admin_or_super($u)): ?>
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
    <div class="col-auto">
      <label class="form-label" style="font-size:12px;">From</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
      <label class="form-label" style="font-size:12px;">To</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-navy btn-sm">Filter</button>
    </div>
  </form>
</div>

<div class="card-x">
  <?php if (!$rows): ?>
    <div class="empty-state">No check-ins in this range.</div>
  <?php else: ?>
  <table class="table-x">
    <tr><th>Date/Time</th><th>Officer</th><th>Center</th><th>Branch</th><th>Distance</th><th>Compliance</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= htmlspecialchars(date('d M Y, h:i A', strtotime($r['CheckinAt']))) ?></td>
      <td><?= htmlspecialchars($r['StaffName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
      <td><?= $r['DistanceMeters'] !== null ? number_format($r['DistanceMeters']) . ' m' : 'N/A' ?></td>
      <td>
        <?php if ($r['WithinGeofence'] === null): ?>
          <span class="badge badge-secondary">No center location</span>
        <?php elseif ($r['WithinGeofence']): ?>
          <span class="badge badge-success">Within range</span>
        <?php else: ?>
          <span class="badge badge-danger">Out of range</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
