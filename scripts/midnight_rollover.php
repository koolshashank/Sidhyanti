<?php
/**
 * Run once a day at 00:00 by the "SidhyantiCRM-MidnightRollover" Windows
 * Scheduled Task (see scripts/midnight_rollover.bat). Closes out anyone who
 * forgot to check out / log out the previous day, so a missed tap doesn't
 * leave their attendance or session open forever.
 */

require_once __DIR__ . '/../config/db.php';

$pdo->exec("
    UPDATE Attendance
    SET CheckOutAt = GETDATE()
    WHERE AttendanceDate = CAST(DATEADD(DAY, -1, GETDATE()) AS DATE)
      AND CheckInAt IS NOT NULL
      AND CheckOutAt IS NULL
");

$pdo->exec("
    UPDATE Login_Log
    SET LogoutAt = GETDATE()
    WHERE LogoutAt IS NULL
      AND LoginAt < CAST(GETDATE() AS DATE)
");

echo "Midnight rollover done at " . date('Y-m-d H:i:s') . "\n";
