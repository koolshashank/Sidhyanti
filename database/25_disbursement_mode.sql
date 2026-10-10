-- ============================================================
-- 25_disbursement_mode.sql
-- Adds a Disbursement Mode (Cash / NEFT / RTGS) to Loan, captured by the
-- BM at disbursement time. For NEFT/RTGS, the mobile app makes the BM
-- re-type the member's bank account number as a confirmation step (the
-- account number shown on the disbursement card is masked so it can't be
-- read off the screen); the server compares the re-typed value against
-- Member_Bank_Details.AccountNumber before allowing the disbursement.
-- Run this once in SSMS against the SidhyantiCRM database.
-- ============================================================

IF COL_LENGTH('dbo.Loan', 'DisbursementMode') IS NULL
    ALTER TABLE dbo.Loan ADD DisbursementMode NVARCHAR(10) NULL;
GO
