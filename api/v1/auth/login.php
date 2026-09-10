<?php
/**
 * POST /api/v1/auth/login
 * Body: { "username": "...", "password": "...", "device_info": "..." (optional) }
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../../includes/mobile_menu_helper.php';
require_once __DIR__ . '/../../../includes/app_settings_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, null, 'Method not allowed', 405);
}

// The Billing kill switch (billing.php, SuperAdmin only) blocks fresh mobile
// logins too, not just already-issued tokens -- checked before the lockout
// query below so a suspended install doesn't also burn login attempts.
if (!services_enabled($pdo)) {
    json_response(false, null, 'Service suspended. Please contact your administrator.', 403);
}

$body = get_json_body();
$username = trim((string)($body['username'] ?? ''));
$password = (string)($body['password'] ?? '');
$deviceInfo = trim((string)($body['device_info'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
$ip = get_client_ip();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($username === '' || $password === '') {
    json_response(false, null, 'Username and password are required', 422);
}

// --- Lockout: 5 failed attempts for this username within the last 15 minutes ---
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM Login_Log
    WHERE UsernameTried = ? AND Status = 'Failed' AND LoginAt >= DATEADD(MINUTE, -15, GETDATE())
");
$stmt->execute([$username]);
if ((int)$stmt->fetchColumn() >= 5) {
    json_response(false, null, 'Too many failed login attempts. Please try again after 15 minutes.', 429);
}

$stmt = $pdo->prepare("
    SELECT s.*, r.RoleName, d.DesignationName
    FROM Staff s
    LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
    LEFT JOIN Designation_Master d ON s.DesignationID = d.DesignationID
    WHERE s.Username = ? AND s.IsActive = 1
");
$stmt->execute([$username]);
$staff = $stmt->fetch();

// Mobile API authenticates against its own MobilePassword hash, kept
// separate from the web CRM's PasswordHash.
$ok = $staff && !empty($staff['MobilePassword']) && password_verify($password, $staff['MobilePassword']);

$pdo->prepare("
    INSERT INTO Login_Log (StaffID, UsernameTried, IPAddress, UserAgent, Status)
    VALUES (?, ?, ?, ?, ?)
")->execute([$staff['StaffID'] ?? null, $username, $ip, $userAgent, $ok ? 'Success' : 'Failed']);

if (!$ok) {
    json_response(false, null, 'Invalid username or password', 401);
}

$pdo->prepare("UPDATE Staff SET LastLoginAt = GETDATE() WHERE StaffID = ?")->execute([$staff['StaffID']]);

$claims = [
    'StaffID'  => (int)$staff['StaffID'],
    'RoleID'   => $staff['RoleID'] !== null ? (int)$staff['RoleID'] : null,
    'RoleName' => $staff['RoleName'],
    'BranchID' => $staff['BranchID'] !== null ? (int)$staff['BranchID'] : null,
    'FullName' => $staff['FullName'],
];

$accessToken = jwt_encode($claims, JWT_SECRET, JWT_ACCESS_TOKEN_TTL);

$refreshToken = bin2hex(random_bytes(32));
$refreshTokenHash = hash('sha256', $refreshToken);

$pdo->prepare("
    INSERT INTO Api_Refresh_Token (StaffID, TokenHash, DeviceInfo, ExpiresAt, IPAddress)
    VALUES (?, ?, ?, DATEADD(DAY, CAST(? AS INT), GETDATE()), ?)
")->execute([$staff['StaffID'], $refreshTokenHash, $deviceInfo, JWT_REFRESH_TOKEN_TTL_DAYS, $ip]);

json_response(true, [
    'access_token'  => $accessToken,
    'refresh_token' => $refreshToken,
    'expires_in'    => JWT_ACCESS_TOKEN_TTL,
    'staff' => [
        'id'          => (int)$staff['StaffID'],
        'full_name'   => $staff['FullName'],
        'role'        => $staff['RoleName'],
        'branch_id'   => $staff['BranchID'] !== null ? (int)$staff['BranchID'] : null,
        'designation' => $staff['DesignationName'],
        'menu_items'  => mobile_menu_items_for_designation($pdo, $staff['DesignationID'] !== null ? (int)$staff['DesignationID'] : null),
    ],
]);
