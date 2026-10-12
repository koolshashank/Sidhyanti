<?php
$pageTitle = 'Registered Devices';
$crumb = 'Staff locked to a phone -- reset to let them switch devices';
$active = 'staff_devices';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can manage registered devices.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_staff_id'])) {
    $staffId = (int)$_POST['reset_staff_id'];
    $pdo->prepare("UPDATE Staff SET DeviceID = NULL, DeviceRegisteredAt = NULL WHERE StaffID = ?")->execute([$staffId]);
    header('Location: staff_devices.php?reset=1'); exit;
}

$staffRows = $pdo->query("
    SELECT s.StaffID, s.FullName, s.Username, r.RoleName, s.DeviceID, s.DeviceRegisteredAt
    FROM Staff s
    LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
    WHERE s.DeviceID IS NOT NULL
    ORDER BY s.DeviceRegisteredAt DESC
")->fetchAll();
?>

<?php if (isset($_GET['reset'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Device reset -- this staff member can log in from a new phone now.</div>
<?php endif; ?>

<div class="card-x">
  <div class="section-title">Registered Devices</div>
  <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">
    Staff whose role has device lock enabled (see <a href="device_lock_settings.php">Device Lock Settings</a>) get bound to the first phone they log in from. Reset here when a phone is lost or replaced.
  </div>
  <?php if (!$staffRows): ?>
    <div class="empty-state">No staff have a registered device yet.</div>
  <?php else: ?>
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Staff</th><th>Role</th><th>Registered On</th><th></th></tr>
      <?php foreach ($staffRows as $s): ?>
      <tr>
        <td>
          <strong><?= htmlspecialchars($s['FullName']) ?></strong><br>
          <span style="color:var(--text-muted);font-size:12px;"><?= htmlspecialchars($s['Username']) ?></span>
        </td>
        <td><?= htmlspecialchars($s['RoleName'] ?? '-') ?></td>
        <td><?= $s['DeviceRegisteredAt'] ? htmlspecialchars(date('d M Y, h:i A', strtotime((string)$s['DeviceRegisteredAt']))) : '-' ?></td>
        <td>
          <form method="POST" onsubmit="return confirm('Reset this staff member\'s registered device? They will be able to log in from a new phone.');">
            <input type="hidden" name="reset_staff_id" value="<?= $s['StaffID'] ?>">
            <button class="btn-outline-x" type="submit" style="padding:4px 10px;font-size:12px;white-space:nowrap;">Reset Device</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
