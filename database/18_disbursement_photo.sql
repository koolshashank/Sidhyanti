-- A customer photo must be captured at disbursement time before a Branch
-- Manager can disburse an approved loan (anti-fraud: proves the customer was
-- physically present). One column is enough -- a single required photo per
-- loan, same pattern as Member.Photo.

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Loan' AND COLUMN_NAME = 'DisbursementPhotoPath')
BEGIN
    ALTER TABLE Loan ADD DisbursementPhotoPath NVARCHAR(500) NULL;
END
