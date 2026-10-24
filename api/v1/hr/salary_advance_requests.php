<?php
/**
 * GET /api/v1/hr/salary_advance_requests.php
 * Returns the caller's own salary advance requests, most recent first.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();
$staffId = (int)$claims['StaffID'];

$stmt = $pdo->prepare("
  SELECT sar.RequestID, sar.RequestType, sar.Amount, sar.Reason, sar.Status,
         sar.ReviewRemarks, sar.CreatedAt, rev.FullName AS ReviewerName
  FROM Salary_Advance_Request sar
  LEFT JOIN Staff rev ON sar.ReviewedByStaffID = rev.StaffID
  WHERE sar.StaffID = ?
  ORDER BY sar.RequestID DESC
");
$stmt->execute([$staffId]);
$rows = $stmt->fetchAll();

json_response(true, [
    'items' => array_map(function ($r) {
        return [
            'request_id'     => (int)$r['RequestID'],
            'request_type'   => $r['RequestType'],
            'amount'         => (float)$r['Amount'],
            'reason'         => $r['Reason'],
            'status'         => $r['Status'],
            'remarks'        => $r['ReviewRemarks'],
            'reviewer_name'  => $r['ReviewerName'],
            'created_at'     => to_iso_date($r['CreatedAt']),
        ];
    }, $rows),
]);
