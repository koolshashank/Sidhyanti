<?php
$pageTitle = 'Add Member / Lead';
$crumb = 'Capture a new prospective member';
$active = 'members';
require_once __DIR__ . '/includes/header.php';

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$centers  = $pdo->query("SELECT CenterID, BranchID, CenterName FROM Center ORDER BY CenterName")->fetchAll();
$staff    = $pdo->query("SELECT StaffID, FullName FROM Staff WHERE IsActive=1 ORDER BY FullName")->fetchAll();
$occupations = $pdo->query("SELECT OccupationID, OccupationName FROM Occupation_Master ORDER BY OccupationName")->fetchAll();
$sources = $pdo->query("SELECT SourceID, SourceName FROM Lead_Source_Master ORDER BY SourceName")->fetchAll();
$newLeadStageId = lookup_id($pdo, 'Lead_Stage_Master', 'StageID', 'StageName', 'New Lead');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO Member
        (BranchID, CenterID, FirstName, LastName, FatherHusbandName, Gender, DOB, MaritalStatus,
         MobileNo, OccupationID, Address, VillageCity, Pincode, AadharNo, PANNo,
         LeadSourceID, AssignedToStaffID, CurrentStageID, CreatedBy)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $_POST['branch_id'], $_POST['center_id'] ?: null, $_POST['first_name'], $_POST['last_name'],
        $_POST['father_husband_name'], $_POST['gender'], $_POST['dob'] ?: null, $_POST['marital_status'],
        $_POST['mobile'], $_POST['occupation_id'] ?: null, $_POST['address'], $_POST['village_city'],
        $_POST['pincode'], $_POST['aadhar_no'], $_POST['pan_no'],
        $_POST['lead_source_id'] ?: null, $_POST['assigned_to'] ?: null, $newLeadStageId,
        current_staff()['StaffID']
    ]);
    $newId = $pdo->query("SELECT SCOPE_IDENTITY()")->fetchColumn();
    header('Location: member_view.php?id=' . $newId);
    exit;
}
?>
<div class="card-x" style="max-width:900px;">
  <form method="POST">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Branch</label>
        <select name="branch_id" class="form-select" required>
          <?php foreach ($branches as $b): ?><option value="<?= $b['BranchID'] ?>"><?= htmlspecialchars($b['BranchName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Center</label>
        <select name="center_id" class="form-select">
          <option value="">Select Center</option>
          <?php foreach ($centers as $c): ?><option value="<?= $c['CenterID'] ?>" data-branch="<?= $c['BranchID'] ?>"><?= htmlspecialchars($c['CenterName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Lead Source</label>
        <select name="lead_source_id" class="form-select">
          <?php foreach ($sources as $s): ?><option value="<?= $s['SourceID'] ?>"><?= htmlspecialchars($s['SourceName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4"><label class="form-label">First Name</label><input class="form-control" name="first_name" required></div>
      <div class="col-md-4"><label class="form-label">Last Name</label><input class="form-control" name="last_name"></div>
      <div class="col-md-4"><label class="form-label">Father / Husband Name</label><input class="form-control" name="father_husband_name"></div>
      <div class="col-md-4"><label class="form-label">Mobile</label><input class="form-control" name="mobile" required></div>
      <div class="col-md-4"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="dob"></div>
      <div class="col-md-4">
        <label class="form-label">Gender</label>
        <select name="gender" class="form-select"><option>Female</option><option>Male</option><option>Other</option></select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Marital Status</label>
        <select name="marital_status" class="form-select"><option>Married</option><option>Unmarried</option><option>Widowed</option><option>Divorced</option></select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Occupation</label>
        <select name="occupation_id" class="form-select">
          <?php foreach ($occupations as $o): ?><option value="<?= $o['OccupationID'] ?>"><?= htmlspecialchars($o['OccupationName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4"><label class="form-label">Assign To (Officer)</label>
        <select name="assigned_to" class="form-select">
          <option value="">Unassigned</option>
          <?php foreach ($staff as $s): ?><option value="<?= $s['StaffID'] ?>"><?= htmlspecialchars($s['FullName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-8"><label class="form-label">Address</label><input class="form-control" name="address"></div>
      <div class="col-md-4"><label class="form-label">Village / City</label><input class="form-control" name="village_city"></div>
      <div class="col-md-4"><label class="form-label">Pincode</label><input class="form-control" name="pincode"></div>
      <div class="col-md-4"><label class="form-label">Aadhar No.</label><input class="form-control" name="aadhar_no"></div>
      <div class="col-md-4"><label class="form-label">PAN No.</label><input class="form-control" name="pan_no"></div>
    </div>
    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn-navy">Save Member</button>
      <a href="members.php" class="btn-outline-x">Cancel</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
