<?php
$pageTitle = 'Death Entries';
$crumb = 'Review death cases reported by field staff';
$active = 'death_entries';
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject'], true)) {
    $newStatus = $_POST['action'] === 'approve' ? 'Approved' : 'Rejected';
    $stmt = $pdo->prepare("UPDATE Death_Entry SET Status=?, RemarksByApprover=?, ReviewedByStaffID=?, ReviewedAt=GETDATE() WHERE DeathEntryID=? AND Status='Pending'");
    $stmt->execute([$newStatus, $_POST['remarks'] ?: null, current_staff()['StaffID'], $_POST['death_entry_id']]);
    header('Location: death_entries.php?' . http_build_query($_GET)); exit;
}

$statusFilter = $_GET['status'] ?? 'Pending';
$where = $statusFilter && $statusFilter !== 'All' ? "WHERE de.Status = ?" : "";
$stmt = $pdo->prepare("
  SELECT de.*, m.FirstName, m.LastName, m.EnrollmentNo, m.MobileNo, c.CenterName, b.BranchName,
         rep.FullName AS ReportedByName, rev.FullName AS ReviewedByName
  FROM Death_Entry de
  JOIN Member m ON de.MemberID = m.MemberID
  LEFT JOIN Center c ON m.CenterID = c.CenterID
  LEFT JOIN Branch b ON m.BranchID = b.BranchID
  LEFT JOIN Staff rep ON de.ReportedByStaffID = rep.StaffID
  LEFT JOIN Staff rev ON de.ReviewedByStaffID = rev.StaffID
  $where
  ORDER BY CASE de.Status WHEN 'Pending' THEN 1 ELSE 2 END, de.CreatedAt DESC
");
$stmt->execute($statusFilter && $statusFilter !== 'All' ? [$statusFilter] : []);
$entries = $stmt->fetchAll();

$statusCounts = [];
foreach ($pdo->query("SELECT Status, COUNT(*) c FROM Death_Entry GROUP BY Status")->fetchAll() as $row) {
    $statusCounts[$row['Status']] = $row['c'];
}

function death_status_badge($status) {
    $map = ['Pending' => 'warning', 'Approved' => 'success', 'Rejected' => 'danger'];
    $color = $map[$status] ?? 'secondary';
    return "<span class=\"badge badge-{$color}\">" . htmlspecialchars($status) . "</span>";
}
?>

<div class="row g-3 mb-3">
  <?php foreach (['Pending', 'Approved', 'Rejected', 'All'] as $s): ?>
  <div class="col-6 col-md-3">
    <a href="?status=<?= $s ?>" style="color:inherit;">
      <div class="card-x stat-card <?= $statusFilter === $s ? 'active' : '' ?>">
        <div class="num"><?= $s === 'All' ? array_sum($statusCounts) : ($statusCounts[$s] ?? 0) ?></div>
        <div class="lbl"><?= $s ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="card-x">
  <div class="section-title">
    Death Entries <?= $statusFilter && $statusFilter !== 'All' ? "— $statusFilter" : '' ?>
  </div>
  <table class="table-x">
    <tr>
      <th>Member</th><th>Center / Branch</th><th>Date of Death</th><th>Informant</th>
      <th>Reported By</th><th>Proof</th><th>Status</th><th></th>
    </tr>
    <?php foreach ($entries as $e): ?>
    <tr>
      <td>
        <?= htmlspecialchars(trim($e['FirstName'] . ' ' . $e['LastName'])) ?><br>
        <small style="color:var(--text-muted);"><?= htmlspecialchars($e['EnrollmentNo']) ?> · <?= htmlspecialchars($e['MobileNo']) ?></small>
      </td>
      <td><?= htmlspecialchars($e['CenterName'] ?? '-') ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars($e['BranchName'] ?? '-') ?></small></td>
      <td><?= fdate($e['DateOfDeath']) ?><?php if ($e['Cause']): ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars($e['Cause']) ?></small><?php endif; ?></td>
      <td><?= htmlspecialchars($e['InformantName'] ?? '-') ?><?php if ($e['InformantRelation']): ?><br><small style="color:var(--text-muted);"><?= htmlspecialchars($e['InformantRelation']) ?></small><?php endif; ?></td>
      <td><?= htmlspecialchars($e['ReportedByName'] ?? '-') ?></td>
      <td><?php if ($e['ProofDocumentPath']): ?><a href="<?= htmlspecialchars($e['ProofDocumentPath']) ?>" target="_blank" class="btn-outline-x" style="font-size:11px;padding:3px 10px;">View ↗</a><?php else: ?><span style="color:var(--text-muted);font-size:12px;">None</span><?php endif; ?></td>
      <td><?= death_status_badge($e['Status']) ?>
        <?php if ($e['Status'] !== 'Pending'): ?>
          <br><small style="color:var(--text-muted);">by <?= htmlspecialchars($e['ReviewedByName'] ?? '-') ?></small>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($e['Status'] === 'Pending'): ?>
        <div class="d-flex gap-1">
          <form method="POST" onsubmit="return confirm('Approve this death entry?');">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="death_entry_id" value="<?= $e['DeathEntryID'] ?>">
            <button class="btn-outline-x" style="font-size:11px;padding:4px 10px;color:#1e9d6b;border-color:#1e9d6b;">Approve</button>
          </form>
          <form method="POST" onsubmit="return confirm('Reject this death entry?');">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="death_entry_id" value="<?= $e['DeathEntryID'] ?>">
            <button class="btn-outline-x" style="font-size:11px;padding:4px 10px;color:#dc3545;border-color:#dc3545;">Reject</button>
          </form>
        </div>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$entries): ?><tr><td colspan="8"><div class="empty-state">No death entries found.</div></td></tr><?php endif; ?>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
