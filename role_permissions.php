<?php
$pageTitle = 'Role Permissions';
$crumb = 'Decide which roles can use sensitive actions';
$active = 'role_permissions';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can manage role permissions.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checked = $_POST['allowed'] ?? []; // ["<roleId>:<permissionKey>", ...]
    $checkedSet = array_flip($checked);
    $roles = $pdo->query("SELECT RoleID FROM Role_Master")->fetchAll();
    $upsert = $pdo->prepare("
        UPDATE Role_Permission SET IsAllowed=?, UpdatedAt=GETDATE(), UpdatedByStaffID=? WHERE RoleID=? AND PermissionKey=?
    ");
    $insert = $pdo->prepare("
        INSERT INTO Role_Permission (RoleID, PermissionKey, IsAllowed, UpdatedAt, UpdatedByStaffID) VALUES (?,?,?,GETDATE(),?)
    ");
    foreach ($roles as $r) {
        foreach (array_keys(PERMISSION_DEFS) as $key) {
            $isAllowed = isset($checkedSet[$r['RoleID'] . ':' . $key]) ? 1 : 0;
            $upsert->execute([$isAllowed, current_staff()['StaffID'], $r['RoleID'], $key]);
            if ($upsert->rowCount() === 0) {
                $insert->execute([$r['RoleID'], $key, $isAllowed, current_staff()['StaffID']]);
            }
        }
    }
    header('Location: role_permissions.php?saved=1'); exit;
}

$roles = $pdo->query("SELECT RoleID, RoleName FROM Role_Master ORDER BY RoleID")->fetchAll();
$grants = $pdo->query("SELECT RoleID, PermissionKey, IsAllowed FROM Role_Permission")->fetchAll();
$grantSet = [];
foreach ($grants as $g) {
    if ($g['IsAllowed']) {
        $grantSet[$g['RoleID'] . ':' . $g['PermissionKey']] = true;
    }
}
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Permissions saved.</div>
<?php endif; ?>

<div class="card-x">
  <div class="section-title">Role Permissions</div>
  <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">
    Tick a box to grant that role the ability to use the action. Roles left unticked cannot use it, in the web CRM or the mobile app.
  </div>
  <form method="POST">
    <div class="table-scroll">
      <table class="table-x">
        <tr>
          <th>Role</th>
          <?php foreach (PERMISSION_DEFS as $label): ?>
            <th><?= htmlspecialchars($label) ?></th>
          <?php endforeach; ?>
        </tr>
        <?php foreach ($roles as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['RoleName']) ?></strong></td>
          <?php foreach (PERMISSION_DEFS as $key => $label): ?>
            <td>
              <input type="checkbox" name="allowed[]" value="<?= $r['RoleID'] ?>:<?= $key ?>"
                     <?= isset($grantSet[$r['RoleID'] . ':' . $key]) ? 'checked' : '' ?>>
            </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <div style="margin-top:14px;">
      <button class="btn-navy" type="submit">Save Permissions</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
