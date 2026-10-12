-- ============================================================
-- 26_device_lock.sql
-- Device-binding security: for roles with DeviceLockEnabled, a staff
-- member's mobile app gets bound to the Android ID of the first phone
-- they log into. A later login attempt from a different device is
-- rejected until an Admin resets it (staff_devices.php). Mirrors the
-- existing Role_Permission-style per-role toggle pattern.
-- Defaults to ON for Field Officer and Branch Manager only -- Admin can
-- change this per role from device_lock_settings.php.
-- Run this once in SSMS against the SidhyantiCRM database.
-- ============================================================

IF COL_LENGTH('dbo.Role_Master', 'DeviceLockEnabled') IS NULL
    ALTER TABLE dbo.Role_Master ADD DeviceLockEnabled BIT NOT NULL CONSTRAINT DF_RoleMaster_DeviceLockEnabled DEFAULT (0);
GO

IF COL_LENGTH('dbo.Staff', 'DeviceID') IS NULL
    ALTER TABLE dbo.Staff ADD DeviceID NVARCHAR(200) NULL;
GO

IF COL_LENGTH('dbo.Staff', 'DeviceRegisteredAt') IS NULL
    ALTER TABLE dbo.Staff ADD DeviceRegisteredAt DATETIME NULL;
GO

UPDATE Role_Master SET DeviceLockEnabled = 1 WHERE RoleName IN ('Branch Manager', 'Field Officer');
GO
