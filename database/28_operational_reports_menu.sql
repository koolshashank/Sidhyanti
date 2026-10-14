-- ============================================================
-- 28_operational_reports_menu.sql
-- Seeds the new 'operational_reports' mobile menu key (Center-wise
-- Collection, Today Pending Collection, Today Disbursed, Due
-- Disbursement, Arrears Clients, Default Loans) to the designations that
-- already deal with loans/collections in the field -- Admin can
-- grant/revoke this for any other designation later via
-- mobile_menu_access.php. Each report is row-scoped server-side by role
-- (Field Officer -> own book, Branch Manager/BM/ABM -> own branch, AM ->
-- every mapped branch), so granting the menu key is safe for all of these.
-- ============================================================

DECLARE @SystemStaffID INT = (
    SELECT TOP 1 s.StaffID FROM Staff s
    JOIN Role_Master r ON s.RoleID = r.RoleID
    WHERE r.RoleName = 'Admin' ORDER BY s.StaffID
);

DECLARE @Seed TABLE (DesignationName NVARCHAR(100));
INSERT INTO @Seed (DesignationName) VALUES
    ('Field Officer'), ('Branch Manager'), ('BM'), ('ABM'), ('AM'), ('CM');

INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible, UpdatedAt, UpdatedByStaffID)
SELECT dm.DesignationID, 'operational_reports', 1, GETDATE(), @SystemStaffID
FROM Designation_Master dm
JOIN @Seed s ON dm.DesignationName = s.DesignationName
WHERE NOT EXISTS (
    SELECT 1 FROM Designation_Mobile_Menu existing
    WHERE existing.DesignationID = dm.DesignationID AND existing.MenuKey = 'operational_reports'
);
GO
