<?php
/**
 * POST /api/v1/auth/refresh
 * Body: { "refresh_token": "..." }
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$body = get_json_body();
$refreshToken = (string)($body['refresh_token'] ?? '');

if ($refreshToken === '') {
    json_response(false, null, 'refresh_token is required', 422);
}

$refreshTokenHash = hash('sha256', $refreshToken);

$stmt = $pdo->prepare("
    SELECT TokenID, StaffID FROM Api_Refresh_Token
    WHERE TokenHash = ? AND RevokedAt IS NULL AND ExpiresAt > GETDATE()
");
$stmt->execute([$refreshTokenHash]);
$tokenRow = $stmt->fetch();

if (!$tokenRow) {
    json_response(false, null, 'Invalid or expired refresh token', 401);
}

$stmt = $pdo->prepare("
    SELECT s.StaffID, s.FullName, s.BranchID, s.RoleID, r.RoleName
    FROM Staff s
    LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
    WHERE s.StaffID = ? AND s.IsActive = 1
");
$stmt->execute([$tokenRow['StaffID']]);
$staff = $stmt->fetch();

if (!$staff) {
    json_response(false, null, 'Account is no longer active', 401);
}

$claims = [
    'StaffID'  => (int)$staff['StaffID'],
    'RoleID'   => $staff['RoleID'] !== null ? (int)$staff['RoleID'] : null,
    'RoleName' => $staff['RoleName'],
    'BranchID' => $staff['BranchID'] !== null ? (int)$staff['BranchID'] : null,
    'FullName' => $staff['FullName'],
];

$accessToken = jwt_encode($claims, JWT_SECRET, JWT_ACCESS_TOKEN_TTL);

json_response(true, [
    'access_token' => $accessToken,
    'expires_in'   => JWT_ACCESS_TOKEN_TTL,
]);
