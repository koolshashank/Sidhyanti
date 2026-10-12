<?php
$pageTitle = 'Edit Employee';
$crumb = 'Update role, branch, and other details';
$active = 'staff';
require_once __DIR__ . '/includes/header.php';

$staffId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM Staff WHERE StaffID = ?");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();
if (!$staff) {
    echo '<div class="empty-state">Employee not found.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$roles = $pdo->query("SELECT RoleID, RoleName FROM Role_Master ORDER BY RoleName")->fetchAll();
$designations = $pdo->query("SELECT DesignationID, DesignationName, RoleID FROM Designation_Master WHERE DesignationName IS NOT NULL AND DesignationName <> '' ORDER BY DesignationName")->fetchAll();
$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$managersStmt = $pdo->prepare("SELECT StaffID, FullName FROM Staff WHERE IsActive=1 AND StaffID <> ? ORDER BY FullName");
$managersStmt->execute([$staffId]);
$managers = $managersStmt->fetchAll();

$form = $staff; // pre-fill from the current row, then overwrite with POSTed values on validation failure
$error = '';
$unlocked = false;

// Mobile login lockout (api/v1/auth/login.php) blocks a username for 15
// minutes after 5 failed attempts -- this lets an Admin clear it early
// instead of making the staff member wait out the window.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_mobile_login'])) {
    $pdo->prepare("DELETE FROM Login_Log WHERE UsernameTried = ? AND Status = 'Failed' AND LoginAt >= DATEADD(MINUTE, -15, GETDATE())")
        ->execute([$staff['Username']]);
    $unlocked = true;
}

