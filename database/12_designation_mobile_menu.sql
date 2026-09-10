-- ============================================================
-- 12_designation_mobile_menu.sql
-- Admin-configurable mobile-menu visibility, keyed by Designation (not
-- Role -- CM/AM/PCM/RM/SCM all share RoleID=3 "Loan Officer" today, so
-- Role is too coarse to tell them apart; Designation is the real
-- job-function axis). Mirrors the existing Role_Permission pattern.
-- Run this once in SSMS against the SidhyantiCRM database.
-- ============================================================

IF OBJECT_ID('dbo.Designation_Mobile_Menu', 'U') IS NULL
CREATE TABLE dbo.Designation_Mobile_Menu (
    DesignationID     INT NOT NULL,
    MenuKey           NVARCHAR(50) NOT NULL,
    IsVisible         BIT NOT NULL CONSTRAINT DF_DesignationMobileMenu_IsVisible DEFAULT (1),
    UpdatedAt         DATETIME NOT NULL CONSTRAINT DF_DesignationMobileMenu_UpdatedAt DEFAULT (GETDATE()),
    UpdatedByStaffID  INT NULL,
    CONSTRAINT PK_DesignationMobileMenu PRIMARY KEY (DesignationID, MenuKey),
    CONSTRAINT FK_DesignationMobileMenu_Designation FOREIGN KEY (DesignationID) REFERENCES dbo.Designation_Master(DesignationID)
);
GO

-- Seed today's exact behavior so nothing changes for existing staff until
-- an Admin deliberately edits the new matrix in mobile_menu_access.php.
DECLARE @SystemStaffID INT = (
    SELECT TOP 1 s.StaffID FROM Staff s
    JOIN Role_Master r ON s.RoleID = r.RoleID
    WHERE r.RoleName = 'Admin' ORDER BY s.StaffID
);

DECLARE @Seed TABLE (DesignationName NVARCHAR(100), MenuKey NVARCHAR(50));
INSERT INTO @Seed (DesignationName, MenuKey) VALUES
    ('CM', 'create_center'), ('CM', 'client_sourcing'), ('CM', 'flo_collection'), ('CM', 'client_details'),
    ('BM', 'client_details'), ('BM', 'flo_collection'), ('BM', 'bm_approval'), ('BM', 'disbursement'), ('BM', 'dcr'), ('BM', 'center_approval'),
    ('ABM', 'client_details'), ('ABM', 'flo_collection'), ('ABM', 'bm_approval'), ('ABM', 'disbursement'), ('ABM', 'dcr'), ('ABM', 'center_approval');

INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible, UpdatedAt, UpdatedByStaffID)
SELECT dm.DesignationID, s.MenuKey, 1, GETDATE(), @SystemStaffID
FROM @Seed s
JOIN Designation_Master dm ON dm.DesignationName = s.DesignationName
WHERE NOT EXISTS (
    SELECT 1 FROM Designation_Mobile_Menu existing
    WHERE existing.DesignationID = dm.DesignationID AND existing.MenuKey = s.MenuKey
);
GO
