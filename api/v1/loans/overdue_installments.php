<?php
/**
 * GET /api/v1/loans/overdue_installments.php?loan_id={loan_id}
 * Every unpaid, past-due installment for one loan, oldest first -- used by
 * the mobile "Collect Arrear" sheet to cascade a single entered amount
 * across installments in order (same per-installment collections/record.php
 * call under the hood, just looped), so a part-payment that doesn't cover
 * the next installment still correctly lands in Partial_Payment_Approval
 * instead of silently overpaying one row.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$loanId = (int)($_GET['loan_id'] ?? 0);
if ($loanId <= 0) {
    json_response(false, null, 'loan_id is required', 422);
}

$loanStmt = $pdo->prepare("SELECT BranchID, LoanOfficerStaffID FROM Loan WHERE LoanID = ?");
$loanStmt->execute([$loanId]);
$loan = $loanStmt->fetch();
if (!$loan) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($loan, $claims);

$stmt = $pdo->prepare("
  SELECT rs.ScheduleID, rs.InstallmentNo, rs.DueDate, rs.EMIAmount
  FROM Repayment_Schedule rs
  WHERE rs.LoanID = ? AND rs.IsPaid = 0 AND rs.DueDate < CAST(GETDATE() AS DATE)
  ORDER BY rs.InstallmentNo
");
$stmt->execute([$loanId]);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'schedule_id'    => (int)$r['ScheduleID'],
            'installment_no' => (int)$r['InstallmentNo'],
            'due_date'       => to_iso_date($r['DueDate']),
            'emi_amount'     => to_money($r['EMIAmount']),
        ];
    }, $rows),
]);
