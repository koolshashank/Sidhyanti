-- Mobile-menu visibility for the two new screens (see
-- Designation_Mobile_Menu / mobile_menu_helper.php). Both are seeded on for
-- every existing designation so no one is unexpectedly blocked on day one --
-- Admin can restrict either later via mobile_menu_access.php exactly like
-- any other menu key.
DECLARE @SystemStaffID INT = (
    SELECT TOP 1 s.StaffID FROM Staff s
    JOIN Role_Master r ON s.RoleID = r.RoleID
    WHERE r.RoleName = 'Admin' ORDER BY s.StaffID
);

INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible, UpdatedAt, UpdatedByStaffID)
SELECT dm.DesignationID, 'announcements', 1, GETDATE(), @SystemStaffID
FROM Designation_Master dm
WHERE NOT EXISTS (
    SELECT 1 FROM Designation_Mobile_Menu existing
    WHERE existing.DesignationID = dm.DesignationID AND existing.MenuKey = 'announcements'
);

INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible, UpdatedAt, UpdatedByStaffID)
SELECT dm.DesignationID, 'leaderboard', 1, GETDATE(), @SystemStaffID
FROM Designation_Master dm
WHERE NOT EXISTS (
    SELECT 1 FROM Designation_Mobile_Menu existing
    WHERE existing.DesignationID = dm.DesignationID AND existing.MenuKey = 'leaderboard'
);
GO
