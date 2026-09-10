-- ============================================================
-- 15_default_reloan_rule.sql
-- Seeds one sensible default Reloan_Eligibility_Rule so the mobile
-- "Reloan" worklist isn't empty by default -- Admin can edit/add more via
-- reloan_eligibility_rules.php (web CRM) at any time.
--
-- Standard microfinance-style default: eligible from the very first
-- completed loan cycle onward, tolerating up to 2 late installments on
-- the prior loan, with a 20% amount increase over the previous loan.
-- ============================================================

DECLARE @SystemStaffID INT = (
    SELECT TOP 1 s.StaffID FROM Staff s
    JOIN Role_Master r ON s.RoleID = r.RoleID
    WHERE r.RoleName = 'Admin' ORDER BY s.StaffID
);

IF NOT EXISTS (SELECT 1 FROM Reloan_Eligibility_Rule WHERE RuleName = 'Standard Reloan')
INSERT INTO Reloan_Eligibility_Rule (RuleName, MinCycleNumber, MaxLatePayments, IncreasePercent, IsActive, CreatedByStaffID)
VALUES ('Standard Reloan', 1, 2, 20, 1, @SystemStaffID);
GO
