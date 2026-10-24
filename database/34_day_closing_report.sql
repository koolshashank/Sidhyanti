-- Day Closing Report -- a per-branch, per-day cash reconciliation
-- submitted by the Branch Manager/Cashier. The "auto" columns are
-- recomputed and stamped server-side at submit time (never trusted from
-- the client); the rest are typed in by hand. OpeningBalance carries
-- forward from the previous day's GrandTotal for the same branch.
--
-- TotalOutflow   = LoanDisbursement + every manual outflow field
-- TotalInflow    = OpeningBalance + CollectedAmount + every manual inflow
--                  field + PreclosureAmount + ArrearRecoveryAmount
--                  (CollectionDues is reference-only, NOT summed in --
--                  it's what was due, not what was actually banked)
-- GrandTotal     = TotalInflow - TotalOutflow
IF OBJECT_ID('dbo.Day_Closing_Report', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Day_Closing_Report (
        DayClosingID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        BranchID INT NOT NULL,
        ClosingDate DATE NOT NULL,

        -- OutFlow
        LoanDisbursement DECIMAL(18,2) NOT NULL DEFAULT 0,
        FundTransferFromAnotherBranch DECIMAL(18,2) NOT NULL DEFAULT 0,
        RentExpenses DECIMAL(18,2) NOT NULL DEFAULT 0,
        StationaryAssets DECIMAL(18,2) NOT NULL DEFAULT 0,
        SalaryPayment DECIMAL(18,2) NOT NULL DEFAULT 0,
        OtherExpenses DECIMAL(18,2) NOT NULL DEFAULT 0,
        PettyCashPayment DECIMAL(18,2) NOT NULL DEFAULT 0,
        CashDepositByHOBank DECIMAL(18,2) NOT NULL DEFAULT 0,
        CashDepositByBranchBank DECIMAL(18,2) NOT NULL DEFAULT 0,
        TotalOutflow DECIMAL(18,2) NOT NULL DEFAULT 0,

        -- InFlow
        OpeningBalance DECIMAL(18,2) NOT NULL DEFAULT 0,
        CollectionDues DECIMAL(18,2) NOT NULL DEFAULT 0,
        CollectedAmount DECIMAL(18,2) NOT NULL DEFAULT 0,
        EnrollmentFee DECIMAL(18,2) NOT NULL DEFAULT 0,
        RiskFee DECIMAL(18,2) NOT NULL DEFAULT 0,
        ProcessingFee DECIMAL(18,2) NOT NULL DEFAULT 0,
        PreclosureAmount DECIMAL(18,2) NOT NULL DEFAULT 0,
        ArrearRecoveryAmount DECIMAL(18,2) NOT NULL DEFAULT 0,
        OtherIncome DECIMAL(18,2) NOT NULL DEFAULT 0,
        BankWithdrawal DECIMAL(18,2) NOT NULL DEFAULT 0,
        FundReceived DECIMAL(18,2) NOT NULL DEFAULT 0,
        FundReceivedByHO DECIMAL(18,2) NOT NULL DEFAULT 0,
        TotalInflow DECIMAL(18,2) NOT NULL DEFAULT 0,

        GrandTotal DECIMAL(18,2) NOT NULL DEFAULT 0,

        CreatedByStaffID INT NOT NULL,
        CreatedAt DATETIME NOT NULL DEFAULT GETDATE(),

        CONSTRAINT FK_DayClosingReport_Branch FOREIGN KEY (BranchID) REFERENCES dbo.Branch(BranchID),
        CONSTRAINT FK_DayClosingReport_Staff FOREIGN KEY (CreatedByStaffID) REFERENCES dbo.Staff(StaffID),
        CONSTRAINT UQ_DayClosingReport_Branch_Date UNIQUE (BranchID, ClosingDate)
    );
    CREATE INDEX IX_DayClosingReport_Branch_Date ON dbo.Day_Closing_Report(BranchID, ClosingDate);
END
GO