$failedAttempts = $pdo->prepare("SELECT COUNT(*) FROM Login_Log WHERE UsernameTried = ? AND Status = 'Failed' AND LoginAt >= DATEADD(MINUTE, -15, GETDATE())");
$failedAttempts->execute([$staff['Username']]);
$failedAttempts = (int)$failedAttempts->fetchColumn();
$isLockedOut = $failedAttempts >= 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'])) {
    $form = $_POST;
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['password'] ?? '';

    if ($fullName === '' || $username === '' || !($_POST['role_id'] ?? '')) {
        $error = 'Full Name, Username and Role are required.';
    } else {
        try {
            $sql = "
                UPDATE Staff SET
                  FullName = ?, Username = ?, Email = ?, Phone = ?, RoleID = ?, DesignationID = ?,
                  BranchID = ?, ReportingManagerID = ?, DateOfJoining = ?, IsActive = ?,
                  Address = ?, City = ?, Pincode = ?, District = ?, State = ?
                  " . ($newPassword !== '' ? ", PasswordHash = ?" : "") . "
                WHERE StaffID = ?
            ";
            $params = [
                $fullName, $username, trim($_POST['email'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null,
                $_POST['role_id'], $_POST['designation_id'] ?: null,
                $_POST['branch_id'] ?: null, $_POST['reporting_manager_id'] ?: null,
                $_POST['date_of_joining'] ?: null, isset($_POST['is_active']) ? 1 : 0,
                trim($_POST['address'] ?? '') ?: null, trim($_POST['city'] ?? '') ?: null,
                trim($_POST['pincode'] ?? '') ?: null, trim($_POST['district'] ?? '') ?: null,
                trim($_POST['state'] ?? '') ?: null,
            ];
            if ($newPassword !== '') {
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            $params[] = $staffId;

            $pdo->prepare($sql)->execute($params);
            header('Location: staff.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Could not save changes — the username may already be in use.';
        }
    }
}
?>
<div class="card-x" style="max-width:900px;">
  <?php if ($error): ?>
    <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($unlocked): ?>
    <div class="alert alert-success" style="border-radius:10px;margin-bottom:16px;">Mobile login unlocked -- they can try again immediately.</div>
  <?php elseif ($isLockedOut): ?>
    <div class="alert alert-danger" style="border-radius:10px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
      <span>Mobile login is locked -- <?= $failedAttempts ?> failed attempts in the last 15 minutes. They'll be blocked until the window passes, or you unlock it now.</span>
      <form method="POST" style="margin:0;">
        <button type="submit" name="unlock_mobile_login" value="1" class="btn-navy" style="white-space:nowrap;">Unlock Mobile Login</button>
      </form>
    </div>
  <?php endif; ?>
  <form method="POST">
    <div class="form-section-label"><span class="dot">👤</span> Basic Information</div>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Full Name <span class="required-mark">*</span></label><input class="form-control" name="full_name" required value="<?= htmlspecialchars($form['FullName'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Username <span class="required-mark">*</span></label><input class="form-control" name="username" required value="<?= htmlspecialchars($form['Username'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">New Password <span style="font-weight:400;color:var(--text-muted);">(leave blank to keep current)</span></label><input type="password" class="form-control" name="password" placeholder="••••••••"></div>
      <div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($form['Email'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= htmlspecialchars($form['Phone'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Date of Joining</label><input type="date" class="form-control" name="date_of_joining" value="<?= htmlspecialchars(isset($form['DateOfJoining']) ? substr((string)$form['DateOfJoining'], 0, 10) : '') ?>"></div>
    </div>

    <div class="form-section-label"><span class="dot">🏢</span> Role &amp; Assignment</div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Role <span class="required-mark">*</span></label>
        <select class="form-select" name="role_id" id="roleSelect" required>
          <option value="">Select Role</option>
          <?php foreach ($roles as $r): ?><option value="<?= $r['RoleID'] ?>" <?= (int)($form['RoleID'] ?? 0) === (int)$r['RoleID'] ? 'selected' : '' ?>><?= htmlspecialchars($r['RoleName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Designation</label>
        <select class="form-select" name="designation_id" id="designationSelect">
          <option value="">Select Designation</option>
          <?php foreach ($designations as $d): ?><option value="<?= $d['DesignationID'] ?>" data-role="<?= $d['RoleID'] ?>" <?= (int)($form['DesignationID'] ?? 0) === (int)$d['DesignationID'] ? 'selected' : '' ?>><?= htmlspecialchars($d['DesignationName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Branch</label>
        <select class="form-select" name="branch_id">
          <option value="">Unassigned</option>
          <?php foreach ($branches as $b): ?><option value="<?= $b['BranchID'] ?>" <?= (int)($form['BranchID'] ?? 0) === (int)$b['BranchID'] ? 'selected' : '' ?>><?= htmlspecialchars($b['BranchName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Reporting Manager</label>
        <select class="form-select" name="reporting_manager_id">
          <option value="">None</option>
          <?php foreach ($managers as $mgr): ?><option value="<?= $mgr['StaffID'] ?>" <?= (int)($form['ReportingManagerID'] ?? 0) === (int)$mgr['StaffID'] ? 'selected' : '' ?>><?= htmlspecialchars($mgr['FullName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveStaff" <?= !empty($form['IsActive']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="isActiveStaff" style="font-size:13.5px;font-weight:600;">Employee is active</label>
        </div>
      </div>
    </div>

    <div class="form-section-label"><span class="dot">📍</span> Address</div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Pincode</label>
        <div class="d-flex gap-2">
          <input class="form-control" id="pincodeInput" name="pincode" maxlength="6" placeholder="e.g. 226001" value="<?= htmlspecialchars($form['Pincode'] ?? '') ?>">
          <button type="button" class="btn-outline-x" id="pincodeLookupBtn" style="white-space:nowrap;">🔍 Lookup</button>
        </div>
        <div id="pincodeLookupStatus" style="font-size:11.5px;margin-top:5px;min-height:14px;"></div>
      </div>
      <div class="col-md-4">
        <label class="form-label">City / Area</label>
        <input class="form-control" id="cityInput" name="city" value="<?= htmlspecialchars($form['City'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">District</label>
        <input class="form-control" id="districtInput" name="district" value="<?= htmlspecialchars($form['District'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">State</label>
        <input class="form-control" id="stateInput" name="state" value="<?= htmlspecialchars($form['State'] ?? '') ?>">
      </div>
      <div class="col-md-8">
        <label class="form-label">Address</label>
        <input class="form-control" name="address" placeholder="House no., street, landmark..." value="<?= htmlspecialchars($form['Address'] ?? '') ?>">
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn-navy">Save Changes</button>
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
