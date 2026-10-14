<?php
$pageTitle = 'New Branch';
$crumb = 'Open a new branch office';
$active = 'branches';
require_once __DIR__ . '/includes/header.php';

$states = $pdo->query("SELECT StateID, StateName FROM State_Master ORDER BY StateName")->fetchAll();
$districts = $pdo->query("SELECT DistrictID, DistrictName, StateID FROM District_Master ORDER BY DistrictName")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branchCode = trim($_POST['branch_code'] ?? '');
    $branchName = trim($_POST['branch_name'] ?? '');
    if ($branchCode === '' || $branchName === '') {
        $error = 'Branch Name and Branch Code are required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO Branch
                (BranchCode, BranchName, Address, DistrictID, StateID, Pincode, Phone, Email, DateOpened, IsActive, CreatedAt, CreatedBy)
                VALUES (?,?,?,?,?,?,?,?,?,?,GETDATE(),?)");
            $stmt->execute([
                $branchCode, $branchName, $_POST['address'] ?: null,
                $_POST['district_id'] ?: null, $_POST['state_id'] ?: null, $_POST['pincode'] ?: null,
                $_POST['phone'] ?: null, $_POST['email'] ?: null, $_POST['date_opened'] ?: date('Y-m-d'),
                isset($_POST['is_active']) ? 1 : 0, current_staff()['StaffID'],
            ]);
            header('Location: branches.php?created=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Could not create branch — the branch code may already be in use.';
        }
    }
}
?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card-x">
      <div class="form-card-head">
        <div class="form-card-icon"><i class="bi bi-building"></i></div>
        <div>
          <h5>Branch Details</h5>
          <p>Fill in the information below to open a new branch office.</p>
        </div>
      </div>

      <?php if ($error): ?><div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:18px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="POST" id="branchForm">
        <div class="form-section-label"><span class="dot"><i class="bi bi-tag-fill"></i></span> Basic Information</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Branch Name <span class="required-mark">*</span></label>
            <input class="form-control" name="branch_name" id="f_name" required value="<?= htmlspecialchars($_POST['branch_name'] ?? '') ?>" placeholder="e.g. Kashi Vidyapeeth">
          </div>
          <div class="col-md-6">
            <label class="form-label">Branch Code <span class="required-mark">*</span></label>
            <input class="form-control" name="branch_code" id="f_code" required value="<?= htmlspecialchars($_POST['branch_code'] ?? '') ?>" placeholder="e.g. SIDH-6">
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <input class="form-control" name="address" id="f_address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" placeholder="Street, locality">
          </div>
        </div>

        <div class="form-section-label"><span class="dot"><i class="bi bi-geo-alt-fill"></i></span> Location</div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">State</label>
            <select class="form-select" name="state_id" id="stateSelect_add" onchange="filterDistricts('stateSelect_add','districtSelect_add')">
              <option value="">Select State</option>
              <?php foreach ($states as $s): ?><option value="<?= $s['StateID'] ?>"><?= htmlspecialchars($s['StateName']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">District</label>
            <select class="form-select" name="district_id" id="districtSelect_add">
              <option value="">Select District</option>
              <?php foreach ($districts as $d): ?><option value="<?= $d['DistrictID'] ?>" data-state="<?= $d['StateID'] ?>"><?= htmlspecialchars($d['DistrictName']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Pincode</label>
            <input class="form-control" name="pincode" id="f_pincode" value="<?= htmlspecialchars($_POST['pincode'] ?? '') ?>" placeholder="221001">
          </div>
        </div>

        <div class="form-section-label"><span class="dot"><i class="bi bi-telephone"></i></span> Contact &amp; Status</div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input class="form-control" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="10-digit number">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="branch@sidhyanti.org">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date Opened</label>
            <input type="date" class="form-control" name="date_opened" value="<?= htmlspecialchars($_POST['date_opened'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="f_active" checked>
              <label class="form-check-label" for="f_active" style="font-size:13.5px;font-weight:600;">Branch is active</label>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-navy"><i class="bi bi-plus-lg"></i> Create Branch</button>
          <a href="branches.php" class="btn-outline-x">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card-x" style="position:sticky;top:88px;">
      <div class="section-title">Live Preview</div>
      <div class="preview-card-frame">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div style="font-weight:700;font-size:16px;" id="prevName">New Branch</div>
            <div style="font-size:12px;color:var(--text-muted);" id="prevCode">—</div>
          </div>
          <span class="badge badge-success" id="prevStatus">Active</span>
        </div>
        <div style="font-size:13px;color:var(--text-muted);margin:10px 0;" id="prevAddress">Address will appear here</div>
        <div class="row text-center" style="margin-top:14px;">
          <div class="col-4"><div style="font-weight:700;">0</div><div style="font-size:11px;color:var(--text-muted);">Centers</div></div>
          <div class="col-4"><div style="font-weight:700;">0</div><div style="font-size:11px;color:var(--text-muted);">Members</div></div>
          <div class="col-4"><div style="font-weight:700;">0</div><div style="font-size:11px;color:var(--text-muted);">Active Loans</div></div>
        </div>
      </div>
      <div class="preview-note">This is exactly how the branch will appear on the Branches page.</div>
    </div>
  </div>
</div>

<script>
function filterDistricts(stateId, districtId) {
  const stateVal = document.getElementById(stateId).value;
  const districtSelect = document.getElementById(districtId);
  [...districtSelect.options].forEach(opt => {
    if (!opt.value) return;
    opt.hidden = stateVal !== '' && opt.dataset.state !== stateVal;
  });
  if (districtSelect.selectedOptions[0] && districtSelect.selectedOptions[0].hidden) districtSelect.value = '';
}
filterDistricts('stateSelect_add', 'districtSelect_add');

const prevName = document.getElementById('prevName');
const prevCode = document.getElementById('prevCode');
const prevAddress = document.getElementById('prevAddress');
const prevStatus = document.getElementById('prevStatus');
const nameInput = document.getElementById('f_name');
const codeInput = document.getElementById('f_code');
const addressInput = document.getElementById('f_address');
const pincodeInput = document.getElementById('f_pincode');
const activeInput = document.getElementById('f_active');

function updatePreview() {
  prevName.textContent = nameInput.value.trim() || 'New Branch';
  prevCode.textContent = codeInput.value.trim() || '—';
  const parts = [addressInput.value.trim(), pincodeInput.value.trim()].filter(Boolean);
  prevAddress.textContent = parts.length ? parts.join(' - ') : 'Address will appear here';
  if (activeInput.checked) { prevStatus.textContent = 'Active'; prevStatus.className = 'badge badge-success'; }
  else { prevStatus.textContent = 'Inactive'; prevStatus.className = 'badge badge-danger'; }
}
[nameInput, codeInput, addressInput, pincodeInput].forEach(el => el.addEventListener('input', updatePreview));
activeInput.addEventListener('change', updatePreview);
updatePreview();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
