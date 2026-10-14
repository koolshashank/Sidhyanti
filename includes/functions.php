<?php
// httponly blocks JS from reading the session cookie (XSS-stolen-cookie
// mitigation); samesite=Lax blocks it being sent on cross-site requests
// (CSRF mitigation). secure is auto-detected rather than hardcoded on --
// the site isn't served over HTTPS yet, and forcing it on would silently
// break every login (browsers refuse to send a Secure cookie over plain
// HTTP). It'll start applying itself automatically once HTTPS is added.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/remember_me_helper.php';
attempt_remember_me_login($pdo);

/* ---------------- AUTH ---------------- */
// A session with no activity for this long is treated as expired and
// signed out -- doesn't apply to a "Remember Me" login, which re-logs the
// staff member in silently via its own cookie instead (see
// remember_me_helper.php) rather than being timed out mid-browse.
const SESSION_IDLE_TIMEOUT_SECONDS = 1800;

function is_logged_in() {
    return isset($_SESSION['staff_id']);
}

function require_login() {
    global $pdo;
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
    if (empty($_SESSION['remembered'])
        && isset($_SESSION['last_activity'])
        && (time() - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT_SECONDS) {
        session_destroy();
        header('Location: index.php?expired=1&reason=idle');
        exit;
    }
    $_SESSION['last_activity'] = time();

    if (empty($_SESSION['staff']) || !is_array($_SESSION['staff'])) {
        $stmt = $pdo->prepare("SELECT s.*, r.RoleName, d.DesignationName FROM Staff s
                                LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
                                LEFT JOIN Designation_Master d ON s.DesignationID = d.DesignationID
                                WHERE s.StaffID = ? AND s.IsActive = 1");
        $stmt->execute([$_SESSION['staff_id']]);
        $staff = $stmt->fetch();
        if (!$staff) {
            session_destroy();
            header('Location: index.php?expired=1');
            exit;
        }
        $_SESSION['staff'] = $staff;
    }
    // Tell SQL Server who is making changes this request, so the
    // Audit_Log / Lead_Stage_History / Loan_Stage_History triggers can
    // record ChangedByStaffID automatically.
    $pdo->exec("EXEC sp_set_session_context @key = N'StaffID', @value = " . (int)$_SESSION['staff_id']);

    enforce_service_active();
}

/**
 * The Billing kill switch (billing.php, SuperAdmin only): when disabled,
 * every page redirects to a suspended notice for everyone except SuperAdmin,
 * who still needs full access to go re-enable it.
 */
function enforce_service_active() {
    global $pdo;
    $staff = current_staff();
    if (($staff['RoleName'] ?? '') === 'SuperAdmin') {
        return;
    }
    require_once __DIR__ . '/app_settings_helper.php';
    if (!services_enabled($pdo)) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script !== 'service_suspended.php') {
            header('Location: service_suspended.php');
            exit;
        }
    }
}

function current_staff() {
    return $_SESSION['staff'] ?? null;
}

/**
 * Moves $dueDate to the same calendar week's $newDow (1=Monday..7=Sunday),
 * e.g. a Monday due date shifts to that week's Friday when $newDow=5. Keeps
 * the "which week" the same and only realigns the day-of-week, which is the
 * predictable behaviour when a center's weekly meeting day moves -- except
 * when that would land the (previously not-yet-due) installment in the
 * past, e.g. shifting Friday->Monday for a date due later this week; in
 * that case it rolls forward to the following week's occurrence instead.
 * Used by both centers.php (Shift Meeting) and member_view.php (Transfer Center).
 */
function shift_due_date_to_weekday(string $dueDate, int $newDow): string {
    $d = new DateTime($dueDate);
    $oldDow = (int)$d->format('N');
    $weekStart = (clone $d)->modify('-' . ($oldDow - 1) . ' days');
    $newDue = $weekStart->modify('+' . ($newDow - 1) . ' days');
    if ($newDue->format('Y-m-d') < date('Y-m-d')) {
        $newDue->modify('+7 days');
    }
    return $newDue->format('Y-m-d');
}

/* ---------------- FORMAT HELPERS ---------------- */
function money($amount) {
    return '₹' . number_format((float)$amount, 2);
}

function fdate($date) {
    if (!$date) return '-';
    return date('d M Y', strtotime($date));
}

function stage_color($stage) {
    $map = [
        'New Lead'     => 'secondary', 'Contacted'    => 'info', 'KYC Pending'  => 'warning',
        'Verified'     => 'primary',   'Converted'    => 'success', 'Dropped'   => 'danger',
        'Applied'      => 'secondary', 'GRT Pending'  => 'warning', 'Approved'  => 'info',
        'Disbursed'    => 'primary',   'Active'       => 'success', 'Closed'    => 'dark',
        'Rejected'     => 'danger',    'Preclosed'    => 'dark',
        'Open'         => 'secondary', 'In Progress'  => 'info',   'Completed' => 'success',
        'Overdue'      => 'danger',    'Pending'      => 'secondary', 'Paid'   => 'success',
        'Partial'      => 'warning',
    ];
    return $map[$stage] ?? 'secondary';
}

function stage_badge($stage) {
    $color = stage_color($stage);
    return "<span class=\"badge badge-{$color}\">" . htmlspecialchars($stage) . "</span>";
}

function priority_badge($p) {
    $map = ['Low' => 'success', 'Medium' => 'warning', 'High' => 'danger'];
    $color = $map[$p] ?? 'secondary';
    return "<span class=\"badge badge-{$color}\">" . htmlspecialchars($p) . "</span>";
}

function initials($name) {
    $parts = explode(' ', trim((string)$name));
    $ini = '';
    foreach (array_slice($parts, 0, 2) as $p) { $ini .= strtoupper(substr($p, 0, 1)); }
    return $ini ?: '??';
}

/**
 * Look up a lookup-table ID by its name, e.g. stage_id($pdo, 'Loan_Stage_Master', 'StageID', 'StageName', 'Active')
 */
function lookup_id($pdo, $table, $idCol, $nameCol, $name) {
    $stmt = $pdo->prepare("SELECT $idCol FROM $table WHERE $nameCol = ?");
    $stmt->execute([$name]);
    return $stmt->fetchColumn();
}

/**
 * Percent change of $current vs $previous, for dashboard delta badges.
 * Returns null when there's no meaningful baseline to compare against
 * (so the caller can skip the badge instead of showing a fabricated 0%/∞).
 */
function pct_delta($current, $previous) {
    $previous = (float)$previous;
    $current = (float)$current;
    if ($previous == 0.0) {
        return $current == 0.0 ? 0.0 : null;
    }
    return (($current - $previous) / $previous) * 100;
}
