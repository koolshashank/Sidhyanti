<?php
/**
 * GET /api/v1/loans/reloan_eligible.php
 * Branch/officer-scoped worklist of members currently eligible for a
 * reloan -- same "no open loan" gate members/detail.php uses for
 * can_add_loan, further filtered to only those compute_reloan_eligibility()
 * actually matches a Reloan_Eligibility_Rule for (reuses that helper
 * directly so this list can never drift from what loans/create.php and
 * members/detail.php would compute for the same member individually).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../includes/reloan_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

[$scopeSql, $scopeParams] = member_scope_where($claims, 'm');
$where = $scopeSql !== '' ? "AND $scopeSql" : '';

$stmt = $pdo->prepare("
  SELECT m.MemberID, m.FirstName, m.LastName, m.MobileNo, m.EnrollmentNo
  FROM Member m
  WHERE m.IsActive = 1 $where
    AND EXISTS (
      SELECT 1 FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      WHERE l.MemberID = m.MemberID AND stg.StageName IN ('Closed', 'Preclosed')
    )
    AND NOT EXISTS (
      SELECT 1 FROM Loan l2 JOIN Loan_Stage_Master stg2 ON l2.CurrentStageID = stg2.StageID
      WHERE l2.MemberID = m.MemberID AND stg2.IsFinal = 0
    )
  ORDER BY m.FirstName, m.LastName
");
$stmt->execute($scopeParams);
$candidates = $stmt->fetchAll();

$items = [];
foreach ($candidates as $c) {
    $reloan = compute_reloan_eligibility($pdo, (int)$c['MemberID']);
    if ($reloan === null) {
        continue;
    }
    $items[] = [
        'member_id'            => (int)$c['MemberID'],
        'full_name'            => trim($c['FirstName'] . ' ' . $c['LastName']),
        'mobile_no'            => $c['MobileNo'],
        'enrollment_no'        => $c['EnrollmentNo'],
        'previous_loan_no'     => $reloan['previous_loan_no'],
        'previous_loan_amount' => to_money($reloan['previous_loan_amount']),
        'cycle_number'         => $reloan['cycle_number'],
        'late_payments'        => $reloan['late_payments'],
        'rule_name'            => $reloan['rule_name'],
        'increase_percent'     => (float)$reloan['increase_percent'],
        'eligible_amount'      => to_money($reloan['eligible_amount']),
    ];
}

json_response(true, ['items' => $items]);
