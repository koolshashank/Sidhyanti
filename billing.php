<?php
$pageTitle = 'Billing';
$crumb = 'Generate billing and control whether this install is active';
$active = 'billing';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/app_settings_helper.php';

if ((current_staff()['RoleName'] ?? '') !== 'SuperAdmin') {
    echo '<div class="empty-state">Only SuperAdmin can access Billing.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

/** Last day of the period given its type and start date, e.g. Monthly 2026-01-05 -> 2026-02-04. */
function billing_period_end(string $periodType, string $periodStart): string {
    $d = new DateTime($periodStart);
    switch ($periodType) {
        case 'Quarterly': $d->modify('+3 months'); break;
        case 'Yearly':    $d->modify('+1 year'); break;
        default:          $d->modify('+1 month'); break;
    }
    $d->modify('-1 day');
    return $d->format('Y-m-d');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'toggle_service') {
        $enable = ($_POST['enable'] ?? '') === '1';
        set_services_enabled($pdo, $enable, (int)current_staff()['StaffID']);
        header('Location: billing.php?toggled=1'); exit;
    }

    if (($_POST['action'] ?? '') === 'generate') {
        $periodType = in_array($_POST['period_type'] ?? '', ['Monthly', 'Quarterly', 'Yearly'], true) ? $_POST['period_type'] : '';
        $periodStart = trim($_POST['period_start'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($periodType === '' || $periodStart === '' || $amount <= 0) {
            $error = 'Period type, period start, and a positive amount are required.';
        } else {
            $periodEnd = billing_period_end($periodType, $periodStart);
            $pdo->prepare("
                INSERT INTO Billing_Invoice (PeriodType, PeriodStart, PeriodEnd, Amount, Notes, GeneratedByStaffID)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$periodType, $periodStart, $periodEnd, $amount, $notes ?: null, current_staff()['StaffID']]);
            header('Location: billing.php?generated=1'); exit;
        }
    }
}

$isEnabled = services_enabled($pdo);
$invoices = $pdo->query("
    SELECT b.*, s.FullName AS GeneratedByName
    FROM Billing_Invoice b
    LEFT JOIN Staff s ON b.GeneratedByStaffID = s.StaffID
    ORDER BY b.PeriodStart DESC, b.BillingID DESC
")->fetchAll();
?>

<?php if (isset($_GET['toggled'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Service status updated.</div>
<?php endif; ?>
<?php if (isset($_GET['generated'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Billing generated.</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger" style="border-radius:10px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card-x" style="margin-bottom:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;">
    <div>
      <div class="section-title" style="margin-bottom:2px;">Service Status</div>
      <div style="font-size:12.5px;color:var(--text-muted);">
        Disabling stops the web CRM (except SuperAdmin) and the mobile app for everyone, immediately.
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:14px;">
      <span style="font-weight:700;font-size:14px;color:<?= $isEnabled ? '#2FBF87' : '#D32F2F' ?>;">
        <?= $isEnabled ? '● Enabled' : '● Disabled' ?>
      </span>
      <form method="POST" onsubmit="return confirm('<?= $isEnabled ? 'Disable this CRM for everyone except SuperAdmin?' : 'Re-enable this CRM for everyone?' ?>');">
        <input type="hidden" name="action" value="toggle_service">
        <input type="hidden" name="enable" value="<?= $isEnabled ? '0' : '1' ?>">
        <button type="submit" class="<?= $isEnabled ? 'btn-navy' : 'btn-navy' ?>" style="background:<?= $isEnabled ? '#D32F2F' : '#2FBF87' ?>;border-color:<?= $isEnabled ? '#D32F2F' : '#2FBF87' ?>;">
          <?= $isEnabled ? 'Disable Service' : 'Enable Service' ?>
        </button>
      </form>
    </div>
  </div>
</div>

<div class="card-x" style="max-width:640px;margin-bottom:20px;">
  <div class="section-title" style="margin-bottom:14px;">Generate Billing</div>
  <form method="POST">
    <input type="hidden" name="action" value="generate">
    <label>Period</label>
    <select name="period_type" class="form-control" required style="margin-bottom:14px;">
      <option value="">Select period type</option>
      <option value="Monthly">Monthly</option>
      <option value="Quarterly">Quarterly</option>
      <option value="Yearly">Yearly</option>
    </select>
    <label>Period Start</label>
    <input type="date" name="period_start" class="form-control" required style="margin-bottom:14px;">
    <label>Amount</label>
    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required style="margin-bottom:14px;">
    <label>Notes (optional)</label>
    <input type="text" name="notes" class="form-control" maxlength="255" style="margin-bottom:16px;">
    <button class="btn-navy" type="submit">Generate</button>
  </form>
</div>

<div class="card-x">
  <div class="section-title" style="margin-bottom:14px;">Billing History</div>
  <?php if (!$invoices): ?>
    <div class="empty-state">No billing generated yet.</div>
  <?php else: ?>
    <div class="table-scroll">
      <table class="table-x">
        <thead>
          <tr>
            <th>Period</th>
            <th>Start</th>
            <th>End</th>
            <th>Amount</th>
            <th>Notes</th>
            <th>Generated By</th>
            <th>Generated At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
          <tr>
            <td><?= htmlspecialchars($inv['PeriodType']) ?></td>
            <td><?= htmlspecialchars(date('d M Y', strtotime($inv['PeriodStart']))) ?></td>
            <td><?= htmlspecialchars(date('d M Y', strtotime($inv['PeriodEnd']))) ?></td>
            <td>₹<?= number_format((float)$inv['Amount'], 2) ?></td>
            <td><?= htmlspecialchars($inv['Notes'] ?? '-') ?></td>
            <td><?= htmlspecialchars($inv['GeneratedByName'] ?? '-') ?></td>
            <td><?= htmlspecialchars(date('d M Y, h:i A', strtotime($inv['CreatedAt']))) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
