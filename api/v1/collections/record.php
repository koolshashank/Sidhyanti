<?php
/**
 * POST /api/v1/collections/record
 * Body: { "loan_id": int, "installment_no": int, "amount": number,
 *         "collection_date": "YYYY-MM-DD" (optional, default today),
 *         "remarks": string (optional) }
 *
 * Client never sends a raw ScheduleID -- the server resolves it from
 * (loan_id, installment_no) so a client can't forge writes against an
 * installment that doesn't belong to the loan it claims.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';
require_once __DIR__ . '/../../includes/api_error.php';
require_once __DIR__ . '/../../includes/collection_finalize.php';
require_once __DIR__ . '/../../../includes/notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$body = get_json_body();

$loanId = (int)($body['loan_id'] ?? 0);
$installmentNo = (int)($body['installment_no'] ?? 0);
$amount = (float)($body['amount'] ?? 0);
$collectionDate = trim((string)($body['collection_date'] ?? date('Y-m-d')));
$remarks = trim((string)($body['remarks'] ?? ''));

if ($loanId <= 0 || $installmentNo <= 0 || $amount <= 0) {
    json_response(false, null, 'loan_id, installment_no and a positive amount are required', 422);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $collectionDate) || $collectionDate > date('Y-m-d')) {
    json_response(false, null, 'collection_date must be a valid date not in the future', 422);
}

$loanStmt = $pdo->prepare("SELECT BranchID, LoanOfficerStaffID FROM Loan WHERE LoanID = ?");
$loanStmt->execute([$loanId]);
$loan = $loanStmt->fetch();
if (!$loan) {
    json_response(false, null, 'Loan not found', 404);
}
assert_loan_access($loan, $claims);

$scheduleId = null;
$isPaidNow = false;
$isPartPayment = false;
$partialPaymentApprovalId = null;
$pdo->beginTransaction();
try {
    $schedStmt = $pdo->prepare("SELECT ScheduleID, IsPaid, EMIAmount, PrincipalDue, InterestDue FROM Repayment_Schedule WHERE LoanID = ? AND InstallmentNo = ?");
    $schedStmt->execute([$loanId, $installmentNo]);
    $sched = $schedStmt->fetch();
    if (!$sched) {
        throw new ApiError(404, 'Installment not found for this loan');
    }
    if (to_bool($sched['IsPaid'])) {
        throw new ApiError(409, 'This installment was already marked as paid');
    }
    $scheduleId = (int)$sched['ScheduleID'];
    $isPartPayment = $amount < (float)$sched['EMIAmount'];

    if (!$isPartPayment) {
        // Collection.TotalCollected is a computed column (Principal+Interest+Penalty),
        // so we allocate the paid amount against PrincipalDue/InterestDue first and
        // anything left over (e.g. a late-fee top-up, or an overpayment) as penalty.
        [$principalCollected, $interestCollected, $penaltyCollected] =
            allocate_payment($amount, (float)$sched['PrincipalDue'], (float)$sched['InterestDue']);

        $isPaidNow = finalize_collection(
            $pdo, $loanId, $scheduleId, $amount, $collectionDate,
            $principalCollected, $interestCollected, $penaltyCollected,
            (int)$claims['StaffID'], $remarks, false
        );
    } else {
        // Held for Branch Manager approval -- Daily_Collection_Sheet/Collection
        // are left untouched until api/v1/collections/partial_payment_approve.php
        // runs the same finalize_collection() above.
        $dupCheck = $pdo->prepare("SELECT 1 FROM Partial_Payment_Approval WHERE ScheduleID = ? AND Status = 'Pending'");
        $dupCheck->execute([$scheduleId]);
        if ($dupCheck->fetchColumn()) {
            throw new ApiError(409, 'A partial payment for this installment is already pending approval');
        }
        $partialPaymentApprovalId = insert_and_get_id($pdo, "INSERT INTO Partial_Payment_Approval
            (LoanID, ScheduleID, RequestedAmount, CollectionDate, Remarks, RequestedByStaffID)
            VALUES (?,?,?,?,?,?)", [
            $loanId, $scheduleId, $amount, $collectionDate,
            $remarks !== '' ? $remarks : null, (int)$claims['StaffID'],
        ], 'PartialPaymentApprovalID');

        $bmStmt = $pdo->prepare("
          SELECT s.StaffID FROM Staff s JOIN Role_Master r ON s.RoleID = r.RoleID
          WHERE r.RoleName = 'Branch Manager' AND s.BranchID = ? AND s.IsActive = 1
        ");
        $bmStmt->execute([$loan['BranchID']]);
        foreach (array_column($bmStmt->fetchAll(), 'StaffID') as $sid) {
            notify_staff($pdo, $sid, 'Partial Payment Awaiting Approval', "A partial payment of $amount was submitted for loan ID $loanId.", 1, 'Loan', $loanId);
        }
    }

    $pdo->commit();
} catch (ApiError $e) {
    $pdo->rollBack();
    json_response(false, null, $e->getMessage(), $e->httpCode);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('collections/record.php failed: ' . $e->getMessage());
    json_response(false, null, 'Failed to record payment', 500);
}

json_response(true, [
    'loan_id'                     => $loanId,
    'schedule_id'                 => $scheduleId,
    'installment_no'              => $installmentNo,
    'collected_amount'            => $amount,
    'collection_date'             => $collectionDate,
    'partial_payment_approval_id' => $partialPaymentApprovalId,
    'status'                      => $isPartPayment ? 'PendingApproval' : ($isPaidNow ? 'Paid' : 'PartiallyPaid'),
], $isPartPayment ? 'Partial payment submitted for Branch Manager approval' : 'Payment recorded');
