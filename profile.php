<?php
$pageTitle = 'My Profile';
$crumb = 'Manage your own account details';
$active = 'profile';
require_once __DIR__ . '/includes/header.php';

$staffId = (int)$_SESSION['staff_id'];
$stmt = $pdo->prepare("SELECT * FROM Staff WHERE StaffID = ?");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

$error = '';
$success = '';
$form = $_POST['form'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form === 'details') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '') ?: null;
    $phone = trim($_POST['phone'] ?? '') ?: null;
    $address = trim($_POST['address'] ?? '') ?: null;
    $city = trim($_POST['city'] ?? '') ?: null;
    $pincode = trim($_POST['pincode'] ?? '') ?: null;
    $district = trim($_POST['district'] ?? '') ?: null;
    $state = trim($_POST['state'] ?? '') ?: null;

    if ($fullName === '') {
        $error = 'Full Name is required.';
    }

    $photoPath = $staff['Photo'];
    if ($error === '' && !empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            $error = 'Profile photo must be a JPG or PNG image.';
        } elseif ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Photo upload failed. Please try again.';
        } else {
            $relativeDir = "Document_images/Staff_Profile/$staffId";
            $uploadDir = __DIR__ . '/' . $relativeDir . '/';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $error = 'Could not prepare the upload folder. Please try again.';
            } else {
                $filename = 'profile_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                    $photoPath = "$relativeDir/$filename";
                } else {
                    $error = 'Could not save the uploaded photo.';
                }
            }
        }
    }

    if ($error === '') {
        $pdo->prepare("
            UPDATE Staff SET FullName=?, Email=?, Phone=?, Address=?, City=?, Pincode=?, District=?, State=?, Photo=?, UpdatedAt=GETDATE(), UpdatedBy=?
            WHERE StaffID=?
        ")->execute([$fullName, $email, $phone, $address, $city, $pincode, $district, $state, $photoPath, $staffId, $staffId]);

        $stmt->execute([$staffId]);
        $staff = $stmt->fetch();
        $_SESSION['staff'] = array_merge($_SESSION['staff'], $staff);
        $success = 'Profile updated successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $form === 'password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($current, $staff['PasswordHash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New password and confirmation do not match.';
    } else {
        $pdo->prepare("UPDATE Staff SET PasswordHash=?, UpdatedAt=GETDATE(), UpdatedBy=? WHERE StaffID=?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $staffId, $staffId]);
        $success = 'Password changed successfully.';
    }
}
?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card-x" style="text-align:center;">
      <?php if (!empty($staff['Photo'])): ?>
        <img src="<?= htmlspecialchars($staff['Photo']) ?>" alt="Profile photo" style="width:120px;height:120px;border-radius:50%;object-fit:cover;margin:0 auto 14px;display:block;">
      <?php else: ?>
        <div class="avatar" style="width:120px;height:120px;font-size:34px;margin:0 auto 14px;background:var(--navy);">
          <?= initials($staff['FullName'] ?? '?') ?>
        </div>
      <?php endif; ?>
      <h5 style="font-weight:800;margin-bottom:2px;"><?= htmlspecialchars($staff['FullName']) ?></h5>
      <div style="color:var(--text-muted);font-size:13.5px;margin-bottom:4px;"><?= htmlspecialchars($staff['Username']) ?></div>
      <div class="tag-row" style="justify-content:center;">
        <span class="badge badge-secondary"><?= htmlspecialchars($u['DesignationName'] ?? $u['RoleName'] ?? '-') ?></span>
      </div>
    </div>

    <div class="card-x mt-3">
      <div class="form-section-label"><span class="dot">🔒</span> Change Password</div>
      <?php if ($error && $form === 'password'): ?>
        <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success && $form === 'password'): ?>
        <div class="alert alert-success" style="border-radius:10px;margin-bottom:16px;"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="form" value="password">
        <label class="form-label">Current Password</label>
        <input type="password" class="form-control mb-3" name="current_password" required>
        <label class="form-label">New Password</label>
        <input type="password" class="form-control mb-3" name="new_password" required minlength="8">
        <label class="form-label">Confirm New Password</label>
        <input type="password" class="form-control mb-3" name="confirm_password" required minlength="8">
        <button type="submit" class="btn-navy">Change Password</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card-x">
      <div class="form-section-label"><span class="dot">👤</span> Profile Details</div>
      <?php if ($error && $form === 'details'): ?>
        <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success && $form === 'details'): ?>
        <div class="alert alert-success" style="border-radius:10px;margin-bottom:16px;"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="form" value="details">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Full Name <span class="required-mark">*</span></label><input class="form-control" name="full_name" required value="<?= htmlspecialchars($staff['FullName'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Profile Photo</label><input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png"></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($staff['Email'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= htmlspecialchars($staff['Phone'] ?? '') ?>"></div>
        </div>

        <div class="form-section-label mt-3"><span class="dot">📍</span> Address</div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Pincode</label>
            <div class="d-flex gap-2">
              <input class="form-control" id="pincodeInput" name="pincode" maxlength="6" value="<?= htmlspecialchars($staff['Pincode'] ?? '') ?>">
              <button type="button" class="btn-outline-x" id="pincodeLookupBtn" style="white-space:nowrap;">🔍 Lookup</button>
            </div>
            <div id="pincodeLookupStatus" style="font-size:11.5px;margin-top:5px;min-height:14px;"></div>
          </div>
          <div class="col-md-4"><label class="form-label">City / Area</label><input class="form-control" id="cityInput" name="city" value="<?= htmlspecialchars($staff['City'] ?? '') ?>"></div>
          <div class="col-md-4"><label class="form-label">District</label><input class="form-control" id="districtInput" name="district" value="<?= htmlspecialchars($staff['District'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">State</label><input class="form-control" id="stateInput" name="state" value="<?= htmlspecialchars($staff['State'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= htmlspecialchars($staff['Address'] ?? '') ?>"></div>
        </div>

        <div class="mt-4">
          <button type="submit" class="btn-navy">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
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
