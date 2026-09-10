<?php
$pageTitle = 'Member Profile';
$active = 'members';
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

$id = (int)($_GET['id'] ?? 0);
$canTransferMemberCenter = role_has_permission($pdo, current_staff()['RoleID'] ?? null, 'transfer_member_center');

// Stage update — trigger automatically logs to Lead_Stage_History
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stage_id'])) {
    $remark = trim($_POST['stage_change_remark'] ?? '') ?: 'No reason given';
    $pdo->exec("EXEC sp_set_session_context @key = N'StageChangeRemark', @value = " . $pdo->quote($remark));
    $pdo->prepare("UPDATE Member SET CurrentStageID=?, UpdatedAt=GETDATE(), UpdatedBy=? WHERE MemberID=?")
        ->execute([$_POST['stage_id'], current_staff()['StaffID'], $id]);
    header("Location: member_view.php?id=$id"); exit;
}
// Add note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['note_text'])) {
    $pdo->prepare("INSERT INTO Note (RelatedEntityType, RelatedEntityID, StaffID, NoteText) VALUES ('Member',?,?,?)")
        ->execute([$id, current_staff()['StaffID'], $_POST['note_text']]);
    header("Location: member_view.php?id=$id#tabNotes"); exit;
}
// Save bank details (upsert — one record per member)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bank_name'])) {
    $bankFields = [
        trim($_POST['bank_name']) ?: null,
        trim($_POST['ifsc_code']) ?: null,
        trim($_POST['bank_branch']) ?: null,
        trim($_POST['bank_address']) ?: null,
        trim($_POST['account_number']) ?: null,
        trim($_POST['beneficiary_name']) ?: null,
    ];
    $existingBank = $pdo->prepare("SELECT BankDetailID FROM Member_Bank_Details WHERE MemberID = ?");
    $existingBank->execute([$id]);
    if ($existingBank->fetchColumn()) {
        $pdo->prepare("UPDATE Member_Bank_Details SET
            BankName=?, IFSCCode=?, BankBranch=?, BankAddress=?, AccountNumber=?, BeneficiaryName=?,
            UpdatedAt=GETDATE(), UpdatedBy=?
            WHERE MemberID=?")
            ->execute([...$bankFields, current_staff()['StaffID'], $id]);
    } else {
        $pdo->prepare("INSERT INTO Member_Bank_Details
            (MemberID, BankName, IFSCCode, BankBranch, BankAddress, AccountNumber, BeneficiaryName, CreatedBy)
            VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$id, ...$bankFields, current_staff()['StaffID']]);
    }
    header("Location: member_view.php?id=$id#bank-details"); exit;
}

