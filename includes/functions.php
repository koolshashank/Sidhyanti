<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* ---------------- AUTH ---------------- */
function is_logged_in() {
    return isset($_SESSION['staff_id']);
}

function require_login() {
    global $pdo;
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
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
