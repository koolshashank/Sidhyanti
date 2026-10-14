<?php
$pageTitle = 'Loan Details';
$active = 'loans';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/loan_schedule_helper.php';

$id = (int)($_GET['id'] ?? 0);

$stageChangeError = null;

// Stage update — trigger automatically logs to Loan_Stage_History
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stage_id'])) {
    $remark = trim($_POST['stage_change_remark'] ?? '') ?: 'No reason given';

    $newStageName = $pdo->prepare("SELECT StageName FROM Loan_Stage_Master WHERE StageID=?");
    $newStageName->execute([$_POST['stage_id']]);
    $stageName = $newStageName->fetchColumn();

    // Moving to Disbursed requires a disbursement mode, and for NEFT/RTGS the
    // admin must re-type the member's bank account number -- mirrors the
    // validation api/v1/loans/disburse.php enforces for the mobile BM flow,
    // so the web path can't bypass it.
    $disbursementMode = trim((string)($_POST['disbursement_mode'] ?? ''));
    $confirmAccountNo = trim((string)($_POST['confirm_account_number'] ?? ''));

    if ($stageName === 'Disbursed') {
        if (!in_array($disbursementMode, ['Cash', 'NEFT', 'RTGS'], true)) {
            $stageChangeError = 'Select a Disbursement Mode (Cash, NEFT or RTGS) before moving this loan to Disbursed.';
        } elseif ($disbursementMode !== 'Cash') {
            $memberStmt = $pdo->prepare("SELECT MemberID FROM Loan WHERE LoanID = ?");
            $memberStmt->execute([$id]);
            $memberId = (int)$memberStmt->fetchColumn();
            $bankStmt = $pdo->prepare("SELECT AccountNumber FROM Member_Bank_Details WHERE MemberID = ?");
            $bankStmt->execute([$memberId]);
            $actualAccountNo = trim((string)$bankStmt->fetchColumn());
            if ($actualAccountNo === '') {
                $stageChangeError = "No bank account on file for this member -- cannot disburse via $disbursementMode.";
            } elseif ($confirmAccountNo === '' || $confirmAccountNo !== $actualAccountNo) {
                $stageChangeError = "The account number entered does not match the member's bank account on file.";
            }
        }
    }

    if ($stageChangeError === null) {
        $pdo->exec("EXEC sp_set_session_context @key = N'StageChangeRemark', @value = " . $pdo->quote($remark));
        $updateSql = "UPDATE Loan SET CurrentStageID=?, UpdatedAt=GETDATE(), UpdatedBy=?";
        $updateParams = [$_POST['stage_id'], current_staff()['StaffID']];
        if ($stageName === 'Disbursed') {
            $updateSql .= ", DisbursementMode=?";
            $updateParams[] = $disbursementMode;
        }
        $updateSql .= " WHERE LoanID=?";
        $updateParams[] = $id;
        $pdo->prepare($updateSql)->execute($updateParams);

        // If moving into Disbursed/Active and no schedule exists yet, generate one
        if (in_array($stageName, ['Disbursed','Active'])) {
            generate_repayment_schedule($pdo, $id);
        }
        header("Location: loan_view.php?id=$id"); exit;
    }
}
// Add note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_text'])) {
    $pdo->prepare("INSERT INTO Note (RelatedEntityType, RelatedEntityID, StaffID, NoteText) VALUES ('Loan',?,?,?)")
        ->execute([$id, current_staff()['StaffID'], $_POST['note_text']]);
    header("Location: loan_view.php?id=$id#notes"); exit;
}
// Change scheme (Product) -- gated by the "change_loan_scheme" role
// permission (configurable in Role Permissions), and only before
// disbursement: changing the amortization inputs after a repayment
// schedule already exists would desync the schedule from the loan.
$canChangeScheme = role_has_permission($pdo, current_staff()['RoleID'] ?? null, 'change_loan_scheme');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_scheme_id']) && $canChangeScheme) {
    $loanCheck = $pdo->prepare("SELECT DisbursementDate, SchemeID FROM Loan WHERE LoanID = ?");
    $loanCheck->execute([$id]);
    $loanRow = $loanCheck->fetch();
    $scheduleCount = $pdo->prepare("SELECT COUNT(*) FROM Repayment_Schedule WHERE LoanID = ?");
    $scheduleCount->execute([$id]);
    if ($loanRow && $loanRow['DisbursementDate'] === null && (int)$scheduleCount->fetchColumn() === 0) {
        $newScheme = $pdo->prepare("SELECT SchemeID, SchemeName, TenureMonths, DefaultLoanAmount, InterestRatePercent FROM Loan_Scheme WHERE SchemeID = ? AND IsActive = 1");
        $newScheme->execute([$_POST['new_scheme_id']]);
        $newScheme = $newScheme->fetch();
        if ($newScheme) {
            $oldSchemeName = $pdo->prepare("SELECT SchemeName FROM Loan_Scheme WHERE SchemeID = ?");
            $oldSchemeName->execute([$loanRow['SchemeID']]);
            $oldSchemeName = $oldSchemeName->fetchColumn();

            $pdo->prepare("UPDATE Loan SET SchemeID=?, LoanAmountApplied=?, LoanAmountSanctioned=CASE WHEN LoanAmountSanctioned IS NOT NULL THEN ? ELSE NULL END,
                            TenureMonths=?, InterestRatePercent=?, UpdatedAt=GETDATE(), UpdatedBy=? WHERE LoanID=?")
                ->execute([
                    $newScheme['SchemeID'], $newScheme['DefaultLoanAmount'], $newScheme['DefaultLoanAmount'],
                    $newScheme['TenureMonths'], $newScheme['InterestRatePercent'], current_staff()['StaffID'], $id,
                ]);
            $pdo->prepare("INSERT INTO Note (RelatedEntityType, RelatedEntityID, StaffID, NoteText) VALUES ('Loan',?,?,?)")
                ->execute([$id, current_staff()['StaffID'],
                    'Scheme changed from ' . ($oldSchemeName ?: 'None') . ' to ' . $newScheme['SchemeName']
                    . ' — amount and tenure updated accordingly.']);
        }
    }
    header("Location: loan_view.php?id=$id"); exit;
}
$stmt = $pdo->prepare("
  SELECT l.*, m.FirstName, m.LastName, m.MobileNo, m.EnrollmentNo,
         b.BranchName, c.CenterName, p.PurposeName, stg.StageName, officer.FullName AS officer_name,
         sch.SchemeName
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Loan_Purpose_Master p ON l.PurposeID = p.PurposeID
  LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  LEFT JOIN Loan_Scheme sch ON l.SchemeID = sch.SchemeID
  WHERE l.LoanID = ?
");
$stmt->execute([$id]);
$l = $stmt->fetch();
if (!$l) { echo '<div class="empty-state">Loan not found.</div>'; require __DIR__.'/includes/footer.php'; exit; }
$crumb = 'Loan ' . $l['LoanNo'];

require_once __DIR__ . '/includes/mask_helper.php';
$bankStmt = $pdo->prepare("SELECT AccountNumber FROM Member_Bank_Details WHERE MemberID = ?");
$bankStmt->execute([$l['MemberID']]);
$memberAccountNo = trim((string)$bankStmt->fetchColumn());
$memberAccountMasked = $memberAccountNo !== '' ? mask_account_number($memberAccountNo) : null;
$disbursedStageSort = 0;
foreach ($pdo->query("SELECT StageID, SortOrder FROM Loan_Stage_Master WHERE StageName = 'Disbursed'")->fetchAll() as $ds) {
    $disbursedStageSort = (int)$ds['SortOrder'];
}

// CollectionDate can come from either the settled Collection ledger or, for a
// payment taken today that hasn't been migrated into Collection yet, from the
// live Daily_Collection_Sheet — whichever has it.
$schedule = $pdo->prepare("
  SELECT rs.*,
    (SELECT MAX(c.CollectionDate) FROM Collection c WHERE c.ScheduleID = rs.ScheduleID) AS LedgerCollectionDate,
    (SELECT TOP 1 dcs.CollectionDate FROM Daily_Collection_Sheet dcs WHERE dcs.ScheduleID = rs.ScheduleID AND dcs.CollectionStatus IN ('Collected','Preclosed')) AS SheetCollectionDate
  FROM Repayment_Schedule rs WHERE rs.LoanID=? ORDER BY rs.InstallmentNo
");
$schedule->execute([$id]);
$schedule = $schedule->fetchAll();
foreach ($schedule as &$r) {
    $r['CollectionDate'] = $r['LedgerCollectionDate'] ?: $r['SheetCollectionDate'];
    // IsPaid on Repayment_Schedule can lag behind today's live Daily_Collection_Sheet entry
    $r['Paid'] = $r['IsPaid'] || $r['CollectionDate'];
}
unset($r);

$paidCount = count(array_filter($schedule, fn($r) => $r['Paid']));
$outstanding = array_sum(array_map(fn($r) => $r['Paid'] ? 0 : (float)$r['PrincipalDue'], $schedule));

$notes = $pdo->prepare("SELECT n.*, s.FullName FROM Note n LEFT JOIN Staff s ON n.StaffID=s.StaffID WHERE RelatedEntityType='Loan' AND RelatedEntityID=? ORDER BY n.CreatedAt DESC");
$notes->execute([$id]);
$notes = $notes->fetchAll();

// Arrears — any unpaid installment whose due date has already passed
$today = date('Y-m-d');
$overdueInstallments = array_values(array_filter($schedule, fn($r) => !$r['Paid'] && $r['DueDate'] < $today));
usort($overdueInstallments, fn($a, $b) => strtotime($a['DueDate']) <=> strtotime($b['DueDate']));
foreach ($overdueInstallments as &$r) {
    $r['DaysOverdue'] = (int)floor((strtotime($today) - strtotime($r['DueDate'])) / 86400);
}
unset($r);
$overdueCount = count($overdueInstallments);
$overdueAmount = array_sum(array_map(fn($r) => (float)$r['EMIAmount'], $overdueInstallments));
$maxDaysOverdue = $overdueInstallments ? max(array_column($overdueInstallments, 'DaysOverdue')) : 0;

$canChangeSchemeNow = $canChangeScheme && $l['DisbursementDate'] === null;
$schemeOptions = $canChangeSchemeNow
    ? $pdo->query("SELECT SchemeID, SchemeName, TenureMonths, DefaultLoanAmount, InterestRatePercent FROM Loan_Scheme WHERE IsActive = 1 ORDER BY SchemeName")->fetchAll()
    : [];

$stages = $pdo->query("SELECT StageID, StageName, SortOrder FROM Loan_Stage_Master ORDER BY SortOrder")->fetchAll();
$currentStageSort = 0;
foreach ($stages as $s) { if ($s['StageName'] === $l['StageName']) { $currentStageSort = $s['SortOrder']; break; } }

$stageHistory = $pdo->prepare("
  SELECT h.*, f.StageName AS FromStage, t.StageName AS ToStage, s.FullName AS staff_name
  FROM Loan_Stage_History h
  LEFT JOIN Loan_Stage_Master f ON h.FromStageID = f.StageID
  LEFT JOIN Loan_Stage_Master t ON h.ToStageID = t.StageID
  LEFT JOIN Staff s ON h.ChangedByStaffID = s.StaffID
  WHERE h.LoanID = ? ORDER BY h.ChangedAt DESC
");
$stageHistory->execute([$id]);
$stageHistory = $stageHistory->fetchAll();

// The client's own photograph is stored as a KYC document (DocTypeName = 'Client Photograph'),
// not in Member.Photo — that column is never populated in the migrated data.
$photoStmt = $pdo->prepare("
  SELECT TOP 1 k.DocFilePath FROM Member_KYC_Document k
  JOIN KYC_Document_Type_Master dt ON k.DocTypeID = dt.DocTypeID
  WHERE k.MemberID = ? AND dt.DocTypeName = 'Client Photograph' AND k.DocFilePath IS NOT NULL
");
$photoStmt->execute([$l['MemberID']]);
$clientPhotoUrl = $photoStmt->fetchColumn();

$paidPct = count($schedule) ? round($paidCount / count($schedule) * 100) : 0;

// Next installment still awaiting collection — blank once the loan is fully closed out
$upcomingEmi = null;
foreach ($schedule as $r) {
    if (!$r['Paid']) { $upcomingEmi = $r['EMIAmount']; break; }
}
?>

<div class="profile-header">
  <?php if ($clientPhotoUrl): ?>
    <img src="<?= htmlspecialchars($clientPhotoUrl) ?>" class="avatar" style="object-fit:cover;" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
    <div class="avatar" hidden><?= initials($l['FirstName'].' '.$l['LastName']) ?></div>
  <?php else: ?>
    <div class="avatar"><?= initials($l['FirstName'].' '.$l['LastName']) ?></div>
  <?php endif; ?>
  <div style="flex:1;">
    <h4 style="margin:0;font-weight:700;"><?= htmlspecialchars($l['LoanNo']) ?></h4>
    <div style="color:var(--text-muted);font-size:13px;">
      <a href="member_view.php?id=<?= $l['MemberID'] ?>"><?= htmlspecialchars($l['FirstName'].' '.$l['LastName']) ?></a>
      · <?= htmlspecialchars($l['MobileNo']) ?> · <?= htmlspecialchars($l['BranchName']) ?> / <?= htmlspecialchars($l['CenterName']) ?>
    </div>
    <div class="tag-row">
      <?= stage_badge($l['StageName']) ?>
      <span class="badge badge-secondary"><?= htmlspecialchars($l['PurposeName'] ?? 'General') ?></span>
      <span class="badge badge-secondary">Officer: <?= htmlspecialchars($l['officer_name'] ?? 'Unassigned') ?></span>
      <?php if (!empty($l['LoanCycle'])): ?>
        <span class="badge badge-secondary">Loan Cycle: <?= (int)$l['LoanCycle'] ?></span>
      <?php endif; ?>
    </div>
  </div>
  <form method="POST" class="d-flex gap-2" style="flex-wrap:wrap;align-items:center;">
    <select name="stage_id" id="stageSelectLoan" class="form-select" style="width:auto;" data-current-sort="<?= $currentStageSort ?>">
      <?php foreach ($stages as $s): ?><option value="<?= $s['StageID'] ?>" data-sort="<?= $s['SortOrder'] ?>" data-stage-name="<?= htmlspecialchars($s['StageName']) ?>" <?= $s['StageName']==$l['StageName']?'selected':'' ?>><?= htmlspecialchars($s['StageName']) ?></option><?php endforeach; ?>
    </select>
    <span id="disbursementFields" style="display:none;gap:8px;" class="d-flex">
      <select name="disbursement_mode" id="disbursementModeSelect" class="form-select" style="width:auto;">
        <option value="">Mode…</option>
        <option value="Cash">Cash</option>
        <option value="NEFT">NEFT</option>
        <option value="RTGS">RTGS</option>
      </select>
      <input type="text" name="confirm_account_number" id="confirmAccountInput" class="form-control" style="width:180px;display:none;" placeholder="Confirm account no.">
    </span>
    <input type="text" name="stage_change_remark" id="stageChangeRemarkLoan" class="form-control" style="width:200px;" placeholder="Reason for change (optional)">
    <button class="btn-navy" type="submit">Update Stage</button>
  </form>
</div>
<?php if ($stageChangeError): ?>
<div class="alert alert-danger" style="border-radius:10px;margin:-10px 0 16px;"><?= htmlspecialchars($stageChangeError) ?></div>
<?php endif; ?>
<?php if ($memberAccountMasked): ?>
<div style="font-size:11.5px;color:var(--text-muted);margin:-14px 0 6px;">
  Bank A/c on file: <strong><?= htmlspecialchars($memberAccountMasked) ?></strong>
</div>
<?php endif; ?>
<div style="font-size:11.5px;color:var(--text-muted);margin:-6px 0 16px;">
  Moving to <strong>Disbursed</strong> or <strong>Active</strong> automatically generates the repayment schedule (once).
</div>

<?php if ($canChangeSchemeNow): ?>
<div class="card-x" style="margin-bottom:16px;">
  <div class="section-title">Change Scheme</div>
  <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:10px;">
    Current: <strong><?= htmlspecialchars($l['SchemeName'] ?? '—') ?></strong> · Amount <?= money($l['LoanAmountApplied']) ?> · Tenure <?= (int)$l['TenureMonths'] ?> mo.
    Selecting a new scheme updates amount, tenure and interest rate to match it. Only available before disbursement.
  </div>
  <form method="POST" class="d-flex gap-2">
    <select name="new_scheme_id" class="form-select" style="width:auto;" required>
      <option value="">Select new scheme…</option>
      <?php foreach ($schemeOptions as $s): ?>
        <option value="<?= $s['SchemeID'] ?>" <?= (int)$s['SchemeID'] === (int)$l['SchemeID'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($s['SchemeName']) ?> — <?= money($s['DefaultLoanAmount']) ?> / <?= (int)$s['TenureMonths'] ?> mo / <?= $s['InterestRatePercent'] ?>%
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn-outline-x" type="submit" onclick="return confirm('Change this loan\'s scheme? Amount, tenure and interest rate will be updated to match the new scheme.');">Change Scheme</button>
  </form>
</div>
<?php endif; ?>

<script>
(function () {
  const sel = document.getElementById('stageSelectLoan');
  const remark = document.getElementById('stageChangeRemarkLoan');
  if (!sel || !remark) return;
  const currentSort = parseInt(sel.dataset.currentSort, 10);
  function updatePlaceholder() {
    const sort = parseInt(sel.options[sel.selectedIndex].dataset.sort, 10);
    remark.placeholder = sort < currentSort ? 'Required when moving backward' : 'Reason for change (optional)';
  }
  sel.addEventListener('change', updatePlaceholder);
  updatePlaceholder();

  const disbursementFields = document.getElementById('disbursementFields');
  const modeSelect = document.getElementById('disbursementModeSelect');
  const confirmInput = document.getElementById('confirmAccountInput');
  function updateDisbursementFields() {
    const stageName = sel.options[sel.selectedIndex].dataset.stageName;
    disbursementFields.style.display = stageName === 'Disbursed' ? 'flex' : 'none';
    confirmInput.style.display = modeSelect.value === 'NEFT' || modeSelect.value === 'RTGS' ? 'inline-block' : 'none';
  }
  sel.addEventListener('change', updateDisbursementFields);
  modeSelect.addEventListener('change', updateDisbursementFields);
  updateDisbursementFields();
})();
</script>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy">💰</div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= money($l['LoanAmountSanctioned'] ?: $l['LoanAmountApplied']) ?></div>
        <div class="lbl">Loan Amount</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold">📅</div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= $upcomingEmi ? money($upcomingEmi) : '-' ?></div>
        <div class="lbl">Upcoming EMI Amount</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft">⚠️</div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= money($outstanding) ?></div>
        <div class="lbl">Outstanding Principal</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal">✅</div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= $paidCount ?> / <?= count($schedule) ?></div>
        <div class="lbl">Installments Paid</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 align-items-start">
  <div class="col-lg-7">
    <div class="card-x mb-3" id="schedule">
      <div class="section-title">Repayment Schedule</div>
      <?php if ($schedule): ?>
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
        <div style="flex:1;height:8px;background:var(--border);border-radius:4px;overflow:hidden;">
          <div style="height:100%;background:linear-gradient(90deg,var(--teal),#14a9ab);width:<?= $paidPct ?>%;"></div>
        </div>
        <div style="font-size:12px;font-weight:700;color:var(--text-muted);white-space:nowrap;"><?= $paidPct ?>% paid</div>
      </div>
      <div class="table-scroll">
      <table class="table-x">
        <tr><th>Inst</th><th>Due Date</th><th>EMI</th><th>Collection Date</th><th>Status</th></tr>
        <?php foreach ($schedule as $r): ?>
        <tr>
          <td><?= $r['InstallmentNo'] ?></td>
          <td><?= fdate($r['DueDate']) ?></td>
          <td><?= money($r['EMIAmount']) ?></td>
          <td><?= $r['CollectionDate'] ? fdate($r['CollectionDate']) : '-' ?></td>
          <td><?= $r['Paid'] ? stage_badge('Paid') : (strtotime($r['DueDate']) < time() ? stage_badge('Overdue') : stage_badge('Pending')) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      </div>
      <?php else: ?>
        <div class="empty-state">Repayment schedule not generated yet — move this loan to "Disbursed" or "Active" to auto-generate it.</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card-x" id="notes">
      <div class="section-title">Loan Notes</div>
      <form method="POST" class="d-flex gap-2 mb-3">
        <input class="form-control" name="note_text" placeholder="Add a note..." required>
        <button class="btn-navy" type="submit">Add</button>
      </form>
      <?php foreach ($notes as $n): ?>
        <div style="border-bottom:1px solid var(--border);padding:10px 0;">
          <div style="font-size:13px;"><?= htmlspecialchars($n['NoteText']) ?></div>
          <div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($n['FullName']) ?> · <?= fdate($n['CreatedAt']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$notes): ?><div class="empty-state">No notes yet.</div><?php endif; ?>
    </div>

    <div class="card-x mt-3">
      <div class="section-title">
        Arrears &amp; Overdue Installments
        <?php if ($overdueCount): ?><span class="badge badge-danger"><?= $overdueCount ?> overdue</span><?php else: ?><span class="badge badge-success">No arrears</span><?php endif; ?>
      </div>
      <?php if ($overdueInstallments): ?>
        <div style="background:#fdeeee;border:1px solid #f6caca;border-radius:10px;padding:12px 14px;margin-bottom:14px;display:flex;flex-wrap:wrap;gap:14px;">
          <div><div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Overdue Amount</div><div style="font-weight:800;color:var(--danger);font-size:17px;"><?= money($overdueAmount) ?></div></div>
          <div><div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Installments Missed</div><div style="font-weight:800;font-size:17px;"><?= $overdueCount ?></div></div>
          <div><div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Oldest Overdue By</div><div style="font-weight:800;font-size:17px;"><?= $maxDaysOverdue ?> days</div></div>
        </div>
        <table class="table-x">
          <tr><th>Inst</th><th>Due Date</th><th>EMI</th><th>Overdue</th></tr>
          <?php foreach ($overdueInstallments as $r): ?>
          <tr>
            <td><?= $r['InstallmentNo'] ?></td>
            <td><?= fdate($r['DueDate']) ?></td>
            <td><?= money($r['EMIAmount']) ?></td>
            <td><span class="badge badge-<?= $r['DaysOverdue'] > 90 ? 'danger' : ($r['DaysOverdue'] > 30 ? 'warning' : 'secondary') ?>"><?= $r['DaysOverdue'] ?> days</span></td>
          </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?>
        <div class="empty-state">No arrears — all installments are on track 🎉</div>
      <?php endif; ?>
    </div>

    <div class="card-x mt-3">
      <div class="section-title">Stage History</div>
      <?php if ($stageHistory): ?>
      <div class="timeline">
        <?php foreach ($stageHistory as $h): ?>
        <div class="timeline-item">
          <div class="when"><?= fdate($h['ChangedAt']) ?> · <?= htmlspecialchars($h['staff_name'] ?? 'System') ?></div>
          <div class="what">
            <?= htmlspecialchars($h['FromStage'] ?? 'Created') ?> → <?= htmlspecialchars($h['ToStage']) ?>
            <?php if ($h['ChangeDirection'] === 'Backward'): ?>
              <span class="badge badge-danger">Moved Back</span>
            <?php endif; ?>
          </div>
          <?php if ($h['Remarks'] && $h['Remarks'] !== 'No reason given'): ?>
            <div style="font-size:12px;color:var(--text-muted);">Reason: <?= htmlspecialchars($h['Remarks']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <div class="empty-state">No stage changes yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
