-- "Remember Me" persistent login cookies (index.php) -- selector/validator
-- pattern: Selector is the DB lookup key (not secret), ValidatorHash is the
-- actual secret (never stored in plaintext, verified with hash_equals()).
-- Each successful auto-login rotates the token (deletes this row, issues a
-- new one), so a stolen-but-unused cookie stops working the moment the
-- legitimate user's browser uses its own copy next.
IF OBJECT_ID('dbo.Staff_Remember_Token', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Staff_Remember_Token (
        TokenID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StaffID INT NOT NULL,
        Selector CHAR(24) NOT NULL,
        ValidatorHash CHAR(64) NOT NULL,
        ExpiresAt DATETIME NOT NULL,
        CreatedAt DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_StaffRememberToken_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID)
    );
    CREATE UNIQUE INDEX UQ_StaffRememberToken_Selector ON dbo.Staff_Remember_Token(Selector);
END
GO
