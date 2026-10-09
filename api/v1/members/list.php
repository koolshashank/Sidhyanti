<?php
/**
 * GET /api/v1/members/list
 * Query: page, per_page (max 50), q, stage_id, center_id, has_disbursed_loan
 *
 * has_disbursed_loan=1 narrows to members who have ever had a loan reach
 * Disbursed or further (Disbursed/Active/Closed/Preclosed) -- "Existing
 * Client Journey" in the mobile app's Client Sourcing hub uses this to list
 * a center's existing clients (as opposed to brand-new leads).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/scope_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
$q = trim((string)($_GET['q'] ?? ''));
$stageId = (int)($_GET['stage_id'] ?? 0);
$centerId = (int)($_GET['center_id'] ?? 0);
$hasDisbursedLoan = (string)($_GET['has_disbursed_loan'] ?? '') === '1';

[$scopeSql, $scopeParams] = member_scope_where($claims, 'm');

$where = [];
$params = [];
if ($scopeSql !== '') { $where[] = $scopeSql; array_push($params, ...$scopeParams); }
if ($stageId > 0) { $where[] = 'm.CurrentStageID = ?'; $params[] = $stageId; }
if ($centerId > 0) { $where[] = 'm.CenterID = ?'; $params[] = $centerId; }
if ($q !== '') {
    $where[] = '(m.FirstName LIKE ? OR m.LastName LIKE ? OR m.MobileNo LIKE ? OR m.EnrollmentNo LIKE ?)';
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if ($hasDisbursedLoan) {
    $where[] = "EXISTS (
        SELECT 1 FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
        WHERE l.MemberID = m.MemberID AND stg.StageName IN ('Disbursed', 'Active', 'Closed', 'Preclosed')
    )";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Member m $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
  SELECT m.MemberID, m.EnrollmentNo, m.FirstName, m.LastName, m.MobileNo,
         b.BranchName, c.CenterName, ls.StageName AS Stage, st.FullName AS AssignedOfficer,
         (SELECT TOP 1 k.DocFilePath FROM Member_KYC_Document k
          JOIN KYC_Document_Type_Master dt ON k.DocTypeID = dt.DocTypeID
          WHERE k.MemberID = m.MemberID AND dt.DocTypeName = 'Client Photograph'
          ORDER BY k.UploadedAt DESC) AS PhotoUrl
  FROM Member m
  LEFT JOIN Branch b ON m.BranchID = b.BranchID
  LEFT JOIN Center c ON m.CenterID = c.CenterID
  LEFT JOIN Lead_Stage_Master ls ON m.CurrentStageID = ls.StageID
  LEFT JOIN Staff st ON m.AssignedToStaffID = st.StaffID
  $whereSql
  ORDER BY m.MemberID DESC
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
";
$stmt = $pdo->prepare($sql);
$i = 1;
foreach ($params as $p) { $stmt->bindValue($i++, $p); }
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$items = array_map(function ($m) {
    return [
        'member_id'        => (int)$m['MemberID'],
        'enrollment_no'    => $m['EnrollmentNo'],
        'full_name'        => trim($m['FirstName'] . ' ' . $m['LastName']),
        'mobile_no'        => $m['MobileNo'],
        'branch_name'      => $m['BranchName'],
        'center_name'      => $m['CenterName'],
        'stage'            => $m['Stage'],
        'assigned_officer' => $m['AssignedOfficer'],
        'photo_url'        => $m['PhotoUrl'],
    ];
}, $rows);

json_response(true, [
    'items'       => $items,
    'page'        => $page,
    'per_page'    => $perPage,
    'total'       => $total,
    'total_pages' => $totalPages,
]);
