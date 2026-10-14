-- One FCM registration token per staff member (latest device wins, same
-- single-device assumption the existing device-lock feature already makes).
IF OBJECT_ID('dbo.Staff_Push_Token', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Staff_Push_Token (
        StaffID INT NOT NULL PRIMARY KEY,
        FcmToken NVARCHAR(500) NOT NULL,
        UpdatedAt DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_StaffPushToken_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID)
    );
END
GO
