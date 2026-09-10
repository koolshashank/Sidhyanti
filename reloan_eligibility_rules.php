<?php
$pageTitle = 'Reloan Eligibility Rules';
$crumb = "Decide how much a client's loan amount can grow on their next loan";
$active = 'reloan_eligibility_rules';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

$canManage = role_has_permission($pdo, current_staff()['RoleID'] ?? null, 'manage_reloan_eligibility_rules');
if (!$canManage) {
    echo '<div class="empty-state">You do not have permission to manage reloan eligibility rules.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$error = '';

function reloan_rule_inputs(): array {
    return [
        trim($_POST['rule_name'] ?? ''),
        (int)($_POST['min_cycle_number'] ?? 0),
        (int)($_POST['max_late_payments'] ?? 0),
        (float)($_POST['increase_percent'] ?? 0),
        isset($_POST['is_active']) ? 1 : 0,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    [$name, $minCycle, $maxLate, $pct, $isActive] = reloan_rule_inputs();
    if ($name === '' || $minCycle < 1 || $maxLate < 0 || $pct < 0) {
        $error = 'Please fill in all fields with valid values.';
    } else {
        $pdo->prepare("
            INSERT INTO Reloan_Eligibility_Rule (RuleName, MinCycleNumber, MaxLatePayments, IncreasePercent, IsActive, CreatedByStaffID)
            VALUES (?,?,?,?,?,?)
        ")->execute([$name, $minCycle, $maxLate, $pct, $isActive, current_staff()['StaffID']]);
        header('Location: reloan_eligibility_rules.php?added=1'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $ruleId = (int)($_POST['rule_id'] ?? 0);
    [$name, $minCycle, $maxLate, $pct, $isActive] = reloan_rule_inputs();
    if ($name === '' || $minCycle < 1 || $maxLate < 0 || $pct < 0) {
        $error = 'Please fill in all fields with valid values.';
    } else {
        $pdo->prepare("
            UPDATE Reloan_Eligibility_Rule SET RuleName=?, MinCycleNumber=?, MaxLatePayments=?, IncreasePercent=?, IsActive=?,
                UpdatedByStaffID=?, UpdatedAt=GETDATE()
            WHERE RuleID=?
        ")->execute([$name, $minCycle, $maxLate, $pct, $isActive, current_staff()['StaffID'], $ruleId]);
        header('Location: reloan_eligibility_rules.php?updated=1'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $pdo->prepare("DELETE FROM Reloan_Eligibility_Rule WHERE RuleID=?")->execute([(int)($_POST['rule_id'] ?? 0)]);
    header('Location: reloan_eligibility_rules.php?deleted=1'); exit;
}

$rules = $pdo->query("SELECT * FROM Reloan_Eligibility_Rule ORDER BY MinCycleNumber, IncreasePercent DESC")->fetchAll();
?>

<?php if ($error): ?>
  <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div>
<?php elseif (isset($_GET['added'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Rule added.</div>
<?php elseif (isset($_GET['updated'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Rule updated.</div>
<?php elseif (isset($_GET['deleted'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Rule deleted.</div>
<?php endif; ?>

<div class="card-x mb-3">
  <div style="font-size:12.5px;color:var(--text-muted);">
    Applies <strong>only to reloans</strong> (a member whose previous loan is already Closed/Preclosed) — never to a fresh, first-time loan.
    When a client's loan cycle number and their previous loan's on-time repayment count both satisfy a rule, the Loan Proposal screen on mobile
    shows them as pre-approved for <strong>previous loan amount × (1 + Increase %)</strong>. If more than one rule matches, the highest Increase % wins.
    The CM can enter that amount or less — never more.
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div style="color:var(--text-muted);font-size:13px;"><?= count($rules) ?> rule(s) configured</div>
  <button class="btn-navy" type="button" data-bs-toggle="modal" data-bs-target="#addRuleModal">+ Add Rule</button>
</div>

<div class="card-x">
  <table class="table-x">
    <tr>
      <th>Rule Name</th><th>Min. Loan Cycle</th><th>Max. Late Payments Allowed</th><th>Increase %</th><th>Status</th><th></th>
    </tr>
    <?php foreach ($rules as $r): ?>
    <tr>
      <td style="font-weight:600;"><?= htmlspecialchars($r['RuleName']) ?></td>
      <td><?= (int)$r['MinCycleNumber'] ?><span style="color:var(--text-muted);">th loan onward</span></td>
      <td><?= (int)$r['MaxLatePayments'] ?></td>
      <td style="font-weight:700;color:var(--navy);">+<?= rtrim(rtrim(number_format((float)$r['IncreasePercent'], 2), '0'), '.') ?>%</td>
      <td><span class="badge badge-<?= $r['IsActive']?'success':'secondary' ?>"><?= $r['IsActive']?'Active':'Inactive' ?></span></td>
      <td class="text-nowrap">
        <button type="button" class="btn-outline-x" style="font-size:12px;padding:5px 12px;" data-bs-toggle="modal" data-bs-target="#editRule<?= $r['RuleID'] ?>">Edit</button>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this rule?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="rule_id" value="<?= $r['RuleID'] ?>">
          <button class="btn-outline-x" style="font-size:12px;padding:5px 12px;color:var(--danger);border-color:var(--danger);">Delete</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rules): ?><tr><td colspan="6"><div class="empty-state">No rules configured yet — reloans currently get the scheme's default amount only.</div></td></tr><?php endif; ?>
  </table>
</div>

<div class="modal fade" id="addRuleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title">Add Reloan Eligibility Rule</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Rule Name <span class="required-mark">*</span></label>
              <input class="form-control" name="rule_name" required placeholder="e.g. 2nd loan, perfect repayment">
            </div>
            <div class="col-md-6">
              <label class="form-label">Applies From Loan Cycle <span class="required-mark">*</span></label>
              <input type="number" min="1" class="form-control" name="min_cycle_number" required placeholder="e.g. 2">
            </div>
            <div class="col-md-6">
              <label class="form-label">Max Late Payments Allowed <span class="required-mark">*</span></label>
              <input type="number" min="0" class="form-control" name="max_late_payments" required placeholder="e.g. 0">
            </div>
            <div class="col-md-6">
              <label class="form-label">Increase % <span class="required-mark">*</span></label>
              <input type="number" min="0" step="0.01" class="form-control" name="increase_percent" required placeholder="e.g. 25">
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveAdd" checked>
                <label class="form-check-label" for="isActiveAdd" style="font-size:13.5px;font-weight:600;">Active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-outline-x" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-navy">Add Rule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($rules as $r): ?>
<div class="modal fade" id="editRule<?= $r['RuleID'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="rule_id" value="<?= $r['RuleID'] ?>">
        <div class="modal-header">
          <h5 class="modal-title">Edit Rule</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Rule Name <span class="required-mark">*</span></label>
              <input class="form-control" name="rule_name" required value="<?= htmlspecialchars($r['RuleName']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Applies From Loan Cycle <span class="required-mark">*</span></label>
              <input type="number" min="1" class="form-control" name="min_cycle_number" required value="<?= (int)$r['MinCycleNumber'] ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Max Late Payments Allowed <span class="required-mark">*</span></label>
              <input type="number" min="0" class="form-control" name="max_late_payments" required value="<?= (int)$r['MaxLatePayments'] ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Increase % <span class="required-mark">*</span></label>
              <input type="number" min="0" step="0.01" class="form-control" name="increase_percent" required value="<?= $r['IncreasePercent'] ?>">
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveEdit<?= $r['RuleID'] ?>" <?= $r['IsActive']?'checked':'' ?>>
                <label class="form-check-label" for="isActiveEdit<?= $r['RuleID'] ?>" style="font-size:13.5px;font-weight:600;">Active</label>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
