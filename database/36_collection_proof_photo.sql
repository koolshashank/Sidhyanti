-- Optional photo proof captured at the moment of collection (e.g. for a
-- disputed-payment case) -- attached after the fact via
-- api/v1/collections/upload_proof.php once the Collection row already
-- exists, since the photo capture happens as a follow-up step in the app,
-- not inside the same request as the payment itself.
IF COL_LENGTH('dbo.Collection', 'ProofPhotoPath') IS NULL
BEGIN
    ALTER TABLE dbo.Collection ADD ProofPhotoPath NVARCHAR(500) NULL;
END
GO
