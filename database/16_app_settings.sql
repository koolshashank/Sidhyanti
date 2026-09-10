-- ============================================================
-- 16_app_settings.sql
-- General-purpose key/value app settings, admin-editable via
-- app_settings.php. Anything live-toggleable from the web MUST live in
-- the database, never in a git-tracked file -- api/app_version_config.json
-- gets overwritten by every auto-deploy pull, so it can only hold
-- release metadata I control, not admin-facing settings.
-- ============================================================

IF OBJECT_ID('dbo.App_Setting', 'U') IS NULL
CREATE TABLE dbo.App_Setting (
    SettingKey       NVARCHAR(50) NOT NULL PRIMARY KEY,
    SettingValue     NVARCHAR(255) NOT NULL,
    UpdatedAt        DATETIME NOT NULL CONSTRAINT DF_AppSetting_UpdatedAt DEFAULT (GETDATE()),
    UpdatedByStaffID INT NULL
);
GO

IF NOT EXISTS (SELECT 1 FROM App_Setting WHERE SettingKey = 'show_festival_banner')
INSERT INTO App_Setting (SettingKey, SettingValue) VALUES ('show_festival_banner', '1');
GO
