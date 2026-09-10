-- ============================================================
-- 14_reloan_menu_seed.sql
-- Grants the new 'reloan' mobile menu item to the CM designation (the
-- same designation that already has create_center/client_sourcing/
-- flo_collection/client_details -- reloan is a natural extension of that
-- sourcing-adjacent workflow). Admin can grant/revoke this for any other
-- designation later via mobile_menu_access.php.
-- ============================================================

DECLARE @SystemStaffID INT = (
    SELECT TOP 1 s.StaffID FROM Staff s
    JOIN Role_Master r ON s.RoleID = r.RoleID
    WHERE r.RoleName = 'Admin' ORDER BY s.StaffID
);

INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible, UpdatedAt, UpdatedByStaffID)
SELECT dm.DesignationID, 'reloan', 1, GETDATE(), @SystemStaffID
FROM Designation_Master dm
WHERE dm.DesignationName = 'CM'
  AND NOT EXISTS (
    SELECT 1 FROM Designation_Mobile_Menu existing
    WHERE existing.DesignationID = dm.DesignationID AND existing.MenuKey = 'reloan'
  );
GO
