<?php
$pageTitle = 'Add Employee';
$crumb = 'Onboard a new staff member';
$active = 'staff';
require_once __DIR__ . '/includes/header.php';

$roles = $pdo->query("SELECT RoleID, RoleName FROM Role_Master ORDER BY RoleName")->fetchAll();
$designations = $pdo->query("SELECT DesignationID, DesignationName, RoleID FROM Designation_Master WHERE DesignationName IS NOT NULL AND DesignationName <> '' ORDER BY DesignationName")->fetchAll();
$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$managers = $pdo->query("SELECT StaffID, FullName FROM Staff WHERE IsActive=1 ORDER BY FullName")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($fullName === '' || $username === '' || $password === '' || !($_POST['role_id'] ?? '')) {
        $error = 'Full Name, Username, Password and Role are required.';
    } else {
        try {
            // EmployeeCode follows the existing EMP<StaffID> convention — the final
            // value isn't known until after the identity insert, so it's patched in
            // right after with a UNIQUE-safe temporary placeholder in between.
            //
            // SCOPE_IDENTITY() as a separate query() call comes back NULL here (each
            // PDO call is its own batch/scope against this driver), so the new
            // StaffID is captured via OUTPUT ... INTO a table variable in the same
            // batch instead — OUTPUT without INTO is rejected because Staff has an
            // AFTER trigger (TR_Audit_Staff).
            $tempCode = 'TMP-' . uniqid();
            $stmt = $pdo->prepare("
                DECLARE @Inserted TABLE (StaffID INT);
                INSERT INTO Staff
                  (EmployeeCode, FullName, Username, PasswordHash, Email, Phone, RoleID, DesignationID,
                   BranchID, ReportingManagerID, DateOfJoining, IsActive, Address, City, Pincode, District, State, CreatedBy)
                  OUTPUT INSERTED.StaffID INTO @Inserted
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?);
                SELECT StaffID FROM @Inserted;
            ");
            $stmt->execute([
                $tempCode, $fullName, $username, password_hash($password, PASSWORD_DEFAULT),
                trim($_POST['email'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null,
                $_POST['role_id'], $_POST['designation_id'] ?: null,
                $_POST['branch_id'] ?: null, $_POST['reporting_manager_id'] ?: null,
                $_POST['date_of_joining'] ?: null, isset($_POST['is_active']) ? 1 : 0,
                trim($_POST['address'] ?? '') ?: null, trim($_POST['city'] ?? '') ?: null,
                trim($_POST['pincode'] ?? '') ?: null, trim($_POST['district'] ?? '') ?: null,
                trim($_POST['state'] ?? '') ?: null, current_staff()['StaffID'],
            ]);
            $newId = null;
            do {
                try { $row = $stmt->fetch(PDO::FETCH_NUM); if ($row) { $newId = $row[0]; } }
                catch (PDOException $e) { /* rowset with no fields (DECLARE/INSERT) — skip */ }
            } while ($stmt->nextRowset());

            $pdo->prepare("UPDATE Staff SET EmployeeCode = ? WHERE StaffID = ?")->execute(['EMP' . $newId, $newId]);
            header('Location: staff.php?created=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Could not create employee — the username may already be in use.';
        }
    }
}
?>
<div class="card-x" style="max-width:900px;">
  <?php if ($error): ?>
    <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-section-label"><span class="dot">👤</span> Basic Information</div>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Full Name <span class="required-mark">*</span></label><input class="form-control" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Username <span class="required-mark">*</span></label><input class="form-control" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Password <span class="required-mark">*</span></label><input type="password" class="form-control" name="password" required></div>
      <div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Date of Joining</label><input type="date" class="form-control" name="date_of_joining" value="<?= htmlspecialchars($_POST['date_of_joining'] ?? date('Y-m-d')) ?>"></div>
    </div>

    <div class="form-section-label"><span class="dot">🏢</span> Role &amp; Assignment</div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Role <span class="required-mark">*</span></label>
        <select class="form-select" name="role_id" id="roleSelect" required>
          <option value="">Select Role</option>
          <?php foreach ($roles as $r): ?><option value="<?= $r['RoleID'] ?>"><?= htmlspecialchars($r['RoleName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Designation</label>
        <select class="form-select" name="designation_id" id="designationSelect">
          <option value="">Select Designation</option>
          <?php foreach ($designations as $d): ?><option value="<?= $d['DesignationID'] ?>" data-role="<?= $d['RoleID'] ?>"><?= htmlspecialchars($d['DesignationName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Branch</label>
        <select class="form-select" name="branch_id">
          <option value="">Unassigned</option>
          <?php foreach ($branches as $b): ?><option value="<?= $b['BranchID'] ?>"><?= htmlspecialchars($b['BranchName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Reporting Manager</label>
        <select class="form-select" name="reporting_manager_id">
          <option value="">None</option>
          <?php foreach ($managers as $mgr): ?><option value="<?= $mgr['StaffID'] ?>"><?= htmlspecialchars($mgr['FullName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveStaff" checked>
          <label class="form-check-label" for="isActiveStaff" style="font-size:13.5px;font-weight:600;">Employee is active</label>
        </div>
      </div>
    </div>

    <div class="form-section-label"><span class="dot">📍</span> Address</div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Pincode</label>
        <div class="d-flex gap-2">
          <input class="form-control" id="pincodeInput" name="pincode" maxlength="6" placeholder="e.g. 226001" value="<?= htmlspecialchars($_POST['pincode'] ?? '') ?>">
          <button type="button" class="btn-outline-x" id="pincodeLookupBtn" style="white-space:nowrap;">🔍 Lookup</button>
        </div>
        <div id="pincodeLookupStatus" style="font-size:11.5px;margin-top:5px;min-height:14px;"></div>
      </div>
      <div class="col-md-4">
        <label class="form-label">City / Area <span style="font-weight:400;color:var(--text-muted);">(auto-filled from pincode)</span></label>
        <input class="form-control" id="cityInput" name="city" readonly style="background:#f4f6f9;" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">District <span style="font-weight:400;color:var(--text-muted);">(auto-filled from pincode)</span></label>
        <input class="form-control" id="districtInput" name="district" readonly style="background:#f4f6f9;" value="<?= htmlspecialchars($_POST['district'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">State <span style="font-weight:400;color:var(--text-muted);">(auto-filled from pincode)</span></label>
        <input class="form-control" id="stateInput" name="state" readonly style="background:#f4f6f9;" value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
      </div>
      <div class="col-md-8">
        <label class="form-label">Address</label>
        <input class="form-control" name="address" placeholder="House no., street, landmark..." value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn-navy">Save Employee</button>
      <a href="staff.php" class="btn-outline-x">Cancel</a>
    </div>
  </form>
</div>

<script>
function filterDesignations() {
  const roleVal = document.getElementById('roleSelect').value;
  const designationSelect = document.getElementById('designationSelect');
  [...designationSelect.options].forEach(opt => {
    if (!opt.value) return;
    opt.hidden = roleVal !== '' && opt.dataset.role !== roleVal;
  });
  if (designationSelect.selectedOptions[0] && designationSelect.selectedOptions[0].hidden) designationSelect.value = '';
}
document.getElementById('roleSelect').addEventListener('change', filterDesignations);
filterDesignations();

(function () {
  const pincodeInput = document.getElementById('pincodeInput');
  const cityInput = document.getElementById('cityInput');
  const districtInput = document.getElementById('districtInput');
  const stateInput = document.getElementById('stateInput');
  const statusEl = document.getElementById('pincodeLookupStatus');
  const lookupBtn = document.getElementById('pincodeLookupBtn');

  async function lookupPincode() {
    const code = pincodeInput.value.trim();
    if (!/^\d{6}$/.test(code)) {
      statusEl.textContent = code ? 'Enter a valid 6-digit pincode' : '';
      statusEl.style.color = 'var(--danger)';
      return;
    }
    statusEl.textContent = 'Looking up…';
    statusEl.style.color = 'var(--text-muted)';
    try {
      // India Post's free public pincode lookup — no API key required.
      const res = await fetch('https://api.postalpincode.in/pincode/' + code);
      if (!res.ok) throw new Error('lookup failed');
      const data = await res.json();
      const po = data && data[0] && data[0].Status === 'Success' ? data[0].PostOffice[0] : null;
      if (!po) throw new Error('not found');
      cityInput.value = po.Name || po.Block || '';
      districtInput.value = po.District || '';
      stateInput.value = po.State || '';
      statusEl.textContent = '✓ ' + po.District + ', ' + po.State;
      statusEl.style.color = 'var(--teal)';
    } catch (e) {
      statusEl.textContent = 'Pincode not found — please enter address manually';
      statusEl.style.color = 'var(--danger)';
    }
  }

  pincodeInput.addEventListener('blur', lookupPincode);
  pincodeInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); lookupPincode(); }
  });
  lookupBtn.addEventListener('click', lookupPincode);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
