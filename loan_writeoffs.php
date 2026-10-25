<?php
$pageTitle = 'Write-off Requests';
$crumb = 'NPA loans (90+ days overdue) requested for write-off by field/branch staff';
$active = 'loan_writeoffs';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can review write-off requests.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '') ?: null;

    $reqStmt = $pdo->prepare("SELECT RequestID, LoanID FROM Loan_WriteOff_Request WHERE RequestID = ? AND Status = 'Pending'");
    $reqStmt->execute([$requestId]);
    $req = $reqStmt->fetch();

    if ($req && in_array($action, ['approve', 'reject'], true)) {
        $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE Loan_WriteOff_Request SET Status = ?, ReviewedByStaffID = ?, ReviewedAt = GETDATE(), ReviewRemarks = ? WHERE RequestID = ?")
            ->execute([$newStatus, current_staff()['StaffID'], $remarks, $requestId]);
        if ($action === 'approve') {
            $pdo->prepare("
                UPDATE Loan SET
                    CurrentStageID = (SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Closed'),
                    SettlementTypeID = (SELECT SettlementTypeID FROM Settlement_Type_Master WHERE SettlementTypeName = 'Written Off')
                WHERE LoanID = ?
            ")->execute([$req['LoanID']]);
        }
        $pdo->commit();
    }
    header('Location: loan_writeoffs.php?saved=1'); exit;
}

$pending = $pdo->query("
  SELECT wr.RequestID, wr.LoanID, wr.RequestReason, wr.OutstandingAmount, wr.CreatedAt,
         l.LoanNo, m.FirstName, m.LastName, b.BranchName, c.CenterName, req.FullName AS RequestedByName
  FROM Loan_WriteOff_Request wr
  JOIN Loan l ON wr.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Staff req ON wr.RequestedByStaffID = req.StaffID
  WHERE wr.Status = 'Pending'
  ORDER BY wr.CreatedAt ASC
")->fetchAll();

$history = $pdo->query("
  SELECT wr.RequestID, wr.LoanID, wr.Status, wr.ReviewedAt, wr.OutstandingAmount, wr.ReviewRemarks,
         l.LoanNo, m.FirstName, m.LastName, rev.FullName AS ReviewedByName
  FROM Loan_WriteOff_Request wr
  JOIN Loan l ON wr.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Staff rev ON wr.ReviewedByStaffID = rev.StaffID
  WHERE wr.Status != 'Pending'
  ORDER BY wr.ReviewedAt DESC
")->fetchAll();
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Decision recorded.</div>
<?php endif; ?>

<div class="card-x" style="margin-bottom:20px;">
  <div class="section-title">Pending Write-off Requests</div>
  <?php if (!$pending): ?>
    <div class="empty-state">No pending write-off requests.</div>
  <?php else: ?>
  <table class="table-x">
    <tr><th>Loan</th><th>Client</th><th>Branch / Center</th><th>Outstanding</th><th>Requested By</th><th>Reason</th><th>Decision</th></tr>
    <?php foreach ($pending as $r): ?>
    <tr>
      <td><a href="loan_view.php?id=<?= (int)$r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
      <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?></td>
      <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?> / <?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
      <td><?= money($r['OutstandingAmount']) ?></td>
      <td><?= htmlspecialchars($r['RequestedByName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['RequestReason'] ?? '-') ?></td>
      <td>
        <form method="POST" style="display:flex;gap:6px;align-items:center;" onsubmit="return confirm('Confirm this decision?');">
          <input type="hidden" name="request_id" value="<?= (int)$r['RequestID'] ?>">
          <input type="text" name="remarks" placeholder="Remarks (optional)" class="form-control form-control-sm" style="width:140px;">
          <button type="submit" name="action" value="approve" class="btn btn-sm btn-outline-success">Approve</button>
          <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<div class="card-x">
  <div class="section-title">Decision History</div>
  <?php if (!$history): ?>
    <div class="empty-state">No decisions yet.</div>
  <?php else: ?>
  <table class="table-x">
    <tr><th>Loan</th><th>Client</th><th>Outstanding</th><th>Status</th><th>Reviewed By</th><th>Reviewed At</th><th>Remarks</th></tr>
    <?php foreach ($history as $r): ?>
    <tr>
      <td><a href="loan_view.php?id=<?= (int)$r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
      <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?></td>
      <td><?= money($r['OutstandingAmount']) ?></td>
      <td><span class="badge <?= $r['Status']==='Approved'?'badge-success':'badge-danger' ?>"><?= htmlspecialchars($r['Status']) ?></span></td>
      <td><?= htmlspecialchars($r['ReviewedByName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['ReviewedAt'] ? date('d M Y', strtotime($r['ReviewedAt'])) : '-') ?></td>
      <td><?= htmlspecialchars($r['ReviewRemarks'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
