<?php
$pageTitle = 'New Loan Application';
$active = 'loans';
require_once __DIR__ . '/includes/header.php';

$preselect = (int)($_GET['member_id'] ?? 0);
// Only members who are Verified or Converted can have a loan started
$members = $pdo->query("
  SELECT m.MemberID, m.EnrollmentNo, m.FirstName, m.LastName, m.BranchID, m.CenterID
  FROM Member m JOIN Lead_Stage_Master ls ON m.CurrentStageID = ls.StageID
  WHERE ls.StageName IN ('Verified','Converted')
  ORDER BY m.FirstName
")->fetchAll();

$purposes = $pdo->query("SELECT PurposeID, PurposeName FROM Loan_Purpose_Master ORDER BY PurposeName")->fetchAll();
$staff = $pdo->query("SELECT StaffID, FullName FROM Staff WHERE IsActive=1 ORDER BY FullName")->fetchAll();
$appliedStageId = lookup_id($pdo, 'Loan_Stage_Master', 'StageID', 'StageName', 'Applied');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mstmt = $pdo->prepare("SELECT BranchID, CenterID FROM Member WHERE MemberID=?");
    $mstmt->execute([$_POST['member_id']]);
    $mem = $mstmt->fetch();

    $amount = (float)$_POST['loan_amount'];
    $tenure = (int)$_POST['tenure_months'];

    $stmt = $pdo->prepare("INSERT INTO Loan
        (MemberID, BranchID, CenterID, PurposeID, LoanAmountApplied, TenureMonths, ApplicationDate,
         LoanOfficerStaffID, CurrentStageID, CreatedBy)
        VALUES (?,?,?,?,?,?, CAST(GETDATE() AS DATE), ?,?,?)");
    $stmt->execute([$_POST['member_id'], $mem['BranchID'], $mem['CenterID'], $_POST['purpose_id'] ?: null,
                     $amount, $tenure, $_POST['loan_officer_id'] ?: null, $appliedStageId, current_staff()['StaffID']]);
    $newId = $pdo->query("SELECT SCOPE_IDENTITY()")->fetchColumn();
    header('Location: loan_view.php?id=' . $newId);
    exit;
}
?>
<div class="card-x" style="max-width:700px;">
  <form method="POST">
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">Member</label>
        <select name="member_id" class="form-select" required>
          <option value="">Select verified/converted member</option>
          <?php foreach ($members as $m): ?>
            <option value="<?= $m['MemberID'] ?>" <?= $preselect==$m['MemberID']?'selected':'' ?>>
              <?= htmlspecialchars($m['EnrollmentNo'].' - '.$m['FirstName'].' '.$m['LastName']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Loan Purpose</label>
        <select name="purpose_id" class="form-select">
          <?php foreach ($purposes as $p): ?><option value="<?= $p['PurposeID'] ?>"><?= htmlspecialchars($p['PurposeName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Loan Officer</label>
        <select name="loan_officer_id" class="form-select">
          <option value="">Unassigned</option>
          <?php foreach ($staff as $s): ?><option value="<?= $s['StaffID'] ?>"><?= htmlspecialchars($s['FullName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Loan Amount (₹)</label>
        <input type="number" class="form-control" name="loan_amount" min="5000" step="1000" value="30000" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Tenure (months)</label>
        <select name="tenure_months" class="form-select"><option>12</option><option>18</option><option>24</option></select>
      </div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button class="btn-navy" type="submit">Submit Application</button>
      <a href="loans.php" class="btn-outline-x">Cancel</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
