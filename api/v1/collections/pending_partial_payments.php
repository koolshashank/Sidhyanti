<?php
/**
 * GET /api/v1/collections/pending_partial_payments.php
 * Reviewer-facing queue, branch-scoped (Admin sees every branch).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../../includes/permission_helper.php';
require_once __DIR__ . '/../../../includes/loan_arrear_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

if (!role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_partial_payment')) {
    json_response(false, null, 'You do not have permission to review partial payments', 403);
}

[$whereScope, $scopeParams] = loan_scope_where($claims, 'l');
$where = $whereScope !== '' ? "AND $whereScope" : '';

$stmt = $pdo->prepare("
  SELECT ppa.PartialPaymentApprovalID, ppa.LoanID, ppa.ScheduleID, ppa.RequestedAmount, ppa.CollectionDate,
         ppa.Remarks, ppa.CreatedAt, rs.EMIAmount, rs.InstallmentNo,
         l.LoanNo, m.FirstName, m.LastName, req.FullName AS RequestedByName
  FROM Partial_Payment_Approval ppa
  JOIN Loan l ON ppa.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  JOIN Repayment_Schedule rs ON ppa.ScheduleID = rs.ScheduleID
  LEFT JOIN Staff req ON ppa.RequestedByStaffID = req.StaffID
  WHERE ppa.Status = 'Pending' $where
  ORDER BY ppa.CreatedAt ASC
");
$stmt->execute($scopeParams);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) use ($pdo) {
        return [
            'partial_payment_approval_id' => (int)$r['PartialPaymentApprovalID'],
            'loan_id'          => (int)$r['LoanID'],
            'loan_no'          => $r['LoanNo'],
            'member_name'      => trim($r['FirstName'] . ' ' . $r['LastName']),
            'installment_no'   => (int)$r['InstallmentNo'],
            'emi_amount'       => to_money($r['EMIAmount']),
            'requested_amount' => to_money($r['RequestedAmount']),
            'collection_date'  => to_iso_date($r['CollectionDate']),
            'remarks'          => $r['Remarks'],
            'requested_by_name' => $r['RequestedByName'],
            'created_at'       => to_iso_date($r['CreatedAt']),
            'arrear_summary'   => loan_arrear_summary($pdo, (int)$r['LoanID']),
        ];
    }, $rows),
]);
