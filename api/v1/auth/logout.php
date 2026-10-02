<?php
/**
 * POST /api/v1/auth/logout
 * Header: Authorization: Bearer <access_token>
 * Body:   { "refresh_token": "..." } (optional — revokes that specific refresh token)
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth(false);
$staffId = (int)$claims['StaffID'];

$body = get_json_body();
$refreshToken = (string)($body['refresh_token'] ?? '');

if ($refreshToken !== '') {
    $refreshTokenHash = hash('sha256', $refreshToken);
    $pdo->prepare("
        UPDATE Api_Refresh_Token SET RevokedAt = GETDATE()
        WHERE TokenHash = ? AND StaffID = ? AND RevokedAt IS NULL
    ")->execute([$refreshTokenHash, $staffId]);
}

// SQL Server doesn't support ORDER BY directly in UPDATE TOP — use a CTE instead
$pdo->prepare("
    ;WITH LastLogin AS (
        SELECT TOP 1 LoginLogID FROM Login_Log
        WHERE StaffID = ? AND LogoutAt IS NULL
        ORDER BY LoginAt DESC
    )
    UPDATE Login_Log SET LogoutAt = GETDATE() WHERE LoginLogID IN (SELECT LoginLogID FROM LastLogin)
")->execute([$staffId]);

json_response(true, null, 'Logged out');
