<?php
$pageTitle = 'Incentive Slabs';
$crumb = 'Slab-based monthly incentive rules by target-achievement %';
$active = 'incentive_slabs';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can manage incentive slabs.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_slab'])) {
        $metric = in_array($_POST['metric_type'] ?? '', ['Disbursement', 'Collection'], true) ? $_POST['metric_type'] : 'Disbursement';
        $min = (float)($_POST['min_percent'] ?? 0);
        $max = trim($_POST['max_percent'] ?? '') === '' ? null : (float)$_POST['max_percent'];
        $amount = (float)($_POST['amount'] ?? 0);
        $pdo->prepare("INSERT INTO Incentive_Slab (MetricType, MinAchievementPercent, MaxAchievementPercent, IncentiveAmount, UpdatedByStaffID) VALUES (?,?,?,?,?)")
            ->execute([$metric, $min, $max, $amount, current_staff()['StaffID']]);
    } elseif (isset($_POST['delete_slab'])) {
        $pdo->prepare("DELETE FROM Incentive_Slab WHERE SlabID = ?")->execute([(int)$_POST['slab_id']]);
    } elseif (isset($_POST['toggle_slab'])) {
        $pdo->prepare("UPDATE Incentive_Slab SET IsActive = ~IsActive WHERE SlabID = ?")->execute([(int)$_POST['slab_id']]);
    }
    header('Location: incentive_slabs.php?saved=1'); exit;
}

$disbursementSlabs = $pdo->query("SELECT * FROM Incentive_Slab WHERE MetricType = 'Disbursement' ORDER BY MinAchievementPercent")->fetchAll();
$collectionSlabs = $pdo->query("SELECT * FROM Incentive_Slab WHERE MetricType = 'Collection' ORDER BY MinAchievementPercent")->fetchAll();

function renderSlabTable($slabs, $metric) {
    ?>
    <table class="table-x" style="margin-bottom:16px;">
      <tr><th>Min %</th><th>Max %</th><th>Amount</th><th>Status</th><th></th></tr>
      <?php foreach ($slabs as $s): ?>
      <tr>
        <td><?= htmlspecialchars($s['MinAchievementPercent']) ?>%</td>
        <td><?= $s['MaxAchievementPercent'] !== null ? htmlspecialchars($s['MaxAchievementPercent']) . '%' : 'No limit' ?></td>
        <td><?= money($s['IncentiveAmount']) ?></td>
        <td>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="slab_id" value="<?= $s['SlabID'] ?>">
            <button type="submit" name="toggle_slab" value="1" class="badge <?= $s['IsActive'] ? 'badge-success' : 'badge-secondary' ?>" style="border:none;cursor:pointer;">
              <?= $s['IsActive'] ? 'Active' : 'Inactive' ?>
            </button>
          </form>
        </td>
        <td>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this slab?');">
            <input type="hidden" name="slab_id" value="<?= $s['SlabID'] ?>">
            <button type="submit" name="delete_slab" value="1" class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$slabs): ?><tr><td colspan="5" class="text-muted">No slabs yet.</td></tr><?php endif; ?>
    </table>
    <form method="POST" class="row g-2 align-items-end">
      <input type="hidden" name="metric_type" value="<?= $metric ?>">
      <div class="col-auto"><label class="form-label" style="font-size:12px;">Min %</label><input type="number" step="0.01" name="min_percent" class="form-control form-control-sm" style="width:100px;" required></div>
      <div class="col-auto"><label class="form-label" style="font-size:12px;">Max % (blank = no limit)</label><input type="number" step="0.01" name="max_percent" class="form-control form-control-sm" style="width:140px;"></div>
      <div class="col-auto"><label class="form-label" style="font-size:12px;">Incentive Amount</label><input type="number" step="0.01" name="amount" class="form-control form-control-sm" style="width:120px;" required></div>
      <div class="col-auto"><button type="submit" name="add_slab" value="1" class="btn btn-navy btn-sm">Add Slab</button></div>
    </form>
    <?php
}
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Saved.</div>
<?php endif; ?>

<div class="card-x" style="margin-bottom:20px;max-width:760px;">
  <div class="section-title">Disbursement Slabs</div>
  <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">Based on each officer's monthly disbursement-target achievement % (set via Staff Targets).</div>
  <?php renderSlabTable($disbursementSlabs, 'Disbursement'); ?>
</div>

<div class="card-x" style="max-width:760px;">
  <div class="section-title">Collection Slabs</div>
  <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">Based on each officer's monthly collection-due achievement % (collected vs. what was due -- there's no separate settable collection target).</div>
  <?php renderSlabTable($collectionSlabs, 'Collection'); ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
