<?php
/**
 * "Remember Me" persistent login for the web CRM (index.php) -- the PHP
 * session itself still expires when the browser closes (session.php's
 * lifetime=0 cookie is unchanged); this is a *separate*, long-lived cookie
 * that silently re-establishes a session on a later visit, following the
 * selector/validator pattern (Barry Jaspan's "Improved Persistent Login
 * Cookie Best Practice") rather than just stretching the session lifetime:
 *   - Selector: the DB lookup key, not secret, stored and sent in plaintext.
 *   - Validator: the actual secret, hashed before storage, only ever
 *     compared with hash_equals() (timing-safe).
 * Every successful auto-login rotates the token (old row deleted, new one
 * issued) so a stale copy of a stolen cookie stops working as soon as the
 * legitimate browser uses its own.
 */

const REMEMBER_ME_COOKIE = 'remember_me';
const REMEMBER_ME_DAYS = 30;

function _remember_me_cookie_options(int $expiresAt = 0): array {
    return [
        'expires'  => $expiresAt,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ];
}

/** Issues a fresh remember-me token for this staff member and sets the cookie. */
function set_remember_me_cookie(PDO $pdo, int $staffId): void {
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expiresAt = time() + REMEMBER_ME_DAYS * 86400;

    $pdo->prepare("INSERT INTO Staff_Remember_Token (StaffID, Selector, ValidatorHash, ExpiresAt) VALUES (?, ?, ?, ?)")
        ->execute([$staffId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expiresAt)]);

    setcookie(REMEMBER_ME_COOKIE, "$selector:$validator", _remember_me_cookie_options($expiresAt));
}

/** Deletes the DB row(s) backing the current cookie (and the matching ones for $staffId on logout) and clears it. */
function clear_remember_me_cookie(PDO $pdo, ?int $staffId = null): void {
    if (!empty($_COOKIE[REMEMBER_ME_COOKIE])) {
        [$selector] = array_pad(explode(':', $_COOKIE[REMEMBER_ME_COOKIE], 2), 1, null);
        if ($selector) {
            $pdo->prepare("DELETE FROM Staff_Remember_Token WHERE Selector = ?")->execute([$selector]);
        }
    }
    if ($staffId !== null) {
        $pdo->prepare("DELETE FROM Staff_Remember_Token WHERE StaffID = ?")->execute([$staffId]);
    }
    setcookie(REMEMBER_ME_COOKIE, '', _remember_me_cookie_options(time() - 3600));
}

/**
 * Called on every request before is_logged_in() is trusted -- if there's no
 * active session but a valid remember-me cookie, logs the staff member back
 * in and rotates the token. Silently no-ops (and cleans up) on anything
 * invalid/expired/tampered -- never throws, since this runs unconditionally
 * on every page load.
 */
function attempt_remember_me_login(PDO $pdo): void {
    if (isset($_SESSION['staff_id']) || empty($_COOKIE[REMEMBER_ME_COOKIE])) {
        return;
    }
    $parts = explode(':', $_COOKIE[REMEMBER_ME_COOKIE], 2);
    if (count($parts) !== 2) {
        clear_remember_me_cookie($pdo);
        return;
    }
    [$selector, $validator] = $parts;

    $stmt = $pdo->prepare("SELECT * FROM Staff_Remember_Token WHERE Selector = ?");
    $stmt->execute([$selector]);
    $token = $stmt->fetch();

    if (!$token || strtotime($token['ExpiresAt']) < time() || !hash_equals($token['ValidatorHash'], hash('sha256', $validator))) {
        clear_remember_me_cookie($pdo);
        return;
    }

    $staffStmt = $pdo->prepare("
        SELECT s.*, r.RoleName, d.DesignationName
        FROM Staff s
        LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
        LEFT JOIN Designation_Master d ON s.DesignationID = d.DesignationID
        WHERE s.StaffID = ? AND s.IsActive = 1
    ");
    $staffStmt->execute([$token['StaffID']]);
    $staff = $staffStmt->fetch();
    if (!$staff) {
        $pdo->prepare("DELETE FROM Staff_Remember_Token WHERE TokenID = ?")->execute([$token['TokenID']]);
        clear_remember_me_cookie($pdo);
        return;
    }

    $pdo->prepare("DELETE FROM Staff_Remember_Token WHERE TokenID = ?")->execute([$token['TokenID']]);
    set_remember_me_cookie($pdo, (int)$staff['StaffID']);

    $_SESSION['staff_id'] = $staff['StaffID'];
    $_SESSION['staff'] = $staff;
    $_SESSION['last_activity'] = time();
    $_SESSION['remembered'] = true;
}
