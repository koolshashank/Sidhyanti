-- ============================================================
-- 8_api_refresh_token.sql
-- Stores hashed refresh tokens issued by the mobile REST API
-- (POST /api/v1/auth/login, /api/v1/auth/refresh, /api/v1/auth/logout).
-- Run this once in SSMS against the SidhyantiCRM database.
-- ============================================================

IF OBJECT_ID('dbo.Api_Refresh_Token', 'U') IS NOT NULL
    DROP TABLE dbo.Api_Refresh_Token;
GO

CREATE TABLE dbo.Api_Refresh_Token (
    TokenID      INT IDENTITY(1,1) PRIMARY KEY,
    StaffID      INT           NOT NULL,
    TokenHash    VARCHAR(64)   NOT NULL,   -- SHA-256 hex digest of the opaque refresh token (raw token is never stored)
    DeviceInfo   NVARCHAR(255) NULL,       -- e.g. device model / app version sent by the mobile client
    IssuedAt     DATETIME      NOT NULL CONSTRAINT DF_ApiRefreshToken_IssuedAt DEFAULT (GETDATE()),
    ExpiresAt    DATETIME      NOT NULL,
    RevokedAt    DATETIME      NULL,
    IPAddress    VARCHAR(45)   NULL,       -- big enough for IPv6

    CONSTRAINT FK_ApiRefreshToken_Staff FOREIGN KEY (StaffID) REFERENCES dbo.Staff(StaffID)
);
GO

CREATE INDEX IX_ApiRefreshToken_TokenHash ON dbo.Api_Refresh_Token(TokenHash);
CREATE INDEX IX_ApiRefreshToken_StaffID   ON dbo.Api_Refresh_Token(StaffID);
GO
