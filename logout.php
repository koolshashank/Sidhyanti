<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged_in()) {
    // SQL Server doesn't support ORDER BY directly in UPDATE TOP — use a CTE instead
    $pdo->exec("
        ;WITH LastLogin AS (
            SELECT TOP 1 LoginLogID FROM Login_Log
            WHERE StaffID = " . (int)$_SESSION['staff_id'] . " AND LogoutAt IS NULL
            ORDER BY LoginAt DESC
        )
        UPDATE Login_Log SET LogoutAt = GETDATE() WHERE LoginLogID IN (SELECT LoginLogID FROM LastLogin)
    ");
    clear_remember_me_cookie($pdo, (int)$_SESSION['staff_id']);
}
session_destroy();
header('Location: index.php');
exit;
