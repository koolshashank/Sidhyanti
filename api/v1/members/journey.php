<?php
/**
 * GET /api/v1/members/journey.php?member_id={member_id}
 *
 * Every touchpoint across the lead + all its loans, newest first -- the
 * mobile equivalent of member_view.php's "Customer Journey" timeline.
 * Kept in exact sync with that web query; update both together.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$memberId = (int)($_GET['member_id'] ?? 0);
if ($memberId <= 0) {
    json_response(false, null, 'member_id is required', 422);
}

$memberStmt = $pdo->prepare("SELECT AssignedToStaffID, BranchID FROM Member WHERE MemberID = ?");
$memberStmt->execute([$memberId]);
$member = $memberStmt->fetch();
if (!$member) {
    json_response(false, null, 'Member not found', 404);
}
assert_member_access($member, $claims);

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
$journeyStmt = $pdo->prepare($journeySql);
$journeyStmt->execute(array_fill(0, substr_count($journeySql, '?'), $memberId));
$rows = $journeyStmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        $amount = $r['Amount'] !== null ? to_money($r['Amount']) : null;
        $detail = $r['EventDetail'];
        if ($amount !== null && !$detail) {
            $detail = null; // mobile formats the amount itself via the 'amount' field
        }
        return [
            'event_date'      => to_iso_date($r['EventDate']),
            'event_type'      => $r['EventType'],
            'event_title'     => $r['EventTitle'],
            'event_detail'    => $detail,
            'related_loan_no' => $r['RelatedLoanNo'],
            'performed_by'    => $r['PerformedBy'],
            'amount'          => $amount,
        ];
    }, $rows),
]);
