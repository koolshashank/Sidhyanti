-- SuperAdmin role (full, unrestricted access to the web CRM -- Role_Permission
-- checks and every existing "Admin only" page gate are bypassed for it) plus
-- the Billing module: generate monthly/quarterly/yearly billing records and a
-- single global services-enabled kill switch, both driven from billing.php.

IF NOT EXISTS (SELECT 1 FROM Role_Master WHERE RoleName = 'SuperAdmin')
BEGIN
    INSERT INTO Role_Master (RoleName) VALUES ('SuperAdmin');
END

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'Billing_Invoice')
BEGIN
    CREATE TABLE Billing_Invoice (
        BillingID INT IDENTITY(1,1) PRIMARY KEY,
        PeriodType VARCHAR(20) NOT NULL, -- 'Monthly' | 'Quarterly' | 'Yearly'
        PeriodStart DATE NOT NULL,
        PeriodEnd DATE NOT NULL,
        Amount DECIMAL(12,2) NOT NULL,
        Notes NVARCHAR(255) NULL,
        GeneratedByStaffID INT NULL FOREIGN KEY REFERENCES Staff(StaffID),
        CreatedAt DATETIME NOT NULL DEFAULT GETDATE()
    );
END

-- Missing row reads as "enabled" (see services_enabled() in
-- includes/app_settings_helper.php), but seed it explicitly so it's visible
-- and editable from billing.php from day one.
IF NOT EXISTS (SELECT 1 FROM App_Setting WHERE SettingKey = 'services_enabled')
BEGIN
    INSERT INTO App_Setting (SettingKey, SettingValue, UpdatedAt) VALUES ('services_enabled', '1', GETDATE());
END
