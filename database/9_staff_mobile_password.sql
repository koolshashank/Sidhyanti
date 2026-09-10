-- ============================================================
-- 9_staff_mobile_password.sql
-- Adds a dedicated MobilePassword column to Staff, so the mobile
-- REST API (/api/v1/auth/login) authenticates against its own
-- password hash, independent of the web CRM's PasswordHash.
-- Run this once in SSMS against the SidhyantiCRM database.
-- ============================================================

IF COL_LENGTH('dbo.Staff', 'MobilePassword') IS NULL
    ALTER TABLE dbo.Staff ADD MobilePassword NVARCHAR(255) NULL;
GO
