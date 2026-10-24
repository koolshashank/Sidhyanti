<?php
/**
 * POST /api/v1/hr/salary_advance_request_create.php
 * Body: { request_type: 'Personal'|'Emergency', amount, reason? }
 *
 * Business rules (per product decision, not configurable):
 *  - Personal: amount must be one of 1000/2000/3000/4000/5000, and can only
 *    be requested after the 15th of the current month.
 *  - Emergency: amount must be one of 6000/7000/8000/9000/10000, any time.
 *  - At most one Personal and one Emergency request per calendar month that
 *    is still Pending or already Approved (a Rejected one doesn't count).
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

const PERSONAL_AMOUNTS = [1000.0, 2000.0, 3000.0, 4000.0, 5000.0];
const EMERGENCY_AMOUNTS = [6000.0, 7000.0, 8000.0, 9000.0, 10000.0];

$claims = require_api_auth();
$staffId = (int)$claims['StaffID'];
$body = get_json_body();

$requestType = trim((string)($body['request_type'] ?? ''));
$amount = (float)($body['amount'] ?? 0);
$reason = trim((string)($body['reason'] ?? '')) ?: null;

if (!in_array($requestType, ['Personal', 'Emergency'], true)) {
    json_response(false, null, 'request_type must be Personal or Emergency', 422);
}

$allowedAmounts = $requestType === 'Personal' ? PERSONAL_AMOUNTS : EMERGENCY_AMOUNTS;
if (!in_array($amount, $allowedAmounts, true)) {
    json_response(false, null, 'Invalid amount for ' . $requestType . ' advance', 422);
}

if ($requestType === 'Personal' && (int)date('j') <= 15) {
    json_response(false, null, 'Personal advance can only be requested after the 15th of the month', 422);
}

$dupStmt = $pdo->prepare("
  SELECT COUNT(*) FROM Salary_Advance_Request
  WHERE StaffID = ? AND RequestType = ? AND Status IN ('Pending', 'Approved')
    AND YEAR(CreatedAt) = YEAR(GETDATE()) AND MONTH(CreatedAt) = MONTH(GETDATE())
");
$dupStmt->execute([$staffId, $requestType]);
if ((int)$dupStmt->fetchColumn() > 0) {
    json_response(false, null, 'You already have a ' . $requestType . ' advance request this month', 409);
}

$newId = insert_and_get_id($pdo, "INSERT INTO Salary_Advance_Request (StaffID, RequestType, Amount, Reason, Status)
    VALUES (?,?,?,?, 'Pending')", [
    $staffId,
    $requestType,
    $amount,
    $reason,
], 'RequestID');

json_response(true, ['request_id' => $newId], 'Salary advance request submitted');
