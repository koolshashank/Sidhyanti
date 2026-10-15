-- Persists the preclose payoff quote (outstanding principal at request
-- time, see loan_preclose_quote() in includes/loan_preclose_helper.php) on
-- the Loan_Approval row itself. Previously this amount was only ever
-- computed on the fly and shown in the UI, never stored -- so after a
-- preclose was approved there was no way to answer "how much did this
-- preclose for" (the Today Preclose Report needs this).
IF COL_LENGTH('dbo.Loan_Approval', 'PrecloseQuoteAmount') IS NULL
BEGIN
    ALTER TABLE dbo.Loan_Approval ADD PrecloseQuoteAmount DECIMAL(18,2) NULL;
END
GO
