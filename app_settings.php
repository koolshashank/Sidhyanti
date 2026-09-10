<?php
$pageTitle = 'App Settings';
$crumb = 'App-wide toggles for the mobile app';
$active = 'app_settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/app_settings_helper.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can manage app settings.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checked = $_POST['enabled'] ?? []; // ["<key>", ...] for bool settings
    $upsert = $pdo->prepare("
        UPDATE App_Setting SET SettingValue=?, UpdatedAt=GETDATE(), UpdatedByStaffID=? WHERE SettingKey=?
    ");
    $insert = $pdo->prepare("
        INSERT INTO App_Setting (SettingKey, SettingValue, UpdatedAt, UpdatedByStaffID) VALUES (?,?,GETDATE(),?)
    ");
    foreach (APP_SETTING_DEFS as $key => $def) {
        if ($def['type'] !== 'bool') {
            continue;
        }
        $value = in_array($key, $checked, true) ? '1' : '0';
        $upsert->execute([$value, current_staff()['StaffID'], $key]);
        if ($upsert->rowCount() === 0) {
            $insert->execute([$key, $value, current_staff()['StaffID']]);
        }
    }
    header('Location: app_settings.php?saved=1'); exit;
}
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Settings saved.</div>
<?php endif; ?>

<div class="card-x" style="max-width:640px;">
  <div class="section-title">App Settings</div>
  <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:18px;">
    These apply to every staff member's mobile app -- checked once per app launch, so a change here reaches everyone within a launch (no logout needed).
  </div>
  <form method="POST">
    <?php foreach (APP_SETTING_DEFS as $key => $def): ?>
      <?php if ($def['type'] === 'bool'): ?>
        <div class="form-check form-switch" style="margin-bottom:14px;">
          <input class="form-check-input" type="checkbox" role="switch" name="enabled[]" value="<?= htmlspecialchars($key) ?>"
                 id="setting_<?= htmlspecialchars($key) ?>"
                 <?= get_app_setting_bool($pdo, $key) ? 'checked' : '' ?>>
          <label class="form-check-label" for="setting_<?= htmlspecialchars($key) ?>" style="font-size:13.5px;font-weight:600;">
            <?= htmlspecialchars($def['label']) ?>
          </label>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
    <button class="btn-navy" type="submit" style="margin-top:8px;">Save Settings</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
