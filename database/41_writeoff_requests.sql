-- NPA write-off requests. NPA itself (90+ days overdue, same "PAR 90+"
-- definition portfolio.php already uses) is a computed classification, not
-- a stored stage -- see api/v1/reports/npa_loans.php. Writing a loan off is
-- the one piece that needs a durable record: a field request, reviewed by
-- Admin only. Approval reactivates two already-existing-but-unused schema
-- pieces instead of adding new ones -- Loan.SettlementTypeID (Settlement_
-- Type_Master already has a 'Written Off' row, SettlementTypeID=3, that
-- nothing in the app has ever set) and the existing 'Closed' loan stage
-- (same terminal stage a normally-completed loan reaches).
IF OBJECT_ID('dbo.Loan_WriteOff_Request', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Loan_WriteOff_Request (
        RequestID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        LoanID INT NOT NULL,
        RequestedByStaffID INT NOT NULL,
        RequestReason NVARCHAR(500) NULL,
        OutstandingAmount DECIMAL(18,2) NOT NULL,
        Status VARCHAR(20) NOT NULL DEFAULT 'Pending' CHECK (Status IN ('Pending', 'Approved', 'Rejected')),
        ReviewedByStaffID INT NULL,
        ReviewedAt DATETIME NULL,
        ReviewRemarks NVARCHAR(500) NULL,
        CreatedAt DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_WriteOffRequest_Loan FOREIGN KEY (LoanID) REFERENCES dbo.Loan(LoanID),
        CONSTRAINT FK_WriteOffRequest_RequestedBy FOREIGN KEY (RequestedByStaffID) REFERENCES dbo.Staff(StaffID),
        CONSTRAINT FK_WriteOffRequest_ReviewedBy FOREIGN KEY (ReviewedByStaffID) REFERENCES dbo.Staff(StaffID)
    );
    CREATE INDEX IX_WriteOffRequest_Loan_Status ON dbo.Loan_WriteOff_Request(LoanID, Status);
END
GO
