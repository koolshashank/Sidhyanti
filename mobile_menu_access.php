<?php
$pageTitle = 'Mobile Menu Access';
$crumb = 'Decide which mobile screens each designation can see';
$active = 'mobile_menu_access';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/mobile_menu_helper.php';

// Gated by RoleID (not RoleName) -- $_SESSION['staff'] is set at login from a
// raw "SELECT * FROM Staff" with no Role_Master join, so RoleName is not
// reliably populated for the session's first requests; RoleID always is.
if ((int)(current_staff()['RoleID'] ?? 0) !== 1) {
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

<div class="card-x">
  <div class="section-title">Mobile Menu Access</div>
  <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">
    Tick a box to let staff with that designation see that screen in the mobile app. A designation left
    unticked for every screen only sees the standard dashboard (My Members / Due Today / Collected Today).
    Need a new designation first? <a href="designation_add.php">Create one here</a>.
  </div>
  <form method="POST">
    <div class="table-scroll">
      <table class="table-x">
        <tr>
          <th>Designation</th>
          <?php foreach (MOBILE_MENU_DEFS as $label): ?>
            <th><?= htmlspecialchars($label) ?></th>
          <?php endforeach; ?>
        </tr>
        <?php foreach ($designations as $d): ?>
        <tr>
          <td><strong><?= htmlspecialchars($d['DesignationName'] ?: '(unnamed)') ?></strong><br><small style="color:var(--text-muted);"><?= htmlspecialchars($d['RoleName'] ?? '-') ?></small></td>
          <?php foreach (MOBILE_MENU_DEFS as $key => $label): ?>
            <td>
              <input type="checkbox" name="visible[]" value="<?= $d['DesignationID'] ?>:<?= $key ?>"
                     <?= isset($grantSet[$d['DesignationID'] . ':' . $key]) ? 'checked' : '' ?>>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (!$designations): ?><tr><td colspan="<?= count(MOBILE_MENU_DEFS) + 1 ?>"><div class="empty-state">No designations found.</div></td></tr><?php endif; ?>
      </table>
    </div>
    <div style="margin-top:14px;">
      <button class="btn-navy" type="submit">Save Access</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
