-- Adds "Resident Income" as its own line item in the Loan Proposal's Income
-- Assessment, alongside Self/Husband-Son/Other Income -- requested so the
-- field officer can capture it separately instead of folding it into
-- "Other Income".
ALTER TABLE Loan_Income_Assessment ADD ResidentIncomeMonthly DECIMAL(12,2) NULL;
