-- Adds a persistent LoanCycle column to Loan: which numbered loan this is
-- for the member (1 = first loan ever, 2 = second, ...), ordered by creation.
-- Backfills every existing loan, then every future loan is set at creation
-- time in api/v1/loans/create.php.
SET QUOTED_IDENTIFIER ON;
GO

IF COL_LENGTH('dbo.Loan', 'LoanCycle') IS NULL
BEGIN
    ALTER TABLE dbo.Loan ADD LoanCycle INT NULL;
END
GO

WITH Numbered AS (
    SELECT LoanID, ROW_NUMBER() OVER (PARTITION BY MemberID ORDER BY CreatedAt, LoanID) AS CycleNo
    FROM dbo.Loan
)
UPDATE l
SET l.LoanCycle = n.CycleNo
FROM dbo.Loan l
JOIN Numbered n ON n.LoanID = l.LoanID
WHERE l.LoanCycle IS NULL;
GO
