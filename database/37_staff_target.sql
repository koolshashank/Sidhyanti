-- Monthly Disbursement target per staff member, set by Admin/Branch
-- Manager -- shown against actual achievement on the mobile "My
-- Performance" screen. Collection has no separate settable target (it's
-- always "100% of what's due"); that screen computes collection progress
-- directly from Daily_Collection_Sheet (due vs collected this month)
-- instead of reading anything from this table.
IF OBJECT_ID('dbo.Staff_Target', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Staff_Target (
        TargetID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StaffID INT NOT NULL,
        TargetYear INT NOT NULL,
        TargetMonth INT NOT NULL CHECK (TargetMonth BETWEEN 1 AND 12),
        DisbursementTarget DECIMAL(18,2) NOT NULL DEFAULT 0,
        UpdatedByStaffID INT NOT NULL,
        UpdatedAt DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_StaffTarget_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID),
        CONSTRAINT FK_StaffTarget_UpdatedBy FOREIGN KEY (UpdatedByStaffID) REFERENCES dbo.Staff(StaffID),
        CONSTRAINT UQ_StaffTarget_Staff_Month UNIQUE (StaffID, TargetYear, TargetMonth)
    );
    CREATE INDEX IX_StaffTarget_Staff_Month ON dbo.Staff_Target(StaffID, TargetYear, TargetMonth);
END
GO
