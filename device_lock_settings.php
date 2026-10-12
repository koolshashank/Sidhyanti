<?php
$pageTitle = 'Device Lock Settings';
$crumb = 'Choose which roles get locked to a single mobile device';
$active = 'device_lock_settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can manage device lock settings.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checked = $_POST['enabled'] ?? []; // ["<roleId>", ...]
    $checkedSet = array_flip($checked);
    $roles = $pdo->query("SELECT RoleID FROM Role_Master")->fetchAll();
    $update = $pdo->prepare("UPDATE Role_Master SET DeviceLockEnabled = ? WHERE RoleID = ?");
    foreach ($roles as $r) {
        $update->execute([isset($checkedSet[$r['RoleID']]) ? 1 : 0, $r['RoleID']]);
    }
    header('Location: device_lock_settings.php?saved=1'); exit;
}

$roles = $pdo->query("SELECT RoleID, RoleName, DeviceLockEnabled FROM Role_Master ORDER BY RoleID")->fetchAll();
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Settings saved.</div>
<?php endif; ?>

<div class="card-x">
  <div class="section-title">Device Lock Settings</div>
  <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">
    When enabled for a role, that staff member's mobile app gets locked to the first phone they log in from -- the same account can't be used on a second phone at the same time. To let someone switch to a new phone, reset their device from <a href="staff_devices.php">Registered Devices</a>.
  </div>
  <form method="POST">
    <table class="table-x">
      <tr><th>Role</th><th>Lock to one device</th></tr>
      <?php foreach ($roles as $r): ?>
      <tr>
        <td><strong><?= htmlspecialchars($r['RoleName']) ?></strong></td>
        <td><input type="checkbox" name="enabled[]" value="<?= $r['RoleID'] ?>" <?= $r['DeviceLockEnabled'] ? 'checked' : '' ?>></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div style="margin-top:14px;">
      <button class="btn-navy" type="submit">Save Settings</button>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
