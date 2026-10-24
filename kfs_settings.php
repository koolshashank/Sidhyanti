<?php
$pageTitle = 'KFS Settings';
$crumb = 'Company details printed on every loan\'s Key Fact Sheet';
$active = 'kfs_settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can manage KFS settings.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$fields = [
    'CompanyName'              => 'Company Name',
    'NBFCRegistrationNo'       => 'NBFC Registration No. / CIN',
    'RegisteredOfficeAddress'  => 'Registered Office Address',
    'CustomerCarePhone'        => 'Customer Care Phone',
    'CustomerCareEmail'        => 'Customer Care Email',
    'GrievanceOfficerName'     => 'Grievance Redressal Officer Name',
    'GrievanceOfficerPhone'    => 'Grievance Officer Phone',
    'CoolingOffPeriodDays'     => 'Cooling-off / Look-up Period (days)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $update = $pdo->prepare("UPDATE Company_Profile SET
        CompanyName = ?, NBFCRegistrationNo = ?, RegisteredOfficeAddress = ?,
        CustomerCarePhone = ?, CustomerCareEmail = ?, GrievanceOfficerName = ?,
        GrievanceOfficerPhone = ?, CoolingOffPeriodDays = ?, UpdatedAt = GETDATE(), UpdatedByStaffID = ?
        WHERE ProfileID = 1");
    $update->execute([
        trim($_POST['CompanyName'] ?? ''),
        trim($_POST['NBFCRegistrationNo'] ?? ''),
        trim($_POST['RegisteredOfficeAddress'] ?? ''),
        trim($_POST['CustomerCarePhone'] ?? ''),
        trim($_POST['CustomerCareEmail'] ?? ''),
        trim($_POST['GrievanceOfficerName'] ?? ''),
        trim($_POST['GrievanceOfficerPhone'] ?? ''),
        max(0, (int)($_POST['CoolingOffPeriodDays'] ?? 3)),
        current_staff()['StaffID'],
    ]);
    header('Location: kfs_settings.php?saved=1'); exit;
}

$profile = $pdo->query("SELECT * FROM Company_Profile WHERE ProfileID = 1")->fetch();
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Settings saved.</div>
<?php endif; ?>

<div class="card-x" style="max-width:640px;">
  <div class="section-title">KFS Settings</div>
  <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:18px;">
    These details print on the header/footer of every loan's Key Fact Sheet (kfs_view.php) -- a regulatory
    disclosure document, so fill in real values before relying on it for compliance.
  </div>
  <form method="POST">
    <?php foreach ($fields as $key => $label): ?>
      <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:600;"><?= htmlspecialchars($label) ?></label>
        <?php if ($key === 'RegisteredOfficeAddress'): ?>
          <textarea class="form-control" name="<?= $key ?>" rows="2"><?= htmlspecialchars($profile[$key] ?? '') ?></textarea>
        <?php elseif ($key === 'CoolingOffPeriodDays'): ?>
          <input class="form-control" type="number" min="0" name="<?= $key ?>" value="<?= htmlspecialchars($profile[$key] ?? 3) ?>">
        <?php else: ?>
          <input class="form-control" type="text" name="<?= $key ?>" value="<?= htmlspecialchars($profile[$key] ?? '') ?>">
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <button class="btn-navy" type="submit" style="margin-top:8px;">Save Settings</button>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
