-- Branch Manager "Operations" menu: Center Approval, Loan Proposal/Preclose
-- Approval, Partial Payment Approval, Disbursement.
-- Run once, by hand, against the SidhyantiCRM database (no migration runner
-- in this project -- see database/9_staff_mobile_password.sql for the same
-- idempotent-guard convention this file follows).

-- ---------------------------------------------------------------------
-- 1. Center approval columns
-- ---------------------------------------------------------------------
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

-- Every center that already existed before this feature shipped is
-- considered already-approved -- only centers created AFTER this migration
-- runs should ever sit in 'Pending'.
UPDATE dbo.Center SET ApprovalStatus = 'Approved' WHERE ApprovalStatus = 'Pending';
GO

-- ---------------------------------------------------------------------
-- 2. Disbursement columns
-- ---------------------------------------------------------------------
IF COL_LENGTH('dbo.Loan', 'DisbursedAmount') IS NULL
    ALTER TABLE dbo.Loan ADD DisbursedAmount DECIMAL(12,2) NULL;
GO
IF COL_LENGTH('dbo.Loan', 'DisbursedByStaffID') IS NULL
    ALTER TABLE dbo.Loan ADD DisbursedByStaffID INT NULL;
GO
IF COL_LENGTH('dbo.Loan', 'DisbursementRefNo') IS NULL
    ALTER TABLE dbo.Loan ADD DisbursementRefNo NVARCHAR(100) NULL;
GO

-- ---------------------------------------------------------------------
-- 3. Loan_Approval -- BM sign-off on a loan proposal, or on a preclose
--    request (RequestType discriminates; see includes/permission_helper.php
--    for the gating permission keys).
-- ---------------------------------------------------------------------
IF OBJECT_ID('dbo.Loan_Approval', 'U') IS NULL
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
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_LoanApproval_Loan_Type_Status' AND object_id = OBJECT_ID('dbo.Loan_Approval'))
    CREATE INDEX IX_LoanApproval_Loan_Type_Status ON dbo.Loan_Approval(LoanID, RequestType, Status);
GO

-- ---------------------------------------------------------------------
-- 4. Partial_Payment_Approval -- holds a short (partial) EMI payment until
--    a Branch Manager approves it; only then is it finalized into
--    Daily_Collection_Sheet / Collection (see api/includes/collection_finalize.php).
-- ---------------------------------------------------------------------
IF OBJECT_ID('dbo.Partial_Payment_Approval', 'U') IS NULL
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
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PartialPaymentApproval_Status' AND object_id = OBJECT_ID('dbo.Partial_Payment_Approval'))
    CREATE INDEX IX_PartialPaymentApproval_Status ON dbo.Partial_Payment_Approval(Status);
GO

-- ---------------------------------------------------------------------
-- 5. Leave_Request already has Status/RemarksByApprover/ApprovedByStaffID --
--    just add a review timestamp to match the Death_Entry/Center/Loan_Approval
--    shape.
-- ---------------------------------------------------------------------
IF COL_LENGTH('dbo.Leave_Request', 'ReviewedAt') IS NULL
    ALTER TABLE dbo.Leave_Request ADD ReviewedAt DATETIME NULL;
GO

-- ---------------------------------------------------------------------
-- 6. Seed Role_Permission rows so the feature works immediately after this
--    migration, without an Admin having to click through role_permissions.php
--    first. Branch Manager gets every "approve_*"/"disburse_loan" key;
--    the field-facing officer roles get "request_loan_preclose" (the CM
--    designation used for center-level ops sits under these roles -- an
--    Admin can narrow/widen this later in role_permissions.php).
-- ---------------------------------------------------------------------
IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('dbo.Role_Permission') AND name = 'UpdatedAt')
BEGIN
    RAISERROR('Role_Permission table not found as expected -- skipping permission seed. Add these manually via role_permissions.php.', 16, 1);
END
ELSE
BEGIN
    -- Attribute these seed rows to an Admin StaffID (matches the
    -- NOT NULL UpdatedByStaffID every hand-driven save from
    -- role_permissions.php already writes) rather than leaving it NULL.
    DECLARE @SystemStaffID INT = (
        SELECT TOP 1 s.StaffID FROM Staff s
        JOIN Role_Master r ON s.RoleID = r.RoleID
        WHERE r.RoleName = 'Admin' ORDER BY s.StaffID
    );

    DECLARE @BranchManagerRoleID INT = (SELECT RoleID FROM Role_Master WHERE RoleName = 'Branch Manager');
    DECLARE @BmKeys TABLE (PermissionKey NVARCHAR(50));
    INSERT INTO @BmKeys VALUES ('approve_loan_proposal'), ('approve_loan_preclose'), ('approve_partial_payment'),
                                ('approve_leave_request'), ('approve_new_center'), ('disburse_loan');

    IF @BranchManagerRoleID IS NOT NULL
    BEGIN
        INSERT INTO Role_Permission (RoleID, PermissionKey, IsAllowed, UpdatedAt, UpdatedByStaffID)
        SELECT @BranchManagerRoleID, k.PermissionKey, 1, GETDATE(), @SystemStaffID
        FROM @BmKeys k
        WHERE NOT EXISTS (
            SELECT 1 FROM Role_Permission rp WHERE rp.RoleID = @BranchManagerRoleID AND rp.PermissionKey = k.PermissionKey
        );
    END

    DECLARE @OfficerRoleIDs TABLE (RoleID INT);
    INSERT INTO @OfficerRoleIDs SELECT RoleID FROM Role_Master WHERE RoleName IN ('Loan Officer', 'Field Officer');

    INSERT INTO Role_Permission (RoleID, PermissionKey, IsAllowed, UpdatedAt, UpdatedByStaffID)
    SELECT r.RoleID, 'request_loan_preclose', 1, GETDATE(), @SystemStaffID
    FROM @OfficerRoleIDs r
    WHERE NOT EXISTS (
        SELECT 1 FROM Role_Permission rp WHERE rp.RoleID = r.RoleID AND rp.PermissionKey = 'request_loan_preclose'
    );
END
GO
