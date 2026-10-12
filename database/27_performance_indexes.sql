-- ============================================================
-- 27_performance_indexes.sql
-- SQL Server does NOT auto-create an index on a foreign key column (unlike
-- MySQL) -- a sweep of sys.foreign_keys found 102 FK columns on this
-- database with no supporting index at all, meaning every WHERE/JOIN on
-- them is a full table scan. Most of those are cold (e.g. Loan_Scheme.
-- CreatedBy, touched maybe once per scheme edit) and not worth indexing,
-- but the ones below are hit on nearly every page load / API call in the
-- mobile app and web CRM -- this is the single highest-leverage change for
-- "fast in the field on a weak connection", since it's the server doing
-- the slow part, not the network.
--
-- Run this once in SSMS against the SidhyantiCRM database. Safe to re-run
-- (IF NOT EXISTS guards).
-- ============================================================

-- Notification bell + list -- queried on literally every authenticated web
-- page load (includes/header.php) and every mobile screen's badge count.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Notification_StaffID_IsRead' AND object_id = OBJECT_ID('dbo.Notification'))
    CREATE INDEX IX_Notification_StaffID_IsRead ON dbo.Notification(StaffID, IsRead);
GO

-- Branch-scoping: every non-Admin, non-Officer caller's queries filter by
-- "which branch is this staff member in" (api/includes/scope_helper.php).
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Staff_BranchID' AND object_id = OBJECT_ID('dbo.Staff'))
    CREATE INDEX IX_Staff_BranchID ON dbo.Staff(BranchID);
GO

-- Today's/this-installment's collection-sheet row is looked up per
-- Repayment_Schedule row on every loan detail view (web loan_view.php and
-- mobile api/v1/loans/detail.php both do this as a correlated subquery).
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DailyCollectionSheet_ScheduleID' AND object_id = OBJECT_ID('dbo.Daily_Collection_Sheet'))
    CREATE INDEX IX_DailyCollectionSheet_ScheduleID ON dbo.Daily_Collection_Sheet(ScheduleID);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DailyCollectionSheet_BranchID' AND object_id = OBJECT_ID('dbo.Daily_Collection_Sheet'))
    CREATE INDEX IX_DailyCollectionSheet_BranchID ON dbo.Daily_Collection_Sheet(BranchID);
GO

-- Loan.CenterID -- collection worklists and center-scoped loan lists filter
-- on this constantly (FLO Collection, DCR, center detail screens).
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Loan_CenterID' AND object_id = OBJECT_ID('dbo.Loan'))
    CREATE INDEX IX_Loan_CenterID ON dbo.Loan(CenterID);
GO

-- Approval queues (BM Approval screen, loan_approvals.php) filter by both.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_PartialPaymentApproval_LoanID' AND object_id = OBJECT_ID('dbo.Partial_Payment_Approval'))
    CREATE INDEX IX_PartialPaymentApproval_LoanID ON dbo.Partial_Payment_Approval(LoanID);
GO

-- Member detail / death-entry review screens look this up by MemberID.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DeathEntry_MemberID' AND object_id = OBJECT_ID('dbo.Death_Entry'))
    CREATE INDEX IX_DeathEntry_MemberID ON dbo.Death_Entry(MemberID);
GO

-- Center meeting attendance is checked per-loan on the collection screen.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_CenterMeetingAttendance_LoanID' AND object_id = OBJECT_ID('dbo.Center_Meeting_Attendance'))
    CREATE INDEX IX_CenterMeetingAttendance_LoanID ON dbo.Center_Meeting_Attendance(LoanID);
GO

-- Audit trigger inserts (TR_Audit_*) write here on every tracked
-- insert/update/delete -- Audit_Log.sql queries by StaffID far less often
-- than it's written to, but when it IS read (audit_log.php) it's always
-- filtered this way.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_AuditLog_ChangedByStaffID' AND object_id = OBJECT_ID('dbo.Audit_Log'))
    CREATE INDEX IX_AuditLog_ChangedByStaffID ON dbo.Audit_Log(ChangedByStaffID);
GO
