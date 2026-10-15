-- Staff field-location tracking: a Staff member starts/ends a "duty
-- session" from the mobile app; while active, the app pings their position
-- every ~5 minutes (or sooner if they've moved 50m). Each ping is appended
-- to Duty_Session_Point (full route history) and also overwrites
-- Staff_Live_Location (one row per staff, used for the Admin "who's on duty
-- right now" live map so that query never has to scan the full point log).
-- Consent is recorded once, separately, before the app is allowed to start
-- its first duty session.

IF OBJECT_ID('dbo.Staff_Tracking_Consent', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Staff_Tracking_Consent (
        StaffID INT NOT NULL PRIMARY KEY,
        ConsentGiven BIT NOT NULL DEFAULT 0,
        ConsentAt DATETIME NULL,
        CONSTRAINT FK_StaffTrackingConsent_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID)
    );
END
GO

IF OBJECT_ID('dbo.Duty_Session', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Duty_Session (
        SessionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StaffID INT NOT NULL,
        StartTime DATETIME NOT NULL DEFAULT GETDATE(),
        EndTime DATETIME NULL,
        Status VARCHAR(10) NOT NULL DEFAULT 'Active' CHECK (Status IN ('Active', 'Ended')),
        CreatedAt DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_DutySession_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID)
    );
    CREATE INDEX IX_DutySession_Staff_Status ON dbo.Duty_Session(StaffID, Status);
END
GO

IF OBJECT_ID('dbo.Duty_Session_Point', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Duty_Session_Point (
        PointID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        SessionID INT NOT NULL,
        StaffID INT NOT NULL,
        Latitude DECIMAL(10,7) NOT NULL,
        Longitude DECIMAL(10,7) NOT NULL,
        Accuracy DECIMAL(10,2) NULL,
        Speed DECIMAL(10,2) NULL,
        CapturedAt DATETIME NOT NULL,
        CreatedAt DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_DutySessionPoint_Session FOREIGN KEY (SessionID) REFERENCES dbo.Duty_Session(SessionID),
        CONSTRAINT FK_DutySessionPoint_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID)
    );
    CREATE INDEX IX_DutySessionPoint_Session_CapturedAt ON dbo.Duty_Session_Point(SessionID, CapturedAt);
    CREATE INDEX IX_DutySessionPoint_Staff_CapturedAt ON dbo.Duty_Session_Point(StaffID, CapturedAt);
END
GO

IF OBJECT_ID('dbo.Staff_Live_Location', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Staff_Live_Location (
        StaffID INT NOT NULL PRIMARY KEY,
        SessionID INT NULL,
        Latitude DECIMAL(10,7) NOT NULL,
        Longitude DECIMAL(10,7) NOT NULL,
        Accuracy DECIMAL(10,2) NULL,
        CapturedAt DATETIME NOT NULL,
        UpdatedAt DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_StaffLiveLocation_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID),
        CONSTRAINT FK_StaffLiveLocation_Session FOREIGN KEY (SessionID) REFERENCES dbo.Duty_Session(SessionID)
    );
END
GO

-- Mobile-menu visibility for the two new screens (see
-- Designation_Mobile_Menu / mobile_menu_helper.php). 'duty_tracking'
-- (Start/End Duty) is seeded on for every existing designation so no one
-- is unexpectedly blocked on day one -- Admin can restrict it later via
-- mobile_menu_access.php exactly like any other menu key. 'staff_tracking'
-- (the live map + route history) is seeded only for designations under the
-- Admin role.
DECLARE @SystemStaffID INT = (
    SELECT TOP 1 s.StaffID FROM Staff s
    JOIN Role_Master r ON s.RoleID = r.RoleID
    WHERE r.RoleName = 'Admin' ORDER BY s.StaffID
);

INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible, UpdatedAt, UpdatedByStaffID)
SELECT dm.DesignationID, 'duty_tracking', 1, GETDATE(), @SystemStaffID
FROM Designation_Master dm
WHERE NOT EXISTS (
    SELECT 1 FROM Designation_Mobile_Menu existing
    WHERE existing.DesignationID = dm.DesignationID AND existing.MenuKey = 'duty_tracking'
);

INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible, UpdatedAt, UpdatedByStaffID)
SELECT dm.DesignationID, 'staff_tracking', 1, GETDATE(), @SystemStaffID
FROM Designation_Master dm
JOIN Role_Master rm ON dm.RoleID = rm.RoleID
WHERE rm.RoleName = 'Admin'
  AND NOT EXISTS (
    SELECT 1 FROM Designation_Mobile_Menu existing
    WHERE existing.DesignationID = dm.DesignationID AND existing.MenuKey = 'staff_tracking'
);
GO
