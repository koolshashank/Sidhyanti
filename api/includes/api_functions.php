<?php
/**
 * Shared helpers for the /api/v1 REST API: DB connection reuse, JSON
 * response envelope, request body parsing, and the Bearer-token auth
 * middleware used by every protected endpoint.
 */

require_once __DIR__ . '/../../config/db.php';   // gives us $pdo (same connection as the web CRM)
require_once __DIR__ . '/../config/jwt_config.php';
require_once __DIR__ . '/jwt_helper.php';

// Allow the Flutter app (web build runs on its own dev-server origin, native
// builds send no Origin at all) to call this API cross-origin.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Send a JSON response in the standard { success, data|message } envelope
 * and terminate the request.
 */
function json_response(bool $success, $data = null, ?string $message = null, int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    if ($success) {
        $body = ['success' => true];
        if ($data !== null) { $body['data'] = $data; }
        if ($message !== null) { $body['message'] = $message; }
        echo json_encode($body);
    } else {
        echo json_encode(['success' => false, 'message' => $message ?? 'Error']);
    }
    exit;
}

/** Decode the JSON request body into an associative array (empty array if absent/invalid). */
function get_json_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
}

function get_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/** Extract the raw token string from an `Authorization: Bearer <token>` header, if present. */
function get_bearer_token(): ?string {
    $authHeader = null;

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $authHeader = $value;
                break;
            }
        }
    }
    if (!$authHeader) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    }
    if ($authHeader && preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Auth middleware for protected endpoints. Verifies the Bearer access token,
 * primes sp_set_session_context (so Audit_Log / *_Stage_History triggers keep
 * attributing changes to the right StaffID, exactly like the web CRM session
 * does), and returns the decoded claims: StaffID, RoleName, BranchID, FullName.
 *
 * On any failure it responds 401 Unauthorized and exits — callers don't need
 * to check a return value.
 */
function require_api_auth(): array {
    global $pdo;

    $token = get_bearer_token();
    if (!$token) {
        json_response(false, null, 'Unauthorized', 401);
    }

    $payload = jwt_decode($token, JWT_SECRET);
    if (!$payload || empty($payload['StaffID'])) {
        json_response(false, null, 'Unauthorized', 401);
    }

    $pdo->exec("EXEC sp_set_session_context @key = N'StaffID', @value = " . (int)$payload['StaffID']);

    return $payload;
}

/**
 * Runs an INSERT against a table that has AFTER triggers and returns the new
 * row's identity value reliably.
 *
 * SQL Server refuses a plain `OUTPUT INSERTED.col` (no INTO) on any table
 * with an enabled trigger, and PDO's lastInsertId()/@@IDENTITY is unsafe here
 * too -- it returns the last identity inserted *anywhere in the session*,
 * which on these tables is the trigger's own row (e.g. Lead_Stage_History),
 * not the table you just inserted into. Routing the OUTPUT into a table
 * variable sidesteps both problems.
 *
 * $insertSql must NOT include the OUTPUT/SELECT wrapper -- just the plain
 * `INSERT INTO Table (...) VALUES (...)` -- this wraps it automatically.
 */
function insert_and_get_id(PDO $pdo, string $insertSql, array $params, string $idColumn): int {
    $sql = "DECLARE @__ids TABLE (id INT);\n"
         . preg_replace('/\bVALUES\b/i', "OUTPUT INSERTED.$idColumn INTO @__ids\nVALUES", $insertSql, 1)
         . ";\nSELECT id FROM @__ids;";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($stmt->columnCount() === 0 && $stmt->nextRowset()) {
        // skip the empty result set the INSERT itself produces
    }
    return (int)$stmt->fetchColumn();
}
