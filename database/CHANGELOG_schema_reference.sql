-- ============================================================================
-- SIDHYANTI CRM v2 -- DATABASE SCHEMA CHANGELOG (reference copy)
-- ============================================================================
-- This file is a READ-ONLY REFERENCE consolidating every table and column
-- added to the SidhyantiCRM database across migrations 8_api_refresh_token.sql
-- through 21_two_stage_loan_approval.sql (see the database/ folder for each
-- original file, with fuller context comments).
--
-- It is NOT a new migration to run as part of the normal deploy flow -- those
-- numbered files already applied everything below to the live database. This
-- copy exists purely so you have one place to look up "what did this session
-- add to the schema" without opening 14 separate files.
--
-- That said, every statement below is written the same idempotent way as the
-- originals (IF NOT EXISTS / COL_LENGTH(...) IS NULL guards), so it is SAFE
-- to run again -- e.g. against a fresh copy of the database when setting up
-- a new environment -- it will just skip anything that already exists.
-- ============================================================================


-- ============================================================================
-- QUICK INDEX
-- ============================================================================
-- 08  Api_Refresh_Token         (new table)   -- mobile login refresh tokens
-- 09  Staff.MobilePassword      (new column)  -- separate mobile login password
-- 10  Center (4 columns)                      -- center-approval workflow
--     Loan (3 columns)                        -- disbursement fields
--     Loan_Approval             (new table)   -- proposal/preclose sign-off
--     Partial_Payment_Approval  (new table)   -- short-EMI-payment sign-off
--     Leave_Request.ReviewedAt  (new column)
-- 12  Designation_Mobile_Menu   (new table)   -- per-designation mobile menu access
-- 13  SOS_Alert                 (new table)   -- SOS button press log
-- 16  App_Setting               (new table)   -- generic admin-editable key/value settings
-- 17  Role_Master: 'SuperAdmin' (new row)     -- unrestricted web-CRM role
--     Billing_Invoice           (new table)   -- monthly/quarterly/yearly billing records
-- 18  Loan.DisbursementPhotoPath (new column) -- required customer photo before disbursal
-- 19  Role_Master: 'Area Manager' (new row)   -- branch-wide (not officer-scoped) loan-approval role
-- 20  Staff_Branch_Map          (new table)   -- lets one Area Manager cover multiple branches
-- 21  Loan_Approval (3 columns)               -- two-stage BM -> AM approval above the BM limit
--
-- (Migrations 11, 14, 15 are seed-data only -- no schema change -- and are
--  omitted here; see those files directly if you need that context.)
-- ============================================================================


-- ============================================================================
-- 08 -- Api_Refresh_Token (database/8_api_refresh_token.sql)
-- Stores hashed refresh tokens issued by the mobile REST API.
-- ============================================================================
IF OBJECT_ID('dbo.Api_Refresh_Token', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Api_Refresh_Token (
        TokenID      INT IDENTITY(1,1) PRIMARY KEY,
        StaffID      INT           NOT NULL,
        TokenHash    VARCHAR(64)   NOT NULL,   -- SHA-256 hex digest of the opaque refresh token (raw token is never stored)
        DeviceInfo   NVARCHAR(255) NULL,       -- e.g. device model / app version sent by the mobile client
        IssuedAt     DATETIME      NOT NULL CONSTRAINT DF_ApiRefreshToken_IssuedAt DEFAULT (GETDATE()),
        ExpiresAt    DATETIME      NOT NULL,
        RevokedAt    DATETIME      NULL,
        IPAddress    VARCHAR(45)   NULL,       -- big enough for IPv6
        CONSTRAINT FK_ApiRefreshToken_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID)
    );
    CREATE INDEX IX_ApiRefreshToken_TokenHash ON dbo.Api_Refresh_Token(TokenHash);
    CREATE INDEX IX_ApiRefreshToken_StaffID   ON dbo.Api_Refresh_Token(StaffID);
END
GO


-- ============================================================================
-- 09 -- Staff.MobilePassword (database/9_staff_mobile_password.sql)
-- The mobile REST API authenticates against its own password hash,
-- independent of the web CRM's PasswordHash.
-- ============================================================================
IF COL_LENGTH('dbo.Staff', 'MobilePassword') IS NULL
    ALTER TABLE dbo.Staff ADD MobilePassword NVARCHAR(255) NULL;
