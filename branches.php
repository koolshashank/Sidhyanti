<?php
$pageTitle = 'Branches';
$crumb = 'All Sidhyanti branch offices';
$active = 'branches';
require_once __DIR__ . '/includes/header.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['branch_id'])) {
    $branchCode = trim($_POST['branch_code'] ?? '');
    $branchName = trim($_POST['branch_name'] ?? '');
    if ($branchCode === '' || $branchName === '') {
        $error = 'Branch Name and Branch Code are required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE Branch SET
                BranchCode=?, BranchName=?, Address=?, DistrictID=?, StateID=?, Pincode=?, Phone=?, Email=?,
                DateOpened=?, IsActive=?, UpdatedAt=GETDATE(), UpdatedBy=?
                WHERE BranchID=?");
            $stmt->execute([
                $branchCode, $branchName, $_POST['address'] ?: null,
                $_POST['district_id'] ?: null, $_POST['state_id'] ?: null, $_POST['pincode'] ?: null,
                $_POST['phone'] ?: null, $_POST['email'] ?: null, $_POST['date_opened'] ?: null,
                isset($_POST['is_active']) ? 1 : 0, current_staff()['StaffID'], $_POST['branch_id'],
            ]);
            header('Location: branches.php?updated=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Could not update branch — the branch code may already be in use.';
        }
    }
}

$states = $pdo->query("SELECT StateID, StateName FROM State_Master ORDER BY StateName")->fetchAll();
$districts = $pdo->query("SELECT DistrictID, DistrictName, StateID FROM District_Master ORDER BY DistrictName")->fetchAll();

