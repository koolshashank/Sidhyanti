-- Lets one Area Manager cover multiple branches ("an Area" = several
-- branches), configurable via am_branch_mapping.php. An AM's own
-- Staff.BranchID stays their "home" branch (used everywhere else in the
-- system exactly like before) -- this table is the *additional* explicit
-- set of branches api/includes/scope_helper.php scopes their loans/
-- collections/centers to. An AM with no rows here yet falls back to just
-- their own Staff.BranchID (seeded below), so nothing changes until an
-- admin explicitly maps more branches to them.

IF NOT EXISTS (SELECT 1 FROM sys.tables WHERE name = 'Staff_Branch_Map')
BEGIN
    CREATE TABLE Staff_Branch_Map (
        MapID INT IDENTITY(1,1) PRIMARY KEY,
        StaffID INT NOT NULL FOREIGN KEY REFERENCES Staff(StaffID),
        BranchID INT NOT NULL FOREIGN KEY REFERENCES Branch(BranchID),
        CreatedAt DATETIME NOT NULL DEFAULT GETDATE(),
        CreatedByStaffID INT NULL,
        CONSTRAINT UQ_Staff_Branch_Map UNIQUE (StaffID, BranchID)
    );
END

INSERT INTO Staff_Branch_Map (StaffID, BranchID)
SELECT s.StaffID, s.BranchID
FROM Staff s
WHERE s.RoleID = (SELECT RoleID FROM Role_Master WHERE RoleName = 'Area Manager')
  AND s.BranchID IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM Staff_Branch_Map m WHERE m.StaffID = s.StaffID AND m.BranchID = s.BranchID);