GO


-- ============================================================================
-- 10 -- BM Operations: Center Approval, Disbursement, Loan/Preclose sign-off,
--       Partial Payment sign-off (database/10_bm_approvals.sql)
-- ============================================================================

-- Center approval workflow columns
IF COL_LENGTH('dbo.Center', 'ApprovalStatus') IS NULL
    ALTER TABLE dbo.Center ADD ApprovalStatus NVARCHAR(20) NOT NULL CONSTRAINT DF_Center_ApprovalStatus DEFAULT ('Pending');
GO
IF COL_LENGTH('dbo.Center', 'RemarksByApprover') IS NULL
    ALTER TABLE dbo.Center ADD RemarksByApprover NVARCHAR(500) NULL;
GO
IF COL_LENGTH('dbo.Center', 'ReviewedByStaffID') IS NULL
    ALTER TABLE dbo.Center ADD ReviewedByStaffID INT NULL;
GO
IF COL_LENGTH('dbo.Center', 'ReviewedAt') IS NULL
    ALTER TABLE dbo.Center ADD ReviewedAt DATETIME NULL;
GO

-- Disbursement columns
IF COL_LENGTH('dbo.Loan', 'DisbursedAmount') IS NULL
    ALTER TABLE dbo.Loan ADD DisbursedAmount DECIMAL(12,2) NULL;
GO
IF COL_LENGTH('dbo.Loan', 'DisbursedByStaffID') IS NULL
    ALTER TABLE dbo.Loan ADD DisbursedByStaffID INT NULL;
GO
IF COL_LENGTH('dbo.Loan', 'DisbursementRefNo') IS NULL
    ALTER TABLE dbo.Loan ADD DisbursementRefNo NVARCHAR(100) NULL;
GO

