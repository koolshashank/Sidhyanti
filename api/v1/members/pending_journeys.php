<?php
/**
 * GET /api/v1/members/pending_journeys.php
 *
 * Lists every Member this staff sourced that hasn't reached a Loan yet (the
 * client-sourcing journey was interrupted somewhere between creating the
 * lead and submitting the Loan Proposal). Lets the mobile app's "Pending
 * Journey" list send the field officer back into the exact right screen for
 * any one of them, instead of only ever remembering the single most recent
 * one.
 *
 * Member KYC / Co-Borrower KYC capture state is device-local only (never
 * synced), so it can't be used to pinpoint the exact screen -- the resume
 * point is only ever one of two things we can actually verify server-side:
 *   - no Co_Borrower row yet -> resume_stage = "coborrower"
 *   - Co_Borrower exists but no Loan yet -> resume_stage = "kyc_member"
 *     (send them back through the KYC screens; nothing there is destructive
 *     to repeat, and it naturally chains into Loan Proposal same as normal)
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
[$scopeSql, $scopeParams] = member_scope_where($claims, 'm');

$where = ["m.IsActive = 1", "NOT EXISTS (SELECT 1 FROM Loan l WHERE l.MemberID = m.MemberID)"];
$params = [];
if ($scopeSql !== '') { $where[] = $scopeSql; array_push($params, ...$scopeParams); }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
  SELECT m.MemberID, m.FirstName, m.LastName, m.CenterID, c.CenterName, m.CreatedAt,
    (SELECT TOP 1 CoBorrowerID FROM Co_Borrower WHERE MemberID = m.MemberID ORDER BY CoBorrowerID DESC) AS CoBorrowerID,
    (SELECT TOP 1 FirstName FROM Co_Borrower WHERE MemberID = m.MemberID ORDER BY CoBorrowerID DESC) AS CbFirstName,
    (SELECT TOP 1 MiddleName FROM Co_Borrower WHERE MemberID = m.MemberID ORDER BY CoBorrowerID DESC) AS CbMiddleName,
    (SELECT TOP 1 LastName FROM Co_Borrower WHERE MemberID = m.MemberID ORDER BY CoBorrowerID DESC) AS CbLastName
  FROM Member m
  LEFT JOIN Center c ON m.CenterID = c.CenterID
  WHERE $whereSql
  ORDER BY m.MemberID DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$items = array_map(function ($row) {
    $hasCoBorrower = $row['CoBorrowerID'] !== null;
    return [
        'member_id'       => (int)$row['MemberID'],
        'member_name'     => trim($row['FirstName'] . ' ' . $row['LastName']),
        'center_id'       => $row['CenterID'] !== null ? (int)$row['CenterID'] : null,
        'center_name'     => $row['CenterName'],
        'coborrower_id'   => $hasCoBorrower ? (int)$row['CoBorrowerID'] : null,
        'coborrower_name' => $hasCoBorrower ? trim($row['CbFirstName'] . ' ' . ($row['CbMiddleName'] ? $row['CbMiddleName'] . ' ' : '') . $row['CbLastName']) : null,
        'resume_stage'    => $hasCoBorrower ? 'kyc_member' : 'coborrower',
        'created_at'      => to_iso_date($row['CreatedAt']),
    ];
}, $rows);

json_response(true, ['items' => $items]);
