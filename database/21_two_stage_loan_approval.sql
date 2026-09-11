-- Two-stage approval for a loan Proposal above the BM limit (see
-- includes/loan_approval_helper.php): Branch Manager always reviews first;
-- for a loan at/under the limit, that's final (unchanged from before). For
-- a loan above the limit, a BM approval moves it to an intermediate
-- 'BM Approved' status instead of 'Approved' -- it then sits in the Area
-- Manager's queue for final sign-off. These three columns hold that first
-- (BM) review separately from the existing ReviewedByStaffID/ReviewedAt/
-- RemarksByApprover columns, which now always mean "whoever gave the FINAL
-- decision" (the BM themself, when no second stage applies).

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Loan_Approval' AND COLUMN_NAME = 'BMReviewedByStaffID')
    ALTER TABLE Loan_Approval ADD BMReviewedByStaffID INT NULL;

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Loan_Approval' AND COLUMN_NAME = 'BMReviewedAt')
    ALTER TABLE Loan_Approval ADD BMReviewedAt DATETIME NULL;

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Loan_Approval' AND COLUMN_NAME = 'BMRemarks')
    ALTER TABLE Loan_Approval ADD BMRemarks NVARCHAR(500) NULL;
