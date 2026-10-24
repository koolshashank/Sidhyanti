-- Salary advance requests: a Staff member can request an advance against
-- their salary, either "Personal" use (only allowed after the 15th of the
-- month, amount from a fixed 1000-5000 range) or "Emergency" use (allowed
-- any time, amount from a fixed 6000-10000 range). Reviewed by Admin/HR,
-- same Pending/Approved/Rejected flow as Leave_Request. This phase only
-- tracks the request + approval; no automatic salary deduction yet.
IF OBJECT_ID('dbo.Salary_Advance_Request', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Salary_Advance_Request (
        RequestID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StaffID INT NOT NULL,
        RequestType VARCHAR(20) NOT NULL CHECK (RequestType IN ('Personal', 'Emergency')),
        Amount DECIMAL(10,2) NOT NULL,
        Reason NVARCHAR(500) NULL,
        Status VARCHAR(20) NOT NULL DEFAULT 'Pending' CHECK (Status IN ('Pending', 'Approved', 'Rejected')),
        ReviewedByStaffID INT NULL,
        ReviewedAt DATETIME NULL,
        ReviewRemarks NVARCHAR(500) NULL,
        CreatedAt DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_SalaryAdvanceRequest_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID),
        CONSTRAINT FK_SalaryAdvanceRequest_Reviewer FOREIGN KEY (ReviewedByStaffID) REFERENCES dbo.Staff(StaffID)
    );
    CREATE INDEX IX_SalaryAdvanceRequest_Staff_Status ON dbo.Salary_Advance_Request(StaffID, Status);
END
GO