-- Loan_Approval -- BM sign-off on a loan proposal, or on a preclose request
-- (RequestType discriminates). Extended again in migration 21 (see below).
IF OBJECT_ID('dbo.Loan_Approval', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Loan_Approval (
        LoanApprovalID     INT IDENTITY(1,1) PRIMARY KEY,
        LoanID             INT NOT NULL,
        RequestType        NVARCHAR(20) NOT NULL, -- 'Proposal' | 'Preclose'
        Status             NVARCHAR(20) NOT NULL CONSTRAINT DF_LoanApproval_Status DEFAULT ('Pending'),
        RequestedByStaffID INT NOT NULL,
        RequestRemarks     NVARCHAR(500) NULL,
        RemarksByApprover  NVARCHAR(500) NULL,
        ReviewedByStaffID  INT NULL,
        ReviewedAt         DATETIME NULL,
        CreatedAt          DATETIME NOT NULL CONSTRAINT DF_LoanApproval_CreatedAt DEFAULT (GETDATE()),
        CONSTRAINT FK_LoanApproval_Loan FOREIGN KEY (LoanID) REFERENCES dbo.Loan(LoanID)
    );
    CREATE INDEX IX_LoanApproval_Loan_Type_Status ON dbo.Loan_Approval(LoanID, RequestType, Status);
END
GO

-- Partial_Payment_Approval -- holds a short (partial) EMI payment until a
-- Branch Manager approves it; only then is it finalized into
-- Daily_Collection_Sheet / Collection.
IF OBJECT_ID('dbo.Partial_Payment_Approval', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Partial_Payment_Approval (
        PartialPaymentApprovalID INT IDENTITY(1,1) PRIMARY KEY,
        LoanID             INT NOT NULL,
        ScheduleID         INT NOT NULL,
        RequestedAmount    DECIMAL(12,2) NOT NULL,
        CollectionDate     DATE NOT NULL,
        Remarks            NVARCHAR(500) NULL,
        Status             NVARCHAR(20) NOT NULL CONSTRAINT DF_PartialPaymentApproval_Status DEFAULT ('Pending'),
        RequestedByStaffID INT NOT NULL,
        RemarksByApprover  NVARCHAR(500) NULL,
        ReviewedByStaffID  INT NULL,
        ReviewedAt         DATETIME NULL,
        CreatedAt          DATETIME NOT NULL CONSTRAINT DF_PartialPaymentApproval_CreatedAt DEFAULT (GETDATE()),
        CONSTRAINT FK_PartialPaymentApproval_Loan FOREIGN KEY (LoanID) REFERENCES dbo.Loan(LoanID),
        CONSTRAINT FK_PartialPaymentApproval_Schedule FOREIGN KEY (ScheduleID) REFERENCES dbo.Repayment_Schedule(ScheduleID)
    );
    CREATE INDEX IX_PartialPaymentApproval_Status ON dbo.Partial_Payment_Approval(Status);
END
GO

-- Leave_Request already had Status/RemarksByApprover/ApprovedByStaffID --
-- just add a review timestamp to match the shape of the tables above.
IF COL_LENGTH('dbo.Leave_Request', 'ReviewedAt') IS NULL
    ALTER TABLE dbo.Leave_Request ADD ReviewedAt DATETIME NULL;
GO


-- ============================================================================
-- 12 -- Designation_Mobile_Menu (database/12_designation_mobile_menu.sql)
-- Admin-configurable mobile-menu visibility per Designation (Role is too
-- coarse -- several designations like CM/AM/PCM/RM/SCM share one Role).
-- Deny-by-default: a designation with no row for a key can't see that screen.
-- ============================================================================
IF OBJECT_ID('dbo.Designation_Mobile_Menu', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Designation_Mobile_Menu (
        DesignationID     INT NOT NULL,
        MenuKey           NVARCHAR(50) NOT NULL,
        IsVisible         BIT NOT NULL CONSTRAINT DF_DesignationMobileMenu_IsVisible DEFAULT (1),
        UpdatedAt         DATETIME NOT NULL CONSTRAINT DF_DesignationMobileMenu_UpdatedAt DEFAULT (GETDATE()),
        UpdatedByStaffID  INT NULL,
        CONSTRAINT PK_DesignationMobileMenu PRIMARY KEY (DesignationID, MenuKey),
        CONSTRAINT FK_DesignationMobileMenu_Designation FOREIGN KEY (DesignationID) REFERENCES dbo.Designation_Master(DesignationID)
    );
END
GO
-- Managed day-to-day from mobile_menu_access.php; current menu keys defined
-- in includes/mobile_menu_helper.php's MOBILE_MENU_DEFS.


-- ============================================================================
-- 13 -- SOS_Alert (database/13_sos_alert.sql)
-- Logs every SOS button press from the mobile app (who, where, when). The
-- alert itself is a Notification row per recipient (own branch's Branch
-- Manager(s) + every Admin), inserted alongside this by api/v1/sos/trigger.php.
-- ============================================================================
IF OBJECT_ID('dbo.SOS_Alert', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.SOS_Alert (
        SOSAlertID   INT IDENTITY(1,1) PRIMARY KEY,
        StaffID      INT NOT NULL,
        Latitude     DECIMAL(10,7) NULL,
        Longitude    DECIMAL(10,7) NULL,
        TriggeredAt  DATETIME NOT NULL CONSTRAINT DF_SOSAlert_TriggeredAt DEFAULT (GETDATE()),
        CONSTRAINT FK_SOSAlert_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID)
    );
END
GO


-- ============================================================================
-- 16 -- App_Setting (database/16_app_settings.sql)
-- Generic admin-editable key/value settings (e.g. the festival-banner
-- toggle, the Billing kill switch below) -- editable live from the web,
-- unlike api/app_version_config.json which gets overwritten on every deploy.
-- ============================================================================
IF OBJECT_ID('dbo.App_Setting', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.App_Setting (
        SettingKey       NVARCHAR(50) NOT NULL PRIMARY KEY,
        SettingValue     NVARCHAR(255) NOT NULL,
        UpdatedAt        DATETIME NOT NULL CONSTRAINT DF_AppSetting_UpdatedAt DEFAULT (GETDATE()),
        UpdatedByStaffID INT NULL
    );
END
GO


-- ============================================================================
-- 17 -- SuperAdmin role + Billing_Invoice (database/17_superadmin_billing.sql)
-- SuperAdmin: unrestricted web-CRM role (bypasses Role_Permission and every
-- "Admin only" page gate) so this codebase can be copied/sold to other
-- companies with one owner-level account. Billing_Invoice: monthly/
-- quarterly/yearly billing records, generated from billing.php.
-- Also seeds App_Setting 'services_enabled' -- the global kill switch
-- billing.php's Enable/Disable Service button flips.
-- ============================================================================
IF NOT EXISTS (SELECT 1 FROM Role_Master WHERE RoleName = 'SuperAdmin')
    INSERT INTO Role_Master (RoleName) VALUES ('SuperAdmin');
GO

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
GO

IF NOT EXISTS (SELECT 1 FROM App_Setting WHERE SettingKey = 'services_enabled')
    INSERT INTO App_Setting (SettingKey, SettingValue, UpdatedAt) VALUES ('services_enabled', '1', GETDATE());
GO


-- ============================================================================
-- 18 -- Loan.DisbursementPhotoPath (database/18_disbursement_photo.sql)
-- A customer photo must be captured at disbursement time (anti-fraud: proves
-- the customer was physically present) before a Branch Manager can disburse
-- an approved loan.
-- ============================================================================
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Loan' AND COLUMN_NAME = 'DisbursementPhotoPath')
    ALTER TABLE Loan ADD DisbursementPhotoPath NVARCHAR(500) NULL;
GO


-- ============================================================================
-- 19 -- Area Manager role (database/19_area_manager_role.sql)
-- A new Role (not just the pre-existing "AM" Designation) so it gets the
-- same whole-branch scoping every non-officer, non-admin role already gets
-- for free (api/includes/scope_helper.php) -- an AM oversees a branch's
-- loans/collections, not just their own book, so it can't be a Loan-Officer-
-- family role the way the "AM" Designation was. Existing AM-designated staff
-- are migrated onto this Role (their Designation is untouched).
-- Grants: approve_loan_proposal (above the BM limit -- see migration 21),
-- transfer_center, conduct_tvr. Also seeds the AM's mobile menu keys
-- (am_loan_approval, flo_collection, am_center_transfer, am_tvr).
-- ============================================================================
IF NOT EXISTS (SELECT 1 FROM Role_Master WHERE RoleName = 'Area Manager')
    INSERT INTO Role_Master (RoleName) VALUES ('Area Manager');
GO
-- (Role_Permission grants and Designation_Mobile_Menu seeding for this role
--  are pure data, not schema -- see 19_area_manager_role.sql directly.)


-- ============================================================================
-- 20 -- Staff_Branch_Map (database/20_am_branch_mapping.sql)
-- Lets one Area Manager cover multiple branches ("an Area" = several
-- branches), configurable via am_branch_mapping.php. An AM with no rows here
-- falls back to just their own Staff.BranchID.
-- ============================================================================
IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'Staff_Branch_Map')
BEGIN
    CREATE TABLE Staff_Branch_Map (
        MapID INT IDENTITY(1,1) PRIMARY KEY,
        StaffID INT NOT NULL FOREIGN KEY REFERENCES Staff(StaffID),
        BranchID INT NOT NULL FOREIGN KEY REFERENCES Branch(BranchID),
        CreatedAt DATETIME NOT NULL DEFAULT GETDATE(),
        CreatedByStaffID INT NULL,
        CONSTRAINT UQ_Staff_Branch_Map UNIQUE (StaffID, BranchID)
    );
END
GO


-- ============================================================================
-- 21 -- Loan_Approval: two-stage BM -> AM approval (database/21_two_stage_loan_approval.sql)
-- Above the BM limit (₹20,000), a Branch Manager's approval only moves the
-- request to an intermediate 'BM Approved' status -- it then needs an Area
-- Manager's final sign-off ('Approved'). These three columns hold that first
-- (BM) review separately from the pre-existing ReviewedByStaffID/ReviewedAt/
-- RemarksByApprover columns, which now always mean "whoever gave the FINAL
-- decision".
-- ============================================================================
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Loan_Approval' AND COLUMN_NAME = 'BMReviewedByStaffID')
    ALTER TABLE Loan_Approval ADD BMReviewedByStaffID INT NULL;
GO
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Loan_Approval' AND COLUMN_NAME = 'BMReviewedAt')
    ALTER TABLE Loan_Approval ADD BMReviewedAt DATETIME NULL;
GO
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Loan_Approval' AND COLUMN_NAME = 'BMRemarks')
    ALTER TABLE Loan_Approval ADD BMRemarks NVARCHAR(500) NULL;
GO


-- ============================================================================
-- END OF REFERENCE
-- ============================================================================
