-- New "Dormant Client" mobile menu key -- grant it to every designation that
-- already has "client_details" visible, so it shows up immediately without
-- Admin needing to separately configure it on mobile_menu_access.php first.
INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible)
SELECT DesignationID, 'dormant_clients', 1
FROM Designation_Mobile_Menu dmm
WHERE MenuKey = 'client_details' AND IsVisible = 1
  AND NOT EXISTS (
    SELECT 1 FROM Designation_Mobile_Menu x
    WHERE x.DesignationID = dmm.DesignationID AND x.MenuKey = 'dormant_clients'
  );
