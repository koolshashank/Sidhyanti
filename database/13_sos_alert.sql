-- ============================================================
-- 13_sos_alert.sql
-- Logs every SOS button press from the mobile app (who, where, when).
-- The actual alerting is a Notification row per recipient (staff's own
-- branch's Branch Manager(s) + every Admin), inserted by
-- api/v1/sos/trigger.php at the same time as this row.
-- Run this once in SSMS against the SidhyantiCRM database.
-- ============================================================

IF OBJECT_ID('dbo.SOS_Alert', 'U') IS NULL
CREATE TABLE dbo.SOS_Alert (
    SOSAlertID   INT IDENTITY(1,1) PRIMARY KEY,
    StaffID      INT NOT NULL,
    Latitude     DECIMAL(10,7) NULL,
    Longitude    DECIMAL(10,7) NULL,
    TriggeredAt  DATETIME NOT NULL CONSTRAINT DF_SOSAlert_TriggeredAt DEFAULT (GETDATE()),
    CONSTRAINT FK_SOSAlert_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID)
);
GO
