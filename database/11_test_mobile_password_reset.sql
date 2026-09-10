-- ============================================================
-- 11_test_mobile_password_reset.sql
-- TESTING CONVENIENCE ONLY: sets every staff member's mobile login
-- password (Staff.MobilePassword, checked by api/v1/auth/login.php via
-- PHP's password_verify()) to the same value: Test@12345
--
-- The hash below is bcrypt ($2y$10$...), generated locally with:
--   php -r "echo password_hash('Test@12345', PASSWORD_DEFAULT);"
-- One hash is reused for every row -- password_verify() doesn't need a
-- unique hash per row, it just needs *a* valid bcrypt hash of that password.
--
-- SECURITY NOTE: after running this, EVERY staff account (including any
-- Admin/Branch Manager) can log into the mobile app with this same
-- password. Fine for a dev/staging test pass; re-run with random
-- per-staff passwords (or ask each staff member to reset) before this
-- reaches anyone outside your test group.
-- ============================================================

UPDATE dbo.Staff
SET MobilePassword = '$2y$10$.57vOrb4WAAN8j6F.iFIn.24fCLwZ0rqBuDltMLT1ipmjhm2CQRqW'
WHERE IsActive = 1;
GO
