-- Key Fact Sheet (KFS): the RBI-mandated pre-disbursement disclosure
-- document for a loan (amount, APR, fees, EMI schedule, grievance
-- redressal contact, etc). Computed live from Loan/Loan_Scheme at view
-- time (see includes/kfs_helper.php) -- nothing about the KFS itself is
-- persisted except whether the signed paper copy has been collected.
--
-- Company_Profile holds the NBFC-level facts every KFS prints in its
-- header/footer (registered office, license no, grievance officer, ...).
-- Single row, seeded with placeholders -- Admin fills in the real values
-- via kfs_settings.php before relying on this for compliance.
IF OBJECT_ID('dbo.Company_Profile', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Company_Profile (
        ProfileID INT NOT NULL PRIMARY KEY CHECK (ProfileID = 1),
        CompanyName NVARCHAR(200) NOT NULL DEFAULT '[Company Name]',
        NBFCRegistrationNo NVARCHAR(100) NOT NULL DEFAULT '[NBFC Registration No.]',
        RegisteredOfficeAddress NVARCHAR(500) NOT NULL DEFAULT '[Registered Office Address]',
        CustomerCarePhone NVARCHAR(50) NOT NULL DEFAULT '[Customer Care Phone]',
        CustomerCareEmail NVARCHAR(100) NOT NULL DEFAULT '[Customer Care Email]',
        GrievanceOfficerName NVARCHAR(150) NOT NULL DEFAULT '[Grievance Redressal Officer Name]',
        GrievanceOfficerPhone NVARCHAR(50) NOT NULL DEFAULT '[Grievance Officer Phone]',
        CoolingOffPeriodDays INT NOT NULL DEFAULT 3,
        UpdatedAt DATETIME NULL,
        UpdatedByStaffID INT NULL
    );
    INSERT INTO Company_Profile (ProfileID) VALUES (1);
END
GO

IF COL_LENGTH('dbo.Loan', 'KFSSignedCollected') IS NULL
    ALTER TABLE dbo.Loan ADD KFSSignedCollected BIT NOT NULL DEFAULT 0;
GO
IF COL_LENGTH('dbo.Loan', 'KFSSignedAt') IS NULL
    ALTER TABLE dbo.Loan ADD KFSSignedAt DATETIME NULL;
GO
IF COL_LENGTH('dbo.Loan', 'KFSSignedByStaffID') IS NULL
    ALTER TABLE dbo.Loan ADD KFSSignedByStaffID INT NULL;
GO
