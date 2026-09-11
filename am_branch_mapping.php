<?php
$pageTitle = 'AM Branch Mapping';
$crumb = 'Decide which branches each Area Manager covers';
$active = 'am_branch_mapping';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can manage AM branch mapping.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checked = $_POST['mapped'] ?? []; // ["<staffId>:<branchId>", ...]
    $checkedSet = array_flip($checked);
    $amStaff = $pdo->query("
        SELECT StaffID FROM Staff WHERE RoleID = (SELECT RoleID FROM Role_Master WHERE RoleName = 'Area Manager') AND IsActive = 1
    ")->fetchAll();
    $branches = $pdo->query("SELECT BranchID FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%'")->fetchAll();

    $delete = $pdo->prepare("DELETE FROM Staff_Branch_Map WHERE StaffID = ? AND BranchID = ?");
    $insert = $pdo->prepare("INSERT INTO Staff_Branch_Map (StaffID, BranchID, CreatedByStaffID) VALUES (?,?,?)");
    $exists = $pdo->prepare("SELECT 1 FROM Staff_Branch_Map WHERE StaffID = ? AND BranchID = ?");

    foreach ($amStaff as $s) {
        foreach ($branches as $b) {
            $key = $s['StaffID'] . ':' . $b['BranchID'];
            $shouldBeMapped = isset($checkedSet[$key]);
            $exists->execute([$s['StaffID'], $b['BranchID']]);
            $isMapped = (bool)$exists->fetchColumn();
            if ($shouldBeMapped && !$isMapped) {
                $insert->execute([$s['StaffID'], $b['BranchID'], current_staff()['StaffID']]);
            } elseif (!$shouldBeMapped && $isMapped) {
                $delete->execute([$s['StaffID'], $b['BranchID']]);
            }
        }
    }
    header('Location: am_branch_mapping.php?saved=1'); exit;
}

$amStaff = $pdo->query("
    SELECT s.StaffID, s.FullName, b.BranchName AS HomeBranchName
    FROM Staff s
    LEFT JOIN Branch b ON s.BranchID = b.BranchID
    WHERE s.RoleID = (SELECT RoleID FROM Role_Master WHERE RoleName = 'Area Manager') AND s.IsActive = 1
    ORDER BY s.FullName
")->fetchAll();
$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$mappings = $pdo->query("SELECT StaffID, BranchID FROM Staff_Branch_Map")->fetchAll();
$mappedSet = [];
foreach ($mappings as $m) {
    $mappedSet[$m['StaffID'] . ':' . $m['BranchID']] = true;
}
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success" style="border-radius:10px;">AM branch mapping saved.</div>
<?php endif; ?>

<div class="card-x" style="margin-bottom:16px;">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;">
    <div>
      <div class="section-title" style="margin-bottom:2px;">AM Branch Mapping</div>
      <div style="font-size:12.5px;color:var(--text-muted);">
        Tick which branches each Area Manager covers -- their Loan Approval, Due &amp; Collections, Center
        Transfer, and TVR screens in the mobile app will show all of them combined. An Area Manager with
        nothing ticked here falls back to just their own home branch (from Staff → Edit Employee).
      </div>
    </div>
    <input type="text" id="amSearch" class="form-control access-search" placeholder="🔍 Search Area Manager...">
  </div>
</div>

<?php if (!$amStaff): ?>
  <div class="empty-state">No active Area Manager staff found. Assign the "Area Manager" role to an employee first.</div>
<?php else: ?>
<form method="POST">
  <div class="access-cards-grid" id="amCardsGrid">
    <?php foreach ($amStaff as $s): ?>
      <div class="card-x designation-card" data-name="<?= strtolower(htmlspecialchars($s['FullName'] ?? '')) ?>">
        <div class="access-card-head">
          <div>
            <div class="access-card-name"><?= htmlspecialchars($s['FullName'] ?: '(unnamed)') ?></div>
            <div class="access-card-role">Home branch: <?= htmlspecialchars($s['HomeBranchName'] ?? '-') ?></div>
          </div>
          <div class="access-card-actions">
            <button type="button" class="select-all-btn">All</button>
            <button type="button" class="clear-all-btn">None</button>
          </div>
        </div>
        <div class="access-chips">
          <?php foreach ($branches as $b): ?>
            <?php $isChecked = isset($mappedSet[$s['StaffID'] . ':' . $b['BranchID']]); ?>
            <label class="access-chip <?= $isChecked ? 'active' : '' ?>">
              <input
                type="checkbox"
                name="mapped[]"
                value="<?= $s['StaffID'] ?>:<?= $b['BranchID'] ?>"
                <?= $isChecked ? 'checked' : '' ?>
                onchange="this.closest('label').classList.toggle('active', this.checked)"
              >
              <?= htmlspecialchars($b['BranchName']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:20px;position:sticky;bottom:16px;">
    <button class="btn-navy" type="submit" style="box-shadow:0 6px 18px rgba(11,37,69,0.25);">Save Mapping</button>
  </div>
</form>
<?php endif; ?>

<script>
document.getElementById('amSearch').addEventListener('input', function () {
  var q = this.value.trim().toLowerCase();
  document.querySelectorAll('#amCardsGrid .designation-card').forEach(function (card) {
    card.style.display = card.dataset.name.indexOf(q) === -1 ? 'none' : '';
  });
});

document.querySelectorAll('.select-all-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    this.closest('.designation-card').querySelectorAll('input[type=checkbox]').forEach(function (cb) {
      cb.checked = true;
      cb.closest('label').classList.add('active');
    });
  });
});
document.querySelectorAll('.clear-all-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    this.closest('.designation-card').querySelectorAll('input[type=checkbox]').forEach(function (cb) {
      cb.checked = false;
      cb.closest('label').classList.remove('active');
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