$transferError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_new_center_id'])) {
    if (!$canTransferMemberCenter) {
        $transferError = 'You do not have permission to transfer this client to another center.';
    } else {
        $newCenterId = (int)$_POST['transfer_new_center_id'];
        $transferRemark = trim($_POST['transfer_remark'] ?? '') ?: null;

        $memberRow = $pdo->prepare("SELECT MemberID, CenterID, BranchID, AssignedToStaffID, FirstName, LastName FROM Member WHERE MemberID = ?");
        $memberRow->execute([$id]);
        $memberRow = $memberRow->fetch();

        $newCenter = $pdo->prepare("SELECT CenterID, CenterName, BranchID, ManagedByStaffID, MeetingDay FROM Center WHERE CenterID = ? AND IsActive = 1");
        $newCenter->execute([$newCenterId]);
        $newCenter = $newCenter->fetch();

        if (!$memberRow) {
            $transferError = 'Member not found.';
        } elseif (!$newCenter || (int)$newCenter['BranchID'] !== (int)$memberRow['BranchID']) {
            $transferError = 'Please select an active center in the same branch.';
        } elseif ((int)$memberRow['CenterID'] === $newCenterId) {
            $transferError = 'This client is already assigned to that center.';
        } else {
            $oldCenterId = $memberRow['CenterID'];
            $oldStaffId = $memberRow['AssignedToStaffID'];
            $newStaffId = $newCenter['ManagedByStaffID'];

            $oldCenterName = $pdo->prepare("SELECT CenterName FROM Center WHERE CenterID = ?");
            $oldCenterName->execute([$oldCenterId]);
            $oldCenterName = $oldCenterName->fetchColumn() ?: 'Unassigned';

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE Member SET CenterID=?, AssignedToStaffID=?, UpdatedAt=GETDATE(), UpdatedBy=? WHERE MemberID=?")
                    ->execute([$newCenterId, $newStaffId, current_staff()['StaffID'], $id]);

                $loans = $pdo->prepare("
                    SELECT l.LoanID FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
                    WHERE l.MemberID = ? AND stg.IsFinal = 0
                ");
                $loans->execute([$id]);
                $loanIds = array_column($loans->fetchAll(), 'LoanID');
                $loansMoved = 0;
                $shiftedCount = 0;

                if ($loanIds) {
                    $placeholders = implode(',', array_fill(0, count($loanIds), '?'));

                    $updLoan = $pdo->prepare("UPDATE Loan SET CenterID=?, LoanOfficerStaffID=? WHERE LoanID IN ($placeholders)");
                    $updLoan->execute(array_merge([$newCenterId, $newStaffId], $loanIds));
                    $loansMoved = $updLoan->rowCount();

                    $newDay = $newCenter['MeetingDay'];
                    $newDayIsNumeric = $newDay !== null && ctype_digit((string)$newDay) && (int)$newDay >= 1 && (int)$newDay <= 7;

                    if ($newDayIsNumeric) {
                        $scheduleStmt = $pdo->prepare("
                            SELECT ScheduleID, DueDate FROM Repayment_Schedule
                            WHERE LoanID IN ($placeholders) AND IsPaid = 0 AND DueDate >= CAST(GETDATE() AS DATE)
                        ");
                        $scheduleStmt->execute($loanIds);
                        $updateScheduleStmt = $pdo->prepare("UPDATE Repayment_Schedule SET DueDate=? WHERE ScheduleID=?");
                        $updateSheetStmt = $pdo->prepare("
                            UPDATE Daily_Collection_Sheet SET CollectionDueDate=?, CenterID=?, OfficerStaffID=?, UpdatedAt=GETDATE()
                            WHERE ScheduleID=? AND CollectionStatus IS NULL
                        ");
                        foreach ($scheduleStmt->fetchAll() as $r) {
                            $newDue = shift_due_date_to_weekday($r['DueDate'], (int)$newDay);
                            $updateScheduleStmt->execute([$newDue, $r['ScheduleID']]);
                            $updateSheetStmt->execute([$newDue, $newCenterId, $newStaffId, $r['ScheduleID']]);
                            $shiftedCount++;
                        }
                    } else {
                        $pdo->prepare("
                            UPDATE Daily_Collection_Sheet SET CenterID=?, OfficerStaffID=?, UpdatedAt=GETDATE()
                            WHERE LoanID IN ($placeholders) AND CollectionStatus IS NULL
                        ")->execute(array_merge([$newCenterId, $newStaffId], $loanIds));
                    }
                }

                $pdo->prepare("
                    INSERT INTO Member_Center_Transfer_History (MemberID, OldCenterID, NewCenterID, LoansTransferredCount, SchedulesShiftedCount, Remark, TransferredByStaffID)
                    VALUES (?,?,?,?,?,?,?)
                ")->execute([$id, $oldCenterId, $newCenterId, $loansMoved, $shiftedCount, $transferRemark, current_staff()['StaffID']]);

                $notifyIds = array_unique(array_filter([$oldStaffId, $newStaffId], fn($sid) => $sid && (int)$sid !== (int)current_staff()['StaffID']));
                if ($notifyIds) {
                    $memberName = trim($memberRow['FirstName'] . ' ' . $memberRow['LastName']);
                    $msg = 'Client ' . $memberName . ' has been transferred from ' . $oldCenterName . ' to ' . $newCenter['CenterName']
                        . '. ' . $loansMoved . ' active loan(s) moved with them.';
                    $notifyIns = $pdo->prepare("
                        INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
                        VALUES (?, 'Client Transferred', ?, 1, 'Member', ?, 0, GETDATE())
                    ");
                    foreach ($notifyIds as $sid) {
                        $notifyIns->execute([$sid, $msg, $id]);
                    }
                }

                $pdo->commit();
                header("Location: member_view.php?id=$id&transferred=" . $loansMoved); exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $transferError = 'Could not transfer this client. Please try again.';
            }
        }
    }
}

// vw_Member_Full doesn't expose EnrollmentDate — pull it from Member directly.
// It's the actual date the client was onboarded; CreatedAt is just the DB record's
// insert timestamp (identical for every migrated row, so useless as a "since" date).
$stmt = $pdo->prepare("SELECT v.*, mm.EnrollmentDate FROM vw_Member_Full v JOIN Member mm ON v.MemberID = mm.MemberID WHERE v.MemberID = ?");
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) { echo '<div class="empty-state">Member not found.</div>'; require __DIR__.'/includes/footer.php'; exit; }
$crumb = 'Member ' . $m['EnrollmentNo'];

$loans = $pdo->prepare("
  SELECT l.LoanID, l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, l.ApplicationDate, l.DisbursementDate, stg.StageName
  FROM Loan l LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE l.MemberID = ? ORDER BY l.LoanID DESC
");
$loans->execute([$id]);
$loans = $loans->fetchAll();

$loanValue = fn($l) => (float)($l['LoanAmountSanctioned'] ?: $l['LoanAmountApplied']);
$totalLoans = count($loans);
$activeLoans = count(array_filter($loans, fn($l) => $l['StageName'] === 'Active'));
$totalDisbursed = array_sum(array_map(fn($l) => $l['DisbursementDate'] ? $loanValue($l) : 0, $loans));

$outstandingStmt = $pdo->prepare("
  SELECT COALESCE(SUM(rs.PrincipalDue),0)
  FROM Repayment_Schedule rs JOIN Loan l ON rs.LoanID = l.LoanID
  WHERE l.MemberID = ? AND rs.IsPaid = 0
");
$outstandingStmt->execute([$id]);
$outstandingAmt = (float)$outstandingStmt->fetchColumn();

$centerInfo = $pdo->prepare("SELECT c.* FROM Center c JOIN Member m ON m.CenterID = c.CenterID WHERE m.MemberID = ?");
$centerInfo->execute([$id]);
$centerInfo = $centerInfo->fetch();

$dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

$otherCentersInBranch = [];
$transferHistory = [];
if ($canTransferMemberCenter && $centerInfo) {
    $otherCentersInBranch = $pdo->prepare("
        SELECT CenterID, CenterName, MeetingDay FROM Center
        WHERE BranchID = ? AND CenterID <> ? AND IsActive = 1 ORDER BY CenterName
    ");
    $otherCentersInBranch->execute([$centerInfo['BranchID'], $centerInfo['CenterID']]);
    $otherCentersInBranch = $otherCentersInBranch->fetchAll();

    $transferHistory = $pdo->prepare("
        SELECT h.*, oc.CenterName AS OldCenterName, nc.CenterName AS NewCenterName, s.FullName AS TransferredByName
        FROM Member_Center_Transfer_History h
        LEFT JOIN Center oc ON h.OldCenterID = oc.CenterID
        LEFT JOIN Center nc ON h.NewCenterID = nc.CenterID
        LEFT JOIN Staff s ON h.TransferredByStaffID = s.StaffID
        WHERE h.MemberID = ? ORDER BY h.TransferredAt DESC
    ");
    $transferHistory->execute([$id]);
    $transferHistory = $transferHistory->fetchAll();
}

$interactions = $pdo->prepare("
  SELECT i.*, it.TypeName, io.OutcomeName, s.FullName AS staff_name
  FROM Interaction i
  LEFT JOIN Interaction_Type_Master it ON i.InteractionTypeID = it.TypeID
  LEFT JOIN Interaction_Outcome_Master io ON i.OutcomeID = io.OutcomeID
  LEFT JOIN Staff s ON i.StaffID = s.StaffID
  WHERE i.MemberID = ? ORDER BY i.InteractionDate DESC
");
$interactions->execute([$id]);
$interactions = $interactions->fetchAll();

$notes = $pdo->prepare("SELECT n.*, s.FullName FROM Note n LEFT JOIN Staff s ON n.StaffID=s.StaffID WHERE RelatedEntityType='Member' AND RelatedEntityID=? ORDER BY n.CreatedAt DESC");
$notes->execute([$id]);
$notes = $notes->fetchAll();

$kyc = $pdo->prepare("SELECT k.*, dt.DocTypeName FROM Member_KYC_Document k JOIN KYC_Document_Type_Master dt ON k.DocTypeID=dt.DocTypeID WHERE k.MemberID=?");
$kyc->execute([$id]);
$kyc = $kyc->fetchAll();

$coBorrowers = $pdo->prepare("SELECT * FROM Co_Borrower WHERE MemberID = ?");
$coBorrowers->execute([$id]);
$coBorrowers = $coBorrowers->fetchAll();

$coBorrowerKyc = [];
if ($coBorrowers) {
    $coIds = array_column($coBorrowers, 'CoBorrowerID');
    $placeholders = implode(',', array_fill(0, count($coIds), '?'));
    $ckStmt = $pdo->prepare("
      SELECT k.*, dt.DocTypeName FROM Member_KYC_Document k
      JOIN KYC_Document_Type_Master dt ON k.DocTypeID = dt.DocTypeID
      WHERE k.CoBorrowerID IN ($placeholders)
    ");
    $ckStmt->execute($coIds);
    foreach ($ckStmt->fetchAll() as $row) {
        $coBorrowerKyc[$row['CoBorrowerID']][] = $row;
    }
}

// KYC files were migrated from the old server as full URLs (no local uploads folder exists
// in this app) — a NULL DocFilePath means the file was never migrated across.
function kycDocLink($path, $label = 'Document') {
    if (!$path) return '<span style="font-size:11.5px;color:var(--text-muted);">File not available (needs to be migrated from old server)</span>';
    return '<button type="button" class="btn-outline-x doc-view-btn" data-url="' . htmlspecialchars($path) . '" data-title="' . htmlspecialchars($label) . '" style="font-size:11px;padding:3px 10px;">View ↗</button>';
}

// The client's own photograph is stored as a KYC document (DocTypeName = 'Client Photograph'),
// not in Member.Photo — that column is never populated in the migrated data.
$clientPhotoUrl = null;
foreach ($kyc as $k) {
    if ($k['DocTypeName'] === 'Client Photograph' && $k['DocFilePath']) { $clientPhotoUrl = $k['DocFilePath']; break; }
}

$stageHistory = $pdo->prepare("
  SELECT h.*, f.StageName AS FromStage, t.StageName AS ToStage, s.FullName AS staff_name
  FROM Lead_Stage_History h
  LEFT JOIN Lead_Stage_Master f ON h.FromStageID = f.StageID
  LEFT JOIN Lead_Stage_Master t ON h.ToStageID = t.StageID
  LEFT JOIN Staff s ON h.ChangedByStaffID = s.StaffID
  WHERE h.MemberID = ? ORDER BY h.ChangedAt DESC
");
$stageHistory->execute([$id]);
$stageHistory = $stageHistory->fetchAll();

$stages = $pdo->query("SELECT StageID, StageName, SortOrder FROM Lead_Stage_Master ORDER BY SortOrder")->fetchAll();
$currentStageSort = 0;
foreach ($stages as $s) { if ($s['StageName'] === $m['CurrentStage']) { $currentStageSort = $s['SortOrder']; break; } }

$bankDetailsStmt = $pdo->prepare("SELECT * FROM Member_Bank_Details WHERE MemberID = ?");
$bankDetailsStmt->execute([$id]);
$bankDetails = $bankDetailsStmt->fetch();

// Customer Journey — every touchpoint across the lead + all its loans, newest first.
// Money-bearing event types (Disbursement, Payment) carry a raw Amount instead of a
// pre-formatted EventDetail so money() can be applied consistently after fetch.
$journeySql = "
  SELECT CAST(COALESCE(mm.EnrollmentDate, mm.CreatedAt) AS DATETIME) AS EventDate,
         'Lead Created' AS EventType,
         CAST('Lead created' AS NVARCHAR(MAX)) AS EventTitle,
         CAST(NULL AS NVARCHAR(MAX)) AS EventDetail,
         CAST(NULL AS NVARCHAR(50)) AS RelatedLoanNo,
         cs.FullName AS PerformedBy,
         CAST(NULL AS DECIMAL(18,2)) AS Amount
  FROM Member mm
  LEFT JOIN Staff cs ON mm.CreatedBy = cs.StaffID
  WHERE mm.MemberID = ?

  UNION ALL
  SELECT CAST(h.ChangedAt AS DATETIME), 'Stage Change',
         CAST(COALESCE(f.StageName,'Created') + ' → ' + t.StageName + CASE WHEN h.ChangeDirection = 'Backward' THEN ' (moved back)' ELSE '' END AS NVARCHAR(MAX)),
         CAST(CASE WHEN h.Remarks IS NOT NULL AND h.Remarks <> 'No reason given' THEN h.Remarks ELSE NULL END AS NVARCHAR(MAX)),
         CAST(NULL AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Lead_Stage_History h
  LEFT JOIN Lead_Stage_Master f ON h.FromStageID = f.StageID
  LEFT JOIN Lead_Stage_Master t ON h.ToStageID = t.StageID
  LEFT JOIN Staff s ON h.ChangedByStaffID = s.StaffID
  WHERE h.MemberID = ?

  UNION ALL
  SELECT CAST(i.InteractionDate AS DATETIME), 'Interaction',
         CAST(ISNULL(it.TypeName,'Interaction') + ' — ' + ISNULL(io.OutcomeName,'') AS NVARCHAR(MAX)),
         CAST(i.Remarks AS NVARCHAR(MAX)),
         CAST(NULL AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Interaction i
  LEFT JOIN Interaction_Type_Master it ON i.InteractionTypeID = it.TypeID
  LEFT JOIN Interaction_Outcome_Master io ON i.OutcomeID = io.OutcomeID
  LEFT JOIN Staff s ON i.StaffID = s.StaffID
  WHERE i.MemberID = ?

  UNION ALL
  SELECT CAST(n.CreatedAt AS DATETIME), 'Note',
         CAST('Note added' AS NVARCHAR(MAX)), CAST(n.NoteText AS NVARCHAR(MAX)),
         CAST(NULL AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Note n
  LEFT JOIN Staff s ON n.StaffID = s.StaffID
  WHERE n.RelatedEntityType='Member' AND n.RelatedEntityID = ?

  UNION ALL
  SELECT CAST(k.UploadedAt AS DATETIME), 'KYC Document',
         CAST(dt.DocTypeName + ' uploaded' AS NVARCHAR(MAX)),
         CAST(CASE WHEN k.IsVerified = 1 THEN 'Verified' ELSE 'Pending verification' END AS NVARCHAR(MAX)),
         CAST(NULL AS NVARCHAR(50)), vs.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Member_KYC_Document k
  JOIN KYC_Document_Type_Master dt ON k.DocTypeID = dt.DocTypeID
  LEFT JOIN Staff vs ON k.VerifiedByStaffID = vs.StaffID
  WHERE k.MemberID = ? AND k.UploadedAt IS NOT NULL

  UNION ALL
  SELECT CAST(l.ApplicationDate AS DATETIME), 'Loan Applied',
         CAST('Loan application submitted' AS NVARCHAR(MAX)), CAST(NULL AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), cs2.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Loan l
  LEFT JOIN Staff cs2 ON l.CreatedBy = cs2.StaffID
  WHERE l.MemberID = ? AND l.ApplicationDate IS NOT NULL

  UNION ALL
  SELECT CAST(h.ChangedAt AS DATETIME), 'Loan Stage Change',
         CAST(COALESCE(f.StageName,'Created') + ' → ' + t.StageName + CASE WHEN h.ChangeDirection = 'Backward' THEN ' (moved back)' ELSE '' END AS NVARCHAR(MAX)),
         CAST(CASE WHEN h.Remarks IS NOT NULL AND h.Remarks <> 'No reason given' THEN h.Remarks ELSE NULL END AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Loan_Stage_History h
  JOIN Loan l ON h.LoanID = l.LoanID
  LEFT JOIN Loan_Stage_Master f ON h.FromStageID = f.StageID
  LEFT JOIN Loan_Stage_Master t ON h.ToStageID = t.StageID
  LEFT JOIN Staff s ON h.ChangedByStaffID = s.StaffID
  WHERE l.MemberID = ?

  UNION ALL
  SELECT CAST(g.GRTDate AS DATETIME), 'GRT',
         CAST('GRT ' + CASE gs.GRTStatusName WHEN 'P' THEN 'Passed' WHEN 'F' THEN 'Failed' ELSE gs.GRTStatusName END AS NVARCHAR(MAX)),
         CAST(g.Remarks AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM GRT_Details g
  JOIN Loan l ON g.LoanID = l.LoanID
  LEFT JOIN GRT_Status_Master gs ON g.GRTStatusID = gs.GRTStatusID
  LEFT JOIN Staff s ON g.ConductedByStaffID = s.StaffID
  WHERE l.MemberID = ? AND g.GRTDate IS NOT NULL

  UNION ALL
  SELECT CAST(tv.TVRDate AS DATETIME), 'TVR',
         CAST('TVR ' + ts.TVRStatusName AS NVARCHAR(MAX)),
         CAST(tv.VerificationNotes AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM TVR_Details tv
  JOIN Loan l ON tv.LoanID = l.LoanID
  LEFT JOIN TVR_Status_Master ts ON tv.TVRStatusID = ts.TVRStatusID
  LEFT JOIN Staff s ON tv.ConductedByStaffID = s.StaffID
  WHERE l.MemberID = ? AND tv.TVRDate IS NOT NULL

  UNION ALL
  SELECT CAST(l.DisbursementDate AS DATETIME), 'Disbursement',
         CAST('Loan disbursed' AS NVARCHAR(MAX)), CAST(NULL AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), CAST(NULL AS NVARCHAR(200)),
         COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied)
  FROM Loan l
  WHERE l.MemberID = ? AND l.DisbursementDate IS NOT NULL

  UNION ALL
  SELECT CAST(c.CollectionDate AS DATETIME), 'Payment',
         CAST('EMI collected' AS NVARCHAR(MAX)), CAST(NULL AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), s.FullName, c.TotalCollected
  FROM Collection c
  JOIN Loan l ON c.LoanID = l.LoanID
  LEFT JOIN Staff s ON c.CollectedByStaffID = s.StaffID
  WHERE l.MemberID = ?

  UNION ALL
  SELECT CAST(t.CreatedAt AS DATETIME), 'Task Created',
         CAST(t.Title AS NVARCHAR(MAX)), CAST(t.Description AS NVARCHAR(MAX)),
         CAST(ml.LoanNo AS NVARCHAR(50)), cs3.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Task t
  LEFT JOIN Loan ml ON t.RelatedEntityType='Loan' AND t.RelatedEntityID = ml.LoanID
  LEFT JOIN Staff cs3 ON t.CreatedByStaffID = cs3.StaffID
  WHERE (t.RelatedEntityType='Member' AND t.RelatedEntityID = ?)
     OR (t.RelatedEntityType='Loan' AND t.RelatedEntityID IN (SELECT LoanID FROM Loan WHERE MemberID = ?))

  UNION ALL
  SELECT CAST(t.CompletedAt AS DATETIME), 'Task Completed',
         CAST(t.Title AS NVARCHAR(MAX)), CAST(NULL AS NVARCHAR(MAX)),
         CAST(ml.LoanNo AS NVARCHAR(50)), asf.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Task t
  LEFT JOIN Loan ml ON t.RelatedEntityType='Loan' AND t.RelatedEntityID = ml.LoanID
  LEFT JOIN Staff asf ON t.AssignedToStaffID = asf.StaffID
  WHERE t.CompletedAt IS NOT NULL
    AND ((t.RelatedEntityType='Member' AND t.RelatedEntityID = ?)
     OR (t.RelatedEntityType='Loan' AND t.RelatedEntityID IN (SELECT LoanID FROM Loan WHERE MemberID = ?)))

  ORDER BY EventDate DESC
";
$journey = $pdo->prepare($journeySql);
$journey->execute(array_fill(0, substr_count($journeySql, '?'), $id));
$journey = $journey->fetchAll();
foreach ($journey as &$j) {
    if ($j['Amount'] !== null && !$j['EventDetail']) {
        $j['EventDetail'] = 'Amount: ' . money($j['Amount']);
    }
}
unset($j);
?>

<?php if ($transferError): ?>
  <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= htmlspecialchars($transferError) ?></div>
<?php elseif (isset($_GET['transferred'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Client transferred successfully — <?= (int)$_GET['transferred'] ?> active loan(s) moved.</div>
<?php endif; ?>

<div class="profile-header">
  <?php if ($clientPhotoUrl): ?>
    <img src="<?= htmlspecialchars($clientPhotoUrl) ?>" class="avatar" style="object-fit:cover;" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
    <div class="avatar" hidden><?= initials($m['FirstName'].' '.$m['LastName']) ?></div>
  <?php else: ?>
    <div class="avatar"><?= initials($m['FirstName'].' '.$m['LastName']) ?></div>
  <?php endif; ?>
  <div style="flex:1;">
    <h4 style="margin:0;font-weight:700;"><?= htmlspecialchars(trim($m['FirstName'].' '.$m['MiddleName'].' '.$m['LastName'])) ?></h4>
    <div style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($m['EnrollmentNo']) ?> · <?= htmlspecialchars($m['MobileNo']) ?> · <?= htmlspecialchars($m['BranchName']) ?> / <?= htmlspecialchars($m['CenterName']) ?></div>
    <div class="tag-row">
      <?= stage_badge($m['CurrentStage']) ?>
      <span class="badge badge-secondary"><?= htmlspecialchars($m['OccupationName'] ?? 'Occupation not set') ?></span>
      <span class="badge badge-secondary">Officer: <?= htmlspecialchars($m['AssignedOfficer'] ?? 'Unassigned') ?></span>
      <span class="badge badge-<?= $m['IsActive']?'success':'danger' ?>"><?= $m['IsActive']?'Active':'Inactive' ?></span>
      <span class="badge badge-secondary">Member since <?= fdate($m['EnrollmentDate'] ?: $m['CreatedAt']) ?></span>
    </div>
  </div>
  <div class="d-flex flex-column gap-2 align-items-end">
    <form method="POST" class="d-flex gap-2">
      <select name="stage_id" id="stageSelectMember" class="form-select" style="width:auto;" data-current-sort="<?= $currentStageSort ?>">
        <?php foreach ($stages as $s): ?><option value="<?= $s['StageID'] ?>" data-sort="<?= $s['SortOrder'] ?>" <?= $s['StageName']==$m['CurrentStage']?'selected':'' ?>><?= htmlspecialchars($s['StageName']) ?></option><?php endforeach; ?>
      </select>
      <input type="text" name="stage_change_remark" id="stageChangeRemarkMember" class="form-control" style="width:200px;" placeholder="Reason for change (optional)">
      <button class="btn-navy" type="submit">Update Stage</button>
    </form>
    <?php if ($centerInfo && $centerInfo['Latitude'] && $centerInfo['Longitude']): ?>
      <button type="button" class="btn-outline-x map-btn" data-lat="<?= htmlspecialchars($centerInfo['Latitude']) ?>" data-lng="<?= htmlspecialchars($centerInfo['Longitude']) ?>" data-name="<?= htmlspecialchars($m['CenterName']) ?>">📍 View Center on Map</button>
    <?php endif; ?>
    <?php if ($canTransferMemberCenter): ?>
      <button type="button" class="btn-outline-x" data-bs-toggle="modal" data-bs-target="#transferCenterModal">🔁 Transfer Center</button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy">💼</div>
      <div class="stat-body">
        <div class="num"><?= $totalLoans ?></div>
        <div class="lbl">Total Loans</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal">✅</div>
      <div class="stat-body">
        <div class="num"><?= $activeLoans ?></div>
        <div class="lbl">Active Loans</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold">📈</div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= money($totalDisbursed) ?></div>
        <div class="lbl">Total Disbursed</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft">⚠️</div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= money($outstandingAmt) ?></div>
        <div class="lbl">Outstanding Principal</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 align-items-start">
  <div class="col-lg-5">
    <div class="card-x mb-3">
      <div class="card-title-plain">Profile</div>
      <ul class="nav nav-tabs-x" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabMember" type="button">Member</button></li>
        <?php foreach ($coBorrowers as $ci => $cb): ?>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCoBorrower<?= $ci ?>" type="button"><?= count($coBorrowers) > 1 ? 'Co-Borrower '.($ci+1) : 'Co-Borrower' ?></button></li>
        <?php endforeach; ?>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="tabMember">
          <div class="detail-grid">
            <div class="item"><div class="k">Father / Husband</div><div class="v"><?= htmlspecialchars($m['FatherHusbandName'] ?: '-') ?></div></div>
            <div class="item"><div class="k">Date of Birth</div><div class="v"><?= fdate($m['DOB']) ?></div></div>
            <div class="item"><div class="k">Gender</div><div class="v"><?= htmlspecialchars($m['Gender'] ?: '-') ?></div></div>
            <div class="item"><div class="k">Marital Status</div><div class="v"><?= htmlspecialchars($m['MaritalStatus'] ?: '-') ?></div></div>
            <div class="item" style="grid-column:1 / -1;"><div class="k">Address</div><div class="v"><?= htmlspecialchars(trim(($m['Address'] ?: '').($m['VillageCity'] ? ', '.$m['VillageCity'] : '')) ?: '-') ?></div></div>
            <div class="item"><div class="k">District</div><div class="v"><?= htmlspecialchars($m['DistrictName'] ?: '-') ?></div></div>
            <div class="item"><div class="k">State</div><div class="v"><?= htmlspecialchars($m['StateName'] ?: '-') ?></div></div>
            <div class="item"><div class="k">Pincode</div><div class="v"><?= htmlspecialchars($m['Pincode'] ?: '-') ?></div></div>
            <div class="item"><div class="k">Lead Source</div><div class="v"><?= htmlspecialchars($m['LeadSource'] ?: '-') ?></div></div>
            <div class="item"><div class="k">Aadhar No.</div><div class="v"><?= htmlspecialchars($m['AadharNo'] ?: '-') ?></div></div>
            <div class="item"><div class="k">PAN No.</div><div class="v"><?= htmlspecialchars($m['PANNo'] ?: '-') ?></div></div>
          </div>
        </div>
        <?php foreach ($coBorrowers as $ci => $cb): ?>
        <div class="tab-pane fade" id="tabCoBorrower<?= $ci ?>">
          <div class="detail-grid">
            <div class="item" style="grid-column:1 / -1;"><div class="k">Name</div><div class="v"><?= htmlspecialchars(trim($cb['FirstName'].' '.$cb['MiddleName'].' '.$cb['LastName'])) ?></div></div>
            <div class="item"><div class="k">Relation to Member</div><div class="v"><?= htmlspecialchars($cb['RelationToMember'] ?: '-') ?></div></div>
            <div class="item"><div class="k">Mobile</div><div class="v"><?= htmlspecialchars($cb['MobileNo'] ?: '-') ?></div></div>
            <div class="item"><div class="k">Date of Birth</div><div class="v"><?= fdate($cb['DOB']) ?></div></div>
            <div class="item"><div class="k">Gender</div><div class="v"><?= htmlspecialchars($cb['Gender'] ?: '-') ?></div></div>
            <div class="item" style="grid-column:1 / -1;"><div class="k">Address</div><div class="v"><?= htmlspecialchars(trim(($cb['Address'] ?: '').($cb['VillageCity'] ? ', '.$cb['VillageCity'] : '')) ?: '-') ?></div></div>
            <div class="item"><div class="k">Aadhar No.</div><div class="v"><?= htmlspecialchars($cb['AadharNo'] ?: '-') ?></div></div>
            <div class="item"><div class="k">PAN No.</div><div class="v"><?= htmlspecialchars($cb['PANNo'] ?: '-') ?></div></div>
            <div class="item"><div class="k">Voter ID</div><div class="v"><?= htmlspecialchars($cb['VoterID'] ?: '-') ?></div></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($centerInfo): ?>
    <div class="card-x mb-3">
      <div class="section-title">Center Info</div>
      <div class="detail-grid">
        <div class="item"><div class="k">Center Code</div><div class="v"><?= htmlspecialchars($centerInfo['CenterCode']) ?></div></div>
        <div class="item"><div class="k">Meeting Day</div><div class="v"><?= htmlspecialchars($dayNames[$centerInfo['MeetingDay']] ?? '-') ?></div></div>
        <div class="item"><div class="k">Meeting Time</div><div class="v"><?= htmlspecialchars($centerInfo['MeetingTime'] ?: '-') ?></div></div>
        <div class="item"><div class="k">Place</div><div class="v"><?= htmlspecialchars($centerInfo['CenterPlace'] ?: '-') ?></div></div>
        <div class="item"><div class="k">Contact Person</div><div class="v"><?= htmlspecialchars($centerInfo['ContactPerson'] ?: '-') ?></div></div>
        <div class="item"><div class="k">Contact No.</div><div class="v"><?= htmlspecialchars($centerInfo['ContactNo'] ?: '-') ?></div></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card-x">
      <div class="card-title-plain">Documents</div>
      <ul class="nav nav-tabs-x" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabClientKyc" type="button">Client KYC (<?= count($kyc) ?>)</button></li>
        <?php foreach ($coBorrowers as $ci => $cb): ?>
          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCoKyc<?= $ci ?>" type="button"><?= count($coBorrowers) > 1 ? 'Co-Borrower '.($ci+1).' KYC' : 'Co-Borrower KYC' ?> (<?= count($coBorrowerKyc[$cb['CoBorrowerID']] ?? []) ?>)</button></li>
        <?php endforeach; ?>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="tabClientKyc">
          <?php if ($kyc): ?>
            <table class="table-x">
              <tr><th>Document</th><th>Status</th><th></th></tr>
              <?php foreach ($kyc as $k): ?>
              <tr>
                <td><?= htmlspecialchars($k['DocTypeName']) ?></td>
                <td><?= $k['IsVerified'] ? '<span class="badge badge-success">Verified</span>' : '<span class="badge badge-warning">Pending</span>' ?></td>
                <td><?= kycDocLink($k['DocFilePath'], $k['DocTypeName']) ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          <?php else: ?>
            <div class="empty-state">No KYC documents on file yet.</div>
          <?php endif; ?>
        </div>
        <?php foreach ($coBorrowers as $ci => $cb): ?>
        <div class="tab-pane fade" id="tabCoKyc<?= $ci ?>">
          <?php $docs = $coBorrowerKyc[$cb['CoBorrowerID']] ?? []; ?>
          <?php if ($docs): ?>
            <table class="table-x">
              <tr><th>Document</th><th>Status</th><th></th></tr>
              <?php foreach ($docs as $k): ?>
              <tr>
                <td><?= htmlspecialchars($k['DocTypeName']) ?></td>
                <td><?= $k['IsVerified'] ? '<span class="badge badge-success">Verified</span>' : '<span class="badge badge-warning">Pending</span>' ?></td>
                <td><?= kycDocLink($k['DocFilePath'], $k['DocTypeName']) ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          <?php else: ?>
            <div class="empty-state">No KYC documents on file for the co-borrower yet.</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card-x mb-3">
      <div class="section-title">Loans (<?= count($loans) ?>) <a href="loan_add.php?member_id=<?= $id ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">+ New Loan</a></div>
      <?php if ($loans): ?>
      <table class="table-x">
        <tr><th>Loan No</th><th>Amount</th><th>Stage</th><th>Applied</th><th></th></tr>
        <?php foreach ($loans as $l): ?>
        <tr>
          <td><?= htmlspecialchars($l['LoanNo']) ?></td>
          <td style="font-weight:700;color:var(--navy);"><?= money($loanValue($l)) ?></td>
          <td><?= stage_badge($l['StageName']) ?></td>
          <td><?= fdate($l['ApplicationDate']) ?></td>
          <td><a href="loan_view.php?id=<?= $l['LoanID'] ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <div class="empty-state">No loans yet for this member.</div>
      <?php endif; ?>
    </div>

    <div class="card-x">
      <div class="card-title-plain">Activity</div>
      <ul class="nav nav-tabs-x" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabInteractions" type="button">Interactions (<?= count($interactions) ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabNotes" type="button">Notes (<?= count($notes) ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabStageHistory" type="button">Stage History</button></li>
      </ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="tabInteractions">
          <?php if ($interactions): ?>
          <div class="timeline">
            <?php foreach ($interactions as $i): ?>
            <div class="timeline-item">
              <div class="when"><?= fdate($i['InteractionDate']) ?> · <?= htmlspecialchars($i['staff_name'] ?? 'Unknown') ?></div>
              <div class="what"><?= htmlspecialchars($i['TypeName']) ?> — <?= htmlspecialchars($i['OutcomeName']) ?></div>
              <div style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($i['Remarks']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
            <div class="empty-state">No interactions logged yet.</div>
          <?php endif; ?>
        </div>
        <div class="tab-pane fade" id="tabNotes">
          <form method="POST" class="d-flex gap-2 mb-3">
            <input class="form-control" name="note_text" placeholder="Add a note about this member..." required>
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
        <div class="tab-pane fade" id="tabStageHistory">
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

    <div class="card-x mt-3" id="bank-details">
      <div class="section-title">
        Bank Details
        <button type="button" class="btn-outline-x" style="font-size:12px;padding:5px 12px;" data-bs-toggle="modal" data-bs-target="#editBankDetails"><?= $bankDetails ? 'Edit' : '+ Add' ?></button>
      </div>
      <?php if ($bankDetails): ?>
      <div class="detail-grid">
        <div class="item"><div class="k">Bank Name</div><div class="v"><?= htmlspecialchars($bankDetails['BankName'] ?: '-') ?></div></div>
        <div class="item"><div class="k">IFSC Code</div><div class="v"><?= htmlspecialchars($bankDetails['IFSCCode'] ?: '-') ?></div></div>
        <div class="item"><div class="k">Bank Branch</div><div class="v"><?= htmlspecialchars($bankDetails['BankBranch'] ?: '-') ?></div></div>
        <div class="item" style="grid-column:1 / -1;"><div class="k">Bank Address</div><div class="v"><?= htmlspecialchars($bankDetails['BankAddress'] ?: '-') ?></div></div>
        <div class="item"><div class="k">Account Number</div><div class="v"><?= htmlspecialchars($bankDetails['AccountNumber'] ?: '-') ?></div></div>
        <div class="item"><div class="k">Beneficiary Name</div><div class="v"><?= htmlspecialchars($bankDetails['BeneficiaryName'] ?: '-') ?></div></div>
      </div>
      <?php else: ?>
        <div class="empty-state">No bank details on file yet.</div>
      <?php endif; ?>
    </div>

    <div class="card-x mb-3">
      <div class="section-title">Customer Journey (<?= count($journey) ?> events)</div>
      <?php if ($journey): ?>
      <div class="timeline">
        <?php
        $icons = ['Lead Created'=>'🎯','Stage Change'=>'🔄','Interaction'=>'📞','Note'=>'📝','KYC Document'=>'📄',
                  'Loan Applied'=>'💼','Loan Stage Change'=>'📈','GRT'=>'👥','TVR'=>'☎️','Disbursement'=>'💰',
                  'Payment'=>'✅','Task Created'=>'📌','Task Completed'=>'✔️'];
        foreach ($journey as $j): ?>
        <div class="timeline-item">
          <div class="when"><?= fdate($j['EventDate']) ?> · <?= $icons[$j['EventType']] ?? '•' ?> <?= htmlspecialchars($j['EventType']) ?>
            <?= $j['RelatedLoanNo'] ? ' · <span style="color:var(--gold);">'.htmlspecialchars($j['RelatedLoanNo']).'</span>' : '' ?>
            <?= $j['PerformedBy'] ? ' · '.htmlspecialchars($j['PerformedBy']) : '' ?>
          </div>
          <div class="what"><?= htmlspecialchars($j['EventTitle']) ?></div>
          <?php if ($j['EventDetail']): ?>
            <div style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($j['EventDetail']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <div class="empty-state">No journey events recorded yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
  const sel = document.getElementById('stageSelectMember');
  const remark = document.getElementById('stageChangeRemarkMember');
  if (!sel || !remark) return;
  const currentSort = parseInt(sel.dataset.currentSort, 10);
  function updatePlaceholder() {
    const sort = parseInt(sel.options[sel.selectedIndex].dataset.sort, 10);
    remark.placeholder = sort < currentSort ? 'Required when moving backward' : 'Reason for change (optional)';
  }
  sel.addEventListener('change', updatePlaceholder);
  updatePlaceholder();
})();
</script>

<script>
if (window.location.hash === '#tabNotes') {
  const trigger = document.querySelector('[data-bs-target="#tabNotes"]');
  if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
}
</script>

<!-- ---- Document preview modal (KYC files live on the old legacy server) ---- -->
<div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="docModalTitle">Document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="text-align:center;background:#f4f6f9;">
        <img id="docModalImg" style="max-width:100%;max-height:70vh;border-radius:8px;" onload="this.hidden=false;document.getElementById('docModalError').hidden=true;" onerror="this.hidden=true;document.getElementById('docModalError').hidden=false;">
        <div id="docModalError" hidden style="padding:50px 20px;color:var(--text-muted);">Could not load this document — the legacy file server may be unreachable.</div>
      </div>
      <div class="modal-footer">
        <a id="docModalOpenLink" href="#" target="_blank" rel="noopener" class="btn-outline-x">Open in New Tab</a>
        <button type="button" class="btn-navy" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.doc-view-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const url = btn.dataset.url;
    document.getElementById('docModalTitle').textContent = btn.dataset.title || 'Document';
    document.getElementById('docModalOpenLink').href = url;
    const img = document.getElementById('docModalImg');
    document.getElementById('docModalError').hidden = true;
    img.hidden = true;
    img.src = url;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('docModal')).show();
  });
});
</script>

<?php if ($centerInfo && $centerInfo['Latitude'] && $centerInfo['Longitude']): ?>
<div class="modal fade" id="mapModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mapModalTitle">Center Location</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:0;">
        <div id="centerMap" style="height:320px;border-radius:0 0 var(--radius) var(--radius);"></div>
      </div>
      <div class="modal-footer">
        <a id="gmapsLink" href="#" target="_blank" rel="noopener" class="btn-outline-x">Open in Google Maps</a>
        <button type="button" class="btn-navy" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl: 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/images/marker-shadow.png',
});

let centerMap = null, centerMarker = null, pendingLat = 0, pendingLng = 0;
const mapModalEl = document.getElementById('mapModal');

mapModalEl.addEventListener('shown.bs.modal', function () {
  if (!centerMap) {
    centerMap = L.map('centerMap').setView([pendingLat, pendingLng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
    }).addTo(centerMap);
    centerMarker = L.marker([pendingLat, pendingLng]).addTo(centerMap);
  } else {
    centerMap.setView([pendingLat, pendingLng], 15);
    centerMarker.setLatLng([pendingLat, pendingLng]);
  }
  centerMap.invalidateSize();
});

document.querySelectorAll('.map-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    pendingLat = parseFloat(btn.dataset.lat);
    pendingLng = parseFloat(btn.dataset.lng);
    document.getElementById('mapModalTitle').textContent = '📍 ' + btn.dataset.name;
    document.getElementById('gmapsLink').href = `https://www.google.com/maps?q=${pendingLat},${pendingLng}`;
    bootstrap.Modal.getOrCreateInstance(mapModalEl).show();
  });
});
</script>
<?php endif; ?>

<div class="modal fade" id="editBankDetails" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Bank Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">IFSC Code</label>
              <div class="d-flex gap-2">
                <input class="form-control" id="ifscCodeInput" name="ifsc_code" style="text-transform:uppercase;" maxlength="11" placeholder="e.g. SBIN0001234" value="<?= htmlspecialchars($bankDetails['IFSCCode'] ?? '') ?>">
                <button type="button" class="btn-outline-x" id="ifscLookupBtn" style="white-space:nowrap;">🔍 Lookup</button>
              </div>
              <div id="ifscLookupStatus" style="font-size:11.5px;margin-top:5px;min-height:14px;"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Bank Name <span style="font-weight:400;color:var(--text-muted);">(auto-filled from IFSC)</span></label>
              <input class="form-control" id="bankNameInput" name="bank_name" readonly style="background:#f4f6f9;" value="<?= htmlspecialchars($bankDetails['BankName'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Bank Branch <span style="font-weight:400;color:var(--text-muted);">(auto-filled from IFSC)</span></label>
              <input class="form-control" id="bankBranchInput" name="bank_branch" readonly style="background:#f4f6f9;" value="<?= htmlspecialchars($bankDetails['BankBranch'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Account Number</label>
              <input class="form-control" name="account_number" value="<?= htmlspecialchars($bankDetails['AccountNumber'] ?? '') ?>">
            </div>
            <div class="col-md-12">
              <label class="form-label">Bank Address <span style="font-weight:400;color:var(--text-muted);">(auto-filled from IFSC)</span></label>
              <input class="form-control" id="bankAddressInput" name="bank_address" readonly style="background:#f4f6f9;" value="<?= htmlspecialchars($bankDetails['BankAddress'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Beneficiary Name</label>
              <input class="form-control" name="beneficiary_name" value="<?= htmlspecialchars($bankDetails['BeneficiaryName'] ?? '') ?>">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-outline-x" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-navy">Save Bank Details</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  const ifscInput = document.getElementById('ifscCodeInput');
  if (!ifscInput) return;
  const bankNameInput = document.getElementById('bankNameInput');
  const bankBranchInput = document.getElementById('bankBranchInput');
  const bankAddressInput = document.getElementById('bankAddressInput');
  const statusEl = document.getElementById('ifscLookupStatus');
  const lookupBtn = document.getElementById('ifscLookupBtn');

  async function lookupIfsc() {
    const code = ifscInput.value.trim().toUpperCase();
    ifscInput.value = code;
    if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(code)) {
      statusEl.textContent = code ? 'Enter a valid 11-character IFSC code' : '';
      statusEl.style.color = 'var(--danger)';
      return;
    }
    statusEl.textContent = 'Looking up…';
    statusEl.style.color = 'var(--text-muted)';
    try {
      // Razorpay's free public IFSC lookup — no API key required.
      const res = await fetch('https://ifsc.razorpay.com/' + code);
      if (!res.ok) throw new Error('not found');
      const data = await res.json();
      bankNameInput.value = data.BANK || '';
      bankBranchInput.value = data.BRANCH || '';
      bankAddressInput.value = data.ADDRESS || '';
      statusEl.textContent = '✓ ' + data.BANK + ' — ' + data.BRANCH;
      statusEl.style.color = 'var(--teal)';
    } catch (e) {
      statusEl.textContent = 'IFSC not found — please enter bank details manually';
      statusEl.style.color = 'var(--danger)';
    }
  }

  ifscInput.addEventListener('blur', lookupIfsc);
  ifscInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); lookupIfsc(); }
  });
  lookupBtn.addEventListener('click', lookupIfsc);
})();
</script>

<?php if ($canTransferMemberCenter): ?>
<div class="modal fade" id="transferCenterModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Transfer Center — <?= htmlspecialchars(trim($m['FirstName'].' '.$m['LastName'])) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">
            Currently at <strong><?= htmlspecialchars($m['CenterName'] ?? '-') ?></strong> (<?= htmlspecialchars($m['BranchName'] ?? '-') ?>).
            Transferring moves this client, their <strong>active (non-closed) loans</strong>, and the officer assignment to the new center's manager.
            If the new center's meeting day differs, <strong>upcoming unpaid</strong> installments are realigned to it; overdue/paid ones are left untouched.
            Only centers in the same branch can be selected.
          </div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">New Center <span class="required-mark">*</span></label>
              <select class="form-select" name="transfer_new_center_id" required>
                <option value="">Select center</option>
                <?php foreach ($otherCentersInBranch as $oc): ?>
                  <option value="<?= $oc['CenterID'] ?>"><?= htmlspecialchars($oc['CenterName']) ?><?= isset($dayNames[$oc['MeetingDay']]) ? ' — ' . $dayNames[$oc['MeetingDay']] : '' ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!$otherCentersInBranch): ?><div style="font-size:11.5px;color:var(--danger);margin-top:4px;">No other active centers found in this branch.</div><?php endif; ?>
            </div>
            <div class="col-12">
              <label class="form-label">Reason (optional)</label>
              <input class="form-control" name="transfer_remark" placeholder="Why is this client being transferred?">
            </div>
          </div>

          <?php if ($transferHistory): ?>
          <div class="form-section-label" style="margin-top:18px;"><span class="dot">🕘</span> Transfer History</div>
          <div class="table-scroll" style="max-height:180px;">
            <table class="table-x">
              <tr><th>When</th><th>From → To</th><th>Loans</th><th>By</th></tr>
              <?php foreach ($transferHistory as $h): ?>
              <tr>
                <td style="font-size:11.5px;"><?= fdate($h['TransferredAt']) ?></td>
                <td style="font-size:11.5px;"><?= htmlspecialchars($h['OldCenterName'] ?? 'Unassigned') ?> → <?= htmlspecialchars($h['NewCenterName'] ?? '-') ?></td>
                <td style="font-size:11.5px;"><?= (int)$h['LoansTransferredCount'] ?></td>
                <td style="font-size:11.5px;"><?= htmlspecialchars($h['TransferredByName'] ?? '-') ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-outline-x" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-navy" <?= !$otherCentersInBranch?'disabled':'' ?> onclick="return confirm('Transfer this client to the selected center? Their active loans will move with them.');">Transfer Center</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
