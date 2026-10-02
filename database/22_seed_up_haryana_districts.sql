-- District_Master only had Rajasthan's 5 districts seeded. 302 of the
-- company's 304 live centers are in Uttar Pradesh and Haryana, so the
-- District dropdown in Borrower Enrollment (and anywhere else keyed off
-- State) came back empty for almost every real user. Seeds both states'
-- full official district lists, looked up by StateName (never hardcode IDs).

DECLARE @UP INT = (SELECT StateID FROM State_Master WHERE StateName = 'Uttar Pradesh');
DECLARE @HR INT = (SELECT StateID FROM State_Master WHERE StateName = 'Haryana');

IF @UP IS NOT NULL
BEGIN
    INSERT INTO District_Master (DistrictName, StateID)
    SELECT d.Name, @UP
    FROM (VALUES
        ('Agra'),('Aligarh'),('Prayagraj'),('Ambedkar Nagar'),('Amethi'),('Amroha'),
        ('Auraiya'),('Ayodhya'),('Azamgarh'),('Baghpat'),('Bahraich'),('Ballia'),
        ('Balrampur'),('Banda'),('Barabanki'),('Bareilly'),('Basti'),('Bhadohi'),
        ('Bijnor'),('Budaun'),('Bulandshahr'),('Chandauli'),('Chitrakoot'),('Deoria'),
        ('Etah'),('Etawah'),('Farrukhabad'),('Fatehpur'),('Firozabad'),
        ('Gautam Buddha Nagar'),('Ghaziabad'),('Ghazipur'),('Gonda'),('Gorakhpur'),
        ('Hamirpur'),('Hapur'),('Hardoi'),('Hathras'),('Jalaun'),('Jaunpur'),('Jhansi'),
        ('Kannauj'),('Kanpur Dehat'),('Kanpur Nagar'),('Kasganj'),('Kaushambi'),
        ('Kheri'),('Kushinagar'),('Lalitpur'),('Lucknow'),('Maharajganj'),('Mahoba'),
        ('Mainpuri'),('Mathura'),('Mau'),('Meerut'),('Mirzapur'),('Moradabad'),
        ('Muzaffarnagar'),('Pilibhit'),('Pratapgarh'),('Raebareli'),('Rampur'),
        ('Saharanpur'),('Sambhal'),('Sant Kabir Nagar'),('Shahjahanpur'),('Shamli'),
        ('Shravasti'),('Siddharthnagar'),('Sitapur'),('Sonbhadra'),('Sultanpur'),
        ('Unnao'),('Varanasi')
    ) AS d(Name)
    WHERE NOT EXISTS (
        SELECT 1 FROM District_Master dm WHERE dm.StateID = @UP AND dm.DistrictName = d.Name
    );
END

IF @HR IS NOT NULL
BEGIN
    INSERT INTO District_Master (DistrictName, StateID)
    SELECT d.Name, @HR
    FROM (VALUES
        ('Ambala'),('Bhiwani'),('Charkhi Dadri'),('Faridabad'),('Fatehabad'),
        ('Gurugram'),('Hisar'),('Jhajjar'),('Jind'),('Kaithal'),('Karnal'),
        ('Kurukshetra'),('Mahendragarh'),('Nuh'),('Palwal'),('Panchkula'),
        ('Panipat'),('Rewari'),('Rohtak'),('Sirsa'),('Sonipat'),('Yamunanagar')
    ) AS d(Name)
    WHERE NOT EXISTS (
        SELECT 1 FROM District_Master dm WHERE dm.StateID = @HR AND dm.DistrictName = d.Name
    );
END