$branches = $pdo->query("
  SELECT b.BranchID, b.BranchCode, b.BranchName, b.Address, b.DistrictID, b.StateID, b.Pincode, b.Phone, b.Email, b.DateOpened, b.IsActive,
         s.TotalMembers, s.ActiveCenters, s.ActiveLoans, s.OutstandingPortfolio, s.LifetimeDisbursed
  FROM Branch b
  JOIN vw_Branch_Portfolio_Summary s ON s.BranchID = b.BranchID
  WHERE b.BranchName NOT LIKE '%HEAD OFFICE%'
  ORDER BY b.BranchName
")->fetchAll();

$viewMode = ($_GET['view'] ?? 'card') === 'table' ? 'table' : 'card';
?>
<style>
.view-toggle { display: inline-flex; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 3px; gap: 2px; }
.view-toggle-btn { display: inline-block; padding: 7px 14px; font-size: 13px; font-weight: 600; color: var(--text-muted); border-radius: 7px; }
.view-toggle-btn.active { background: var(--card-bg); color: var(--navy); box-shadow: var(--shadow-sm); }
.view-toggle-btn:hover { color: var(--navy); }
</style>
<?php if ($error): ?>
  <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div>
<?php elseif (isset($_GET['created'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Branch created successfully.</div>
<?php elseif (isset($_GET['updated'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Branch updated successfully.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div class="view-toggle">
    <a href="?view=card" class="view-toggle-btn <?= $viewMode==='card'?'active':'' ?>"><i class="bi bi-grid-3x3-gap-fill"></i> Card</a>
    <a href="?view=table" class="view-toggle-btn <?= $viewMode==='table'?'active':'' ?>"><i class="bi bi-table"></i> Table</a>
  </div>
  <a href="branch_add.php" class="btn-navy">+ New Branch</a>
</div>

<?php if ($viewMode === 'table'): ?>
<div class="card-x">
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Branch</th><th>Address</th><th>Status</th><th>Centers</th><th>Members</th><th>Active Loans</th><th>Portfolio</th><th></th></tr>
      <?php foreach ($branches as $b): ?>
      <tr>
        <td><strong><?= htmlspecialchars($b['BranchName']) ?></strong><br><span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($b['BranchCode']) ?></span></td>
        <td><?= htmlspecialchars($b['Address'].' - '.$b['Pincode']) ?></td>
        <td><span class="badge badge-<?= $b['IsActive']?'success':'danger' ?>"><?= $b['IsActive']?'Active':'Inactive' ?></span></td>
        <td><?= $b['ActiveCenters'] ?></td>
        <td><?= $b['TotalMembers'] ?></td>
        <td><?= $b['ActiveLoans'] ?></td>
        <td><?= money($b['OutstandingPortfolio']) ?></td>
        <td><button type="button" class="btn-outline-x" style="font-size:12px;padding:5px 12px;white-space:nowrap;" data-bs-toggle="modal" data-bs-target="#editBranch<?= $b['BranchID'] ?>">Edit</button></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($branches as $b): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card-x">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div style="font-weight:700;font-size:16px;"><?= htmlspecialchars($b['BranchName']) ?></div>
          <div style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($b['BranchCode']) ?></div>
        </div>
        <span class="badge badge-<?= $b['IsActive']?'success':'danger' ?>"><?= $b['IsActive']?'Active':'Inactive' ?></span>
      </div>
      <div style="font-size:13px;color:var(--text-muted);margin:10px 0;"><?= htmlspecialchars($b['Address'].' - '.$b['Pincode']) ?></div>
      <div class="row text-center" style="margin-top:14px;">
        <div class="col-3"><div style="font-weight:700;"><?= $b['ActiveCenters'] ?></div><div style="font-size:11px;color:var(--text-muted);">Centers</div></div>
        <div class="col-3"><div style="font-weight:700;"><?= $b['TotalMembers'] ?></div><div style="font-size:11px;color:var(--text-muted);">Members</div></div>
        <div class="col-3"><div style="font-weight:700;"><?= $b['ActiveLoans'] ?></div><div style="font-size:11px;color:var(--text-muted);">Active Loans</div></div>
        <div class="col-3"><div style="font-weight:700;font-size:12px;"><?= money($b['OutstandingPortfolio']) ?></div><div style="font-size:11px;color:var(--text-muted);">Portfolio</div></div>
      </div>
      <div class="d-flex justify-content-end mt-3">
        <button type="button" class="btn-outline-x" style="font-size:12px;padding:5px 12px;" data-bs-toggle="modal" data-bs-target="#editBranch<?= $b['BranchID'] ?>">Edit</button>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php foreach ($branches as $b): ?>
  <div class="modal fade" id="editBranch<?= $b['BranchID'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="branch_id" value="<?= $b['BranchID'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Edit Branch — <?= htmlspecialchars($b['BranchName']) ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="form-section-label"><span class="dot"><i class="bi bi-tag-fill"></i></span> Basic Information</div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Branch Name <span class="required-mark">*</span></label>
                <input class="form-control" name="branch_name" required value="<?= htmlspecialchars($b['BranchName']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Branch Code <span class="required-mark">*</span></label>
                <input class="form-control" name="branch_code" required value="<?= htmlspecialchars($b['BranchCode']) ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <input class="form-control" name="address" value="<?= htmlspecialchars($b['Address'] ?? '') ?>">
              </div>
            </div>

            <div class="form-section-label"><span class="dot"><i class="bi bi-geo-alt-fill"></i></span> Location</div>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">State</label>
                <select class="form-select" name="state_id" id="stateSelect_<?= $b['BranchID'] ?>" onchange="filterDistricts('stateSelect_<?= $b['BranchID'] ?>','districtSelect_<?= $b['BranchID'] ?>')">
                  <option value="">Select State</option>
                  <?php foreach ($states as $s): ?><option value="<?= $s['StateID'] ?>" <?= $b['StateID']==$s['StateID']?'selected':'' ?>><?= htmlspecialchars($s['StateName']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">District</label>
                <select class="form-select" name="district_id" id="districtSelect_<?= $b['BranchID'] ?>">
                  <option value="">Select District</option>
                  <?php foreach ($districts as $d): ?><option value="<?= $d['DistrictID'] ?>" data-state="<?= $d['StateID'] ?>" <?= $b['DistrictID']==$d['DistrictID']?'selected':'' ?>><?= htmlspecialchars($d['DistrictName']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Pincode</label>
                <input class="form-control" name="pincode" value="<?= htmlspecialchars($b['Pincode'] ?? '') ?>">
              </div>
            </div>

            <div class="form-section-label"><span class="dot"><i class="bi bi-telephone"></i></span> Contact &amp; Status</div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" value="<?= htmlspecialchars($b['Phone'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($b['Email'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Date Opened</label>
                <input type="date" class="form-control" name="date_opened" value="<?= htmlspecialchars($b['DateOpened'] ?? '') ?>">
              </div>
              <div class="col-md-6 d-flex align-items-end">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActive<?= $b['BranchID'] ?>" <?= $b['IsActive']?'checked':'' ?>>
                  <label class="form-check-label" for="isActive<?= $b['BranchID'] ?>" style="font-size:13.5px;font-weight:600;">Branch is active</label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn-outline-x" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn-navy">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>

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
<?php foreach ($branches as $b): ?>
filterDistricts('stateSelect_<?= $b['BranchID'] ?>', 'districtSelect_<?= $b['BranchID'] ?>');
<?php endforeach; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
