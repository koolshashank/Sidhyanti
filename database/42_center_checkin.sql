-- Center Meeting Geo Check-in: when a field officer visits a center for its
-- weekly meeting (Center.MeetingDay/MeetingTime, already an existing
-- column), they check in from the mobile app and their GPS position is
-- compared against the center's own stored Latitude/Longitude (also
-- already existing columns, just never read for this purpose before).
-- Distinct from Center_Meeting_Attendance, which records whether each
-- individual borrower attended -- this is whether the OFFICER physically
-- showed up, for compliance/anti-fraud auditing.
IF OBJECT_ID('dbo.Center_Visit_Checkin', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Center_Visit_Checkin (
        CheckinID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CenterID INT NOT NULL,
        StaffID INT NOT NULL,
        CheckinAt DATETIME NOT NULL DEFAULT GETDATE(),
        Latitude DECIMAL(10,7) NOT NULL,
        Longitude DECIMAL(10,7) NOT NULL,
        DistanceMeters DECIMAL(10,2) NULL,
        WithinGeofence BIT NULL,
        CONSTRAINT FK_CenterVisitCheckin_Center FOREIGN KEY (CenterID) REFERENCES dbo.Center(CenterID),
        CONSTRAINT FK_CenterVisitCheckin_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID)
    );
    CREATE INDEX IX_CenterVisitCheckin_Center_Date ON dbo.Center_Visit_Checkin(CenterID, CheckinAt);
    CREATE INDEX IX_CenterVisitCheckin_Staff_Date ON dbo.Center_Visit_Checkin(StaffID, CheckinAt);
END
GO

-- Mobile-menu visibility for the new "Today's Meetings" screen -- seeded on
-- for every existing designation, same as every other new menu key's first
-- migration (see 38_announcements_leaderboard_menu.sql).
DECLARE @SystemStaffID INT = (
    SELECT TOP 1 s.StaffID FROM Staff s
    JOIN Role_Master r ON s.RoleID = r.RoleID
    WHERE r.RoleName = 'Admin' ORDER BY s.StaffID
);

INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible, UpdatedAt, UpdatedByStaffID)
SELECT dm.DesignationID, 'todays_meetings', 1, GETDATE(), @SystemStaffID
FROM Designation_Master dm
WHERE NOT EXISTS (
    SELECT 1 FROM Designation_Mobile_Menu existing
    WHERE existing.DesignationID = dm.DesignationID AND existing.MenuKey = 'todays_meetings'
);
GO
