<?php
$pageTitle = 'Mobile Menu Access';
$crumb = 'Decide which mobile screens each designation can see';
$active = 'mobile_menu_access';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/mobile_menu_helper.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can manage mobile menu access.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checked = $_POST['visible'] ?? []; // ["<designationId>:<menuKey>", ...]
    $checkedSet = array_flip($checked);
    $designations = $pdo->query("SELECT DesignationID FROM Designation_Master")->fetchAll();
    $upsert = $pdo->prepare("
        UPDATE Designation_Mobile_Menu SET IsVisible=?, UpdatedAt=GETDATE(), UpdatedByStaffID=? WHERE DesignationID=? AND MenuKey=?
    ");
    $insert = $pdo->prepare("
        INSERT INTO Designation_Mobile_Menu (DesignationID, MenuKey, IsVisible, UpdatedAt, UpdatedByStaffID) VALUES (?,?,?,GETDATE(),?)
    ");
    foreach ($designations as $d) {
        foreach (array_keys(MOBILE_MENU_DEFS) as $key) {
            $isVisible = isset($checkedSet[$d['DesignationID'] . ':' . $key]) ? 1 : 0;
            $upsert->execute([$isVisible, current_staff()['StaffID'], $d['DesignationID'], $key]);
            if ($upsert->rowCount() === 0) {
                $insert->execute([$d['DesignationID'], $key, $isVisible, current_staff()['StaffID']]);
            }
        }
    }
    header('Location: mobile_menu_access.php?saved=1'); exit;
}

$designations = $pdo->query("
  SELECT d.DesignationID, d.DesignationName, r.RoleName
  FROM Designation_Master d LEFT JOIN Role_Master r ON d.RoleID = r.RoleID
  ORDER BY r.RoleName, d.DesignationName
")->fetchAll();
$grants = $pdo->query("SELECT DesignationID, MenuKey, IsVisible FROM Designation_Mobile_Menu")->fetchAll();
$grantSet = [];
foreach ($grants as $g) {
    if ($g['IsVisible']) {
        $grantSet[$g['DesignationID'] . ':' . $g['MenuKey']] = true;
    }
}
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Mobile menu access saved.</div>
<?php endif; ?>

<div class="card-x" style="margin-bottom:16px;">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;">
    <div>
      <div class="section-title" style="margin-bottom:2px;">Mobile Menu Access</div>
      <div style="font-size:12.5px;color:var(--text-muted);">
        Tick a chip to let staff with that designation see that screen in the mobile app. A designation with
        every chip off only sees the "not authorized" message. Need a new designation first?
        <a href="designation_add.php">Create one here</a>.
      </div>
    </div>
    <input type="text" id="designationSearch" class="form-control access-search" placeholder="🔍 Search designation...">
  </div>
</div>

<form method="POST">
  <div class="access-cards-grid" id="accessCardsGrid">
    <?php foreach ($designations as $d): ?>
      <div class="card-x designation-card" data-name="<?= strtolower(htmlspecialchars($d['DesignationName'] ?? '')) ?>">
        <div class="access-card-head">
          <div>
            <div class="access-card-name"><?= htmlspecialchars($d['DesignationName'] ?: '(unnamed)') ?></div>
            <div class="access-card-role"><?= htmlspecialchars($d['RoleName'] ?? '-') ?></div>
          </div>
          <div class="access-card-actions">
            <button type="button" class="select-all-btn">All</button>
            <button type="button" class="clear-all-btn">None</button>
          </div>
        </div>
        <div class="access-chips">
          <?php foreach (MOBILE_MENU_DEFS as $key => $label): ?>
            <?php $isChecked = isset($grantSet[$d['DesignationID'] . ':' . $key]); ?>
            <label class="access-chip <?= $isChecked ? 'active' : '' ?>">
              <input
                type="checkbox"
                name="visible[]"
                value="<?= $d['DesignationID'] ?>:<?= $key ?>"
                <?= $isChecked ? 'checked' : '' ?>
                onchange="this.closest('label').classList.toggle('active', this.checked)"
              >
              <?= htmlspecialchars($label) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$designations): ?>
      <div class="empty-state">No designations found.</div>
    <?php endif; ?>
  </div>

  <div style="margin-top:20px;position:sticky;bottom:16px;">
    <button class="btn-navy" type="submit" style="box-shadow:0 6px 18px rgba(11,37,69,0.25);">Save Access</button>
  </div>
</form>

<script>
document.getElementById('designationSearch').addEventListener('input', function () {
  var q = this.value.trim().toLowerCase();
  document.querySelectorAll('#accessCardsGrid .designation-card').forEach(function (card) {
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
