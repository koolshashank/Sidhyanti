-- Area Manager: a new Role (not just a Designation) so it gets the same
-- "whole branch" scoping every non-officer, non-admin role already gets for
-- free from api/includes/scope_helper.php (Loan Officer/Field Officer are
-- self-scoped; everything else is branch-scoped) -- an AM oversees a whole
-- branch's loans/collections, not just their own book, so it cannot be a
-- Loan Officer-family role the way the "AM" Designation currently is.
--
-- Existing staff whose Designation is "AM" are migrated onto this new Role
-- (their Designation itself is untouched -- Role and Designation are
-- independent, same as everywhere else in this system).

IF NOT EXISTS (SELECT 1 FROM Role_Master WHERE RoleName = 'Area Manager')
BEGIN
    INSERT INTO Role_Master (RoleName) VALUES ('Area Manager');
END

DECLARE @AMRoleID INT = (SELECT RoleID FROM Role_Master WHERE RoleName = 'Area Manager');

UPDATE s SET s.RoleID = @AMRoleID
FROM Staff s
JOIN Designation_Master d ON s.DesignationID = d.DesignationID
WHERE d.DesignationName = 'AM' AND s.RoleID <> @AMRoleID;

-- Area Manager can approve loan proposals (above the BM's ₹20,000 limit --
-- enforced in code, see includes/loan_approval_helper.php), transfer
-- centers within their branch, and conduct TVR. No preclose/leave/partial-
-- payment approval -- those stay Branch-Manager-only per the current ask.
IF NOT EXISTS (SELECT 1 FROM Role_Permission WHERE RoleID = @AMRoleID AND PermissionKey = 'approve_loan_proposal')
    INSERT INTO Role_Permission (RoleID, PermissionKey, IsAllowed) VALUES (@AMRoleID, 'approve_loan_proposal', 1);
ELSE
    UPDATE Role_Permission SET IsAllowed = 1 WHERE RoleID = @AMRoleID AND PermissionKey = 'approve_loan_proposal';

IF NOT EXISTS (SELECT 1 FROM Role_Permission WHERE RoleID = @AMRoleID AND PermissionKey = 'transfer_center')
    INSERT INTO Role_Permission (RoleID, PermissionKey, IsAllowed) VALUES (@AMRoleID, 'transfer_center', 1);
ELSE
    UPDATE Role_Permission SET IsAllowed = 1 WHERE RoleID = @AMRoleID AND PermissionKey = 'transfer_center';

IF NOT EXISTS (SELECT 1 FROM Role_Permission WHERE RoleID = @AMRoleID AND PermissionKey = 'conduct_tvr')
    INSERT INTO Role_Permission (RoleID, PermissionKey, IsAllowed) VALUES (@AMRoleID, 'conduct_tvr', 1);
ELSE
    UPDATE Role_Permission SET IsAllowed = 1 WHERE RoleID = @AMRoleID AND PermissionKey = 'conduct_tvr';

-- Mobile menu: AM gets its own Loan Approval / Center Transfer / TVR
-- screens, plus the existing branch collections summary (same screen an
-- officer sees for "FLO Collection" -- for an AM it shows their whole
-- branch instead of just their own book, purely from the API scoping above).
DECLARE @AMDesignationID INT = (SELECT DesignationID FROM Designation_Master WHERE DesignationName = 'AM');

IF @AMDesignationID IS NOT NULL
BEGIN
    DECLARE @AMMenuKeys TABLE (MenuKey VARCHAR(50));
    INSERT INTO @AMMenuKeys VALUES ('am_loan_approval'), ('flo_collection'), ('am_center_transfer'), ('am_tvr');

    UPDATE dmm SET dmm.IsVisible = 1
    FROM Designation_Mobile_Menu dmm JOIN @AMMenuKeys k ON dmm.MenuKey = k.MenuKey
    WHERE dmm.DesignationID = @AMDesignationID;

    INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible)
    SELECT @AMDesignationID, k.MenuKey, 1
    FROM @AMMenuKeys k
    WHERE NOT EXISTS (SELECT 1 FROM Designation_Mobile_Menu dmm WHERE dmm.DesignationID = @AMDesignationID AND dmm.MenuKey = k.MenuKey);
END
