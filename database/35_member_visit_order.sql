-- Lets a Branch Manager/Field Officer set the order clients should be
-- visited in within their center, instead of the fixed alphabetical
-- listing. NULL (the default) sorts last, after everyone with an explicit
-- order -- so setting an order for a few clients doesn't hide the rest.
IF COL_LENGTH('dbo.Member', 'VisitOrder') IS NULL
BEGIN
    ALTER TABLE dbo.Member ADD VisitOrder INT NULL;
END
GO
