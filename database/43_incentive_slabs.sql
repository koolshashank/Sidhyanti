-- Incentive/Commission slabs: Admin-configurable, slab-based on monthly
-- target-achievement % -- separate slab sets for Disbursement and
-- Collection (Collection's "target" is always 100% of what's due, same
-- definition my_performance.php / the mobile Target vs Achievement tracker
-- already use). A staff member's incentive for a metric is whichever slab's
-- [MinAchievementPercent, MaxAchievementPercent) their achievement % falls
-- into -- see includes/incentive_helper.php.
IF OBJECT_ID('dbo.Incentive_Slab', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.Incentive_Slab (
        SlabID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        MetricType VARCHAR(20) NOT NULL CHECK (MetricType IN ('Disbursement', 'Collection')),
        MinAchievementPercent DECIMAL(6,2) NOT NULL,
        MaxAchievementPercent DECIMAL(6,2) NULL, -- NULL = no upper bound
        IncentiveAmount DECIMAL(18,2) NOT NULL,
        IsActive BIT NOT NULL DEFAULT 1,
        UpdatedByStaffID INT NULL,
        UpdatedAt DATETIME NOT NULL DEFAULT GETDATE()
    );
    CREATE INDEX IX_IncentiveSlab_Metric ON dbo.Incentive_Slab(MetricType, IsActive);
END
GO
