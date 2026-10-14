<?php
$pageTitle = 'Collection';
$crumb = 'Daily, weekly & monthly collection performance by branch and center';
$active = 'collection';
require_once __DIR__ . '/includes/header.php';

/* ---------------------------------------------------------------------
   Period resolution — defaults to "today"
--------------------------------------------------------------------- */
$period = $_GET['period'] ?? 'today';
if (!in_array($period, ['today', 'yesterday', 'week', 'month', 'custom'], true)) $period = 'today';

$today = new DateTime('today');
switch ($period) {
    case 'yesterday':
        $from = $to = (clone $today)->modify('-1 day')->format('Y-m-d');
        break;
    case 'week':
        $from = (clone $today)->modify('monday this week')->format('Y-m-d');
        $to   = (clone $today)->modify('sunday this week')->format('Y-m-d');
        break;
    case 'month':
        $from = (clone $today)->modify('first day of this month')->format('Y-m-d');
        $to   = (clone $today)->modify('last day of this month')->format('Y-m-d');
        break;
    case 'custom':
        $from = $_GET['date_from'] ?? $today->format('Y-m-d');
        $to   = $_GET['date_to'] ?? $today->format('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $today->format('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = $today->format('Y-m-d');
        if ($to < $from) { [$from, $to] = [$to, $from]; }
        break;
    default: // today
        $from = $to = $today->format('Y-m-d');
}

$branchId = (int)($_GET['branch_id'] ?? 0);
$centerId = (int)($_GET['center_id'] ?? 0);
$viewMode = ($_GET['view'] ?? 'card') === 'table' ? 'table' : 'card';

function collectionUrl($overrides = []) {
    global $period, $branchId, $centerId, $viewMode;
    $effPeriod = $overrides['period'] ?? $period;
    $q = ['period' => $effPeriod];
    if ($effPeriod === 'custom') {
        $q['date_from'] = $overrides['date_from'] ?? ($_GET['date_from'] ?? '');
        $q['date_to']   = $overrides['date_to']   ?? ($_GET['date_to'] ?? '');
    }
    $q['branch_id'] = $overrides['branch_id'] ?? $branchId;
    $q['center_id'] = $overrides['center_id'] ?? $centerId;
    $q['view'] = $overrides['view'] ?? $viewMode;
    foreach (['branch_id', 'center_id'] as $k) { if (empty($q[$k])) unset($q[$k]); }
    return 'collection.php?' . http_build_query($q);
}

/* ---------------------------------------------------------------------
   All collection data comes from Daily_Collection_Sheet — one row per
   loan installment due on a given day, already carrying BranchID,
   CenterID, the due amount (EMIAmount) and what was actually collected
   (CollectionAmount / CollectionStatus).
--------------------------------------------------------------------- */
$params = [$from, $to];
$branchCond = '';
$centerCond = '';
if ($branchId) { $branchCond = 'AND dcs.BranchID = ?'; $params[] = $branchId; }
if ($centerId) { $centerCond = 'AND dcs.CenterID = ?'; $params[] = $centerId; }

/* ---- Summary for whatever scope is currently in view ---- */
$summaryStmt = $pdo->prepare("
  SELECT COUNT(DISTINCT dcs.LoanID) AS ClientCount,
         COALESCE(SUM(dcs.EMIAmount),0) AS DueAmount, COALESCE(SUM(dcs.CollectionAmount),0) AS CollectedAmount
  FROM Daily_Collection_Sheet dcs
  WHERE dcs.CollectionDueDate BETWEEN ? AND ? $branchCond $centerCond
");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();
$sDue = (float)$summary['DueAmount'];
$sCollected = (float)$summary['CollectedAmount'];
$sBalance = $sDue - $sCollected;
$sPct = $sDue > 0 ? round($sCollected / $sDue * 100, 1) : 0;

/* ---- Drill-level rows: branches -> centers -> clients ---- */
$branchRows = $centerRows = $clientRows = [];
$branchName = $centerName = '';

if ($centerId) {
    $centerStmt = $pdo->prepare("SELECT c.CenterName, b.BranchName FROM Center c LEFT JOIN Branch b ON c.BranchID = b.BranchID WHERE c.CenterID = ?");
    $centerStmt->execute([$centerId]);
    $centerInfo = $centerStmt->fetch();
    $centerName = $centerInfo['CenterName'] ?? '';
    $branchName = $centerInfo['BranchName'] ?? '';

    $stmt = $pdo->prepare("
      SELECT dcs.LoanID, dcs.BorrowerName, dcs.CoBorrowerName,
             COUNT(*) AS InstallmentCount,
             SUM(dcs.EMIAmount) AS DueAmount, COALESCE(SUM(dcs.CollectionAmount),0) AS CollectedAmount,
             MAX(CASE WHEN dcs.CollectionStatus = 'Preclosed' THEN 1 ELSE 0 END) AS HasPreclose
      FROM Daily_Collection_Sheet dcs
      WHERE dcs.CollectionDueDate BETWEEN ? AND ? $branchCond $centerCond
      GROUP BY dcs.LoanID, dcs.BorrowerName, dcs.CoBorrowerName
      ORDER BY dcs.BorrowerName
    ");
    $stmt->execute($params);
    $clientRows = $stmt->fetchAll();
} elseif ($branchId) {
    $branchStmt = $pdo->prepare("SELECT BranchName FROM Branch WHERE BranchID = ?");
    $branchStmt->execute([$branchId]);
    $branchName = $branchStmt->fetchColumn();

    $stmt = $pdo->prepare("
      SELECT dcs.CenterID, c.CenterName,
             COUNT(DISTINCT dcs.LoanID) AS ClientCount,
             SUM(dcs.EMIAmount) AS DueAmount, COALESCE(SUM(dcs.CollectionAmount),0) AS CollectedAmount
      FROM Daily_Collection_Sheet dcs
      JOIN Center c ON dcs.CenterID = c.CenterID
      WHERE dcs.CollectionDueDate BETWEEN ? AND ? $branchCond
      GROUP BY dcs.CenterID, c.CenterName
      ORDER BY c.CenterName
    ");
    $stmt->execute($params);
    $centerRows = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
      SELECT dcs.BranchID, b.BranchName,
             COUNT(DISTINCT dcs.LoanID) AS ClientCount,
             SUM(dcs.EMIAmount) AS DueAmount, COALESCE(SUM(dcs.CollectionAmount),0) AS CollectedAmount
      FROM Daily_Collection_Sheet dcs
      JOIN Branch b ON dcs.BranchID = b.BranchID
      WHERE dcs.CollectionDueDate BETWEEN ? AND ?
      GROUP BY dcs.BranchID, b.BranchName
      ORDER BY b.BranchName
    ");
    $stmt->execute($params);
    $branchRows = $stmt->fetchAll();
}

$periodLabels = ['today' => 'Today', 'yesterday' => 'Yesterday', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom Range'];
?>
<style>
.collection-card { display: block; text-decoration: none; color: inherit; }
.collection-card:hover .card-x { border-color: var(--navy); box-shadow: var(--shadow-md); transform: translateY(-2px); }
.collection-card .card-x { transition: box-shadow .15s, transform .15s, border-color .15s; height: 100%; }
.coll-name { font-weight: 700; font-size: 15px; margin-bottom: 12px; }
.coll-metrics { display: flex; gap: 16px; margin-bottom: 12px; flex-wrap: wrap; }
.coll-metrics .m-num { font-size: 16px; font-weight: 800; }
.coll-metrics .m-lbl { font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; font-weight: 700; }
.coll-progress-track { height: 8px; background: var(--border); border-radius: 4px; overflow: hidden; }
.coll-progress-fill { height: 100%; background: linear-gradient(90deg, var(--teal), #14a9ab); }
.coll-progress-row { display: flex; align-items: center; gap: 10px; }
.coll-progress-row .pct { font-size: 12px; font-weight: 700; color: var(--text-muted); white-space: nowrap; }
.coll-balance { margin-top: 10px; font-size: 12px; color: var(--text-muted); }
.coll-balance strong { color: var(--danger); }
.period-tabs { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 14px; }
.crumb-nav { font-size: 13px; color: var(--text-muted); margin-bottom: 16px; }
.crumb-nav a { color: var(--navy); font-weight: 600; text-decoration: none; }
.crumb-nav a:hover { text-decoration: underline; }
.view-toggle { display: inline-flex; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 3px; gap: 2px; }
.view-toggle-btn { display: inline-block; padding: 7px 14px; font-size: 13px; font-weight: 600; color: var(--text-muted); border-radius: 7px; }
.view-toggle-btn.active { background: var(--card-bg); color: var(--navy); box-shadow: var(--shadow-sm); }
.view-toggle-btn:hover { color: var(--navy); }
</style>

<div class="period-tabs">
  <div class="stage-tabs" style="margin-bottom:0;">
    <?php foreach (['today', 'yesterday', 'week', 'month'] as $p): ?>
      <a class="stage-tab <?= $period === $p ? 'active' : '' ?>" href="<?= collectionUrl(['period' => $p]) ?>"><?= $periodLabels[$p] ?></a>
    <?php endforeach; ?>
    <a class="stage-tab <?= $period === 'custom' ? 'active' : '' ?>" href="#customRange" data-bs-toggle="collapse"><?= $periodLabels['custom'] ?></a>
  </div>
</div>

<div class="collapse <?= $period === 'custom' ? 'show' : '' ?>" id="customRange">
  <form method="GET" class="card-x mb-3 d-flex gap-3 align-items-end flex-wrap" style="padding:16px;">
    <input type="hidden" name="period" value="custom">
    <input type="hidden" name="branch_id" value="<?= $branchId ?: '' ?>">
    <input type="hidden" name="center_id" value="<?= $centerId ?: '' ?>">
    <div>
      <label class="form-label">From</label>
      <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($from) ?>" required>
    </div>
    <div>
      <label class="form-label">To</label>
      <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($to) ?>" required>
    </div>
    <button class="btn-navy" type="submit">Apply</button>
  </form>
</div>

<div class="crumb-nav">
  <a href="<?= collectionUrl(['branch_id' => 0, 'center_id' => 0]) ?>">All Branches</a>
  <?php if ($branchName): ?>
    &nbsp;▸&nbsp;
    <?php if ($centerName): ?><a href="<?= collectionUrl(['branch_id' => $branchId, 'center_id' => 0]) ?>"><?= htmlspecialchars($branchName) ?></a><?php else: ?><strong><?= htmlspecialchars($branchName) ?></strong><?php endif; ?>
  <?php endif; ?>
  <?php if ($centerName): ?>&nbsp;▸&nbsp;<strong><?= htmlspecialchars($centerName) ?></strong><?php endif; ?>
  &nbsp;·&nbsp;<span><?= $from === $to ? fdate($from) : (fdate($from) . ' – ' . fdate($to)) ?></span>
</div>

<?php if (!$centerId): ?>
<div class="d-flex justify-content-end mb-3">
  <div class="view-toggle">
    <a href="<?= collectionUrl(['view' => 'card']) ?>" class="view-toggle-btn <?= $viewMode==='card'?'active':'' ?>"><i class="bi bi-grid-3x3-gap-fill"></i> Card</a>
    <a href="<?= collectionUrl(['view' => 'table']) ?>" class="view-toggle-btn <?= $viewMode==='table'?'active':'' ?>"><i class="bi bi-table"></i> Table</a>
  </div>
</div>
<?php endif; ?>

<!-- SUMMARY -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy"><i class="bi bi-people-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($summary['ClientCount']) ?></div>
        <div class="lbl">Clients Due</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold"><i class="bi bi-cash-coin"></i></div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= money($sDue) ?></div>
        <div class="lbl">Collection Due</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal"><i class="bi bi-check-circle-fill"></i></div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= money($sCollected) ?></div>
        <div class="lbl"><?= $sPct ?>% Collected</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= money($sBalance) ?></div>
        <div class="lbl">Balance Pending</div>
      </div>
    </div>
  </div>
</div>

<?php if ($centerId): ?>

  <!-- CLIENT LEVEL -->
  <div class="card-x">
    <div class="section-title">Clients Due — <?= htmlspecialchars($centerName) ?></div>
    <?php if ($clientRows): ?>
    <div class="table-scroll">
    <table class="table-x">
      <tr><th>Client</th><th>Installments</th><th>Due</th><th>Collected</th><th>Balance</th><th>Progress</th><th>Status</th><th></th></tr>
      <?php foreach ($clientRows as $r):
        $due = (float)$r['DueAmount']; $coll = (float)$r['CollectedAmount']; $bal = max(0, $due - $coll);
        $pct = $due > 0 ? min(100, round($coll / $due * 100)) : ($coll > 0 ? 100 : 0);
        $status = $r['HasPreclose'] ? 'Preclosed' : ($bal <= 0 ? 'Paid' : ($coll > 0 ? 'Partial' : 'Pending'));
      ?>
      <tr>
        <td>
          <?= htmlspecialchars(trim($r['BorrowerName'])) ?>
          <?php if (trim($r['CoBorrowerName'] ?? '')): ?><div style="font-size:11px;color:var(--text-muted);">Co: <?= htmlspecialchars(trim($r['CoBorrowerName'])) ?></div><?php endif; ?>
        </td>
        <td><?= (int)$r['InstallmentCount'] ?></td>
        <td><?= money($due) ?></td>
        <td><?= money($coll) ?></td>
        <td><?= $bal > 0 ? money($bal) : '-' ?></td>
        <td style="min-width:140px;">
          <div class="coll-progress-row">
            <div class="coll-progress-track" style="flex:1;"><div class="coll-progress-fill" style="width:<?= $pct ?>%;"></div></div>
            <span class="pct"><?= $pct ?>%</span>
          </div>
        </td>
        <td><?= stage_badge($status) ?></td>
        <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <?php else: ?>
      <div class="empty-state">No collection due at this center for the selected period.</div>
    <?php endif; ?>
  </div>

<?php elseif ($branchId): ?>

  <!-- CENTER LEVEL -->
  <?php if ($centerRows): ?>
  <?php if ($viewMode === 'table'): ?>
  <div class="card-x">
    <div class="table-scroll">
      <table class="table-x">
        <tr><th>Center</th><th>Clients</th><th>Due</th><th>Collected</th><th>Balance</th><th>Progress</th><th></th></tr>
        <?php foreach ($centerRows as $c):
          $due = (float)$c['DueAmount']; $coll = (float)$c['CollectedAmount']; $bal = $due - $coll;
          $pct = $due > 0 ? round($coll / $due * 100) : 0;
        ?>
        <tr>
          <td><strong><i class="bi bi-houses-fill"></i> <?= htmlspecialchars($c['CenterName']) ?></strong></td>
          <td><?= number_format($c['ClientCount']) ?></td>
          <td><?= money($due) ?></td>
          <td><?= money($coll) ?></td>
          <td><?= money($bal) ?></td>
          <td style="min-width:140px;">
            <div class="coll-progress-row">
              <div class="coll-progress-track" style="flex:1;"><div class="coll-progress-fill" style="width:<?= min(100, $pct) ?>%;"></div></div>
              <span class="pct"><?= $pct ?>%</span>
            </div>
          </td>
          <td><a href="<?= collectionUrl(['branch_id' => $branchId, 'center_id' => $c['CenterID']]) ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach ($centerRows as $c):
      $due = (float)$c['DueAmount']; $coll = (float)$c['CollectedAmount']; $bal = $due - $coll;
      $pct = $due > 0 ? round($coll / $due * 100) : 0;
    ?>
    <div class="col-md-6 col-lg-4">
      <a class="collection-card" href="<?= collectionUrl(['branch_id' => $branchId, 'center_id' => $c['CenterID']]) ?>">
        <div class="card-x">
          <div class="coll-name"><i class="bi bi-houses-fill"></i> <?= htmlspecialchars($c['CenterName']) ?></div>
          <div class="coll-metrics">
            <div><div class="m-num"><?= number_format($c['ClientCount']) ?></div><div class="m-lbl">Clients</div></div>
            <div><div class="m-num"><?= money($due) ?></div><div class="m-lbl">Due</div></div>
            <div><div class="m-num"><?= money($coll) ?></div><div class="m-lbl">Collected</div></div>
          </div>
          <div class="coll-progress-row">
            <div class="coll-progress-track" style="flex:1;"><div class="coll-progress-fill" style="width:<?= min(100, $pct) ?>%;"></div></div>
            <span class="pct"><?= $pct ?>%</span>
          </div>
          <div class="coll-balance">Balance: <strong><?= money($bal) ?></strong></div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php else: ?>
    <div class="card-x empty-state">No centers have collection due in <?= htmlspecialchars($branchName) ?> for the selected period.</div>
  <?php endif; ?>

<?php else: ?>

  <!-- BRANCH LEVEL -->
  <?php if ($branchRows): ?>
  <?php if ($viewMode === 'table'): ?>
  <div class="card-x">
    <div class="table-scroll">
      <table class="table-x">
        <tr><th>Branch</th><th>Clients</th><th>Due</th><th>Collected</th><th>Balance</th><th>Progress</th><th></th></tr>
        <?php foreach ($branchRows as $b):
          $due = (float)$b['DueAmount']; $coll = (float)$b['CollectedAmount']; $bal = $due - $coll;
          $pct = $due > 0 ? round($coll / $due * 100) : 0;
        ?>
        <tr>
          <td><strong><i class="bi bi-building"></i> <?= htmlspecialchars($b['BranchName']) ?></strong></td>
          <td><?= number_format($b['ClientCount']) ?></td>
          <td><?= money($due) ?></td>
          <td><?= money($coll) ?></td>
          <td><?= money($bal) ?></td>
          <td style="min-width:140px;">
            <div class="coll-progress-row">
              <div class="coll-progress-track" style="flex:1;"><div class="coll-progress-fill" style="width:<?= min(100, $pct) ?>%;"></div></div>
              <span class="pct"><?= $pct ?>%</span>
            </div>
          </td>
          <td><a href="<?= collectionUrl(['branch_id' => $b['BranchID']]) ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach ($branchRows as $b):
      $due = (float)$b['DueAmount']; $coll = (float)$b['CollectedAmount']; $bal = $due - $coll;
      $pct = $due > 0 ? round($coll / $due * 100) : 0;
    ?>
    <div class="col-md-6 col-lg-4">
      <a class="collection-card" href="<?= collectionUrl(['branch_id' => $b['BranchID']]) ?>">
        <div class="card-x">
          <div class="coll-name"><i class="bi bi-building"></i> <?= htmlspecialchars($b['BranchName']) ?></div>
          <div class="coll-metrics">
            <div><div class="m-num"><?= number_format($b['ClientCount']) ?></div><div class="m-lbl">Clients</div></div>
            <div><div class="m-num"><?= money($due) ?></div><div class="m-lbl">Due</div></div>
            <div><div class="m-num"><?= money($coll) ?></div><div class="m-lbl">Collected</div></div>
          </div>
          <div class="coll-progress-row">
            <div class="coll-progress-track" style="flex:1;"><div class="coll-progress-fill" style="width:<?= min(100, $pct) ?>%;"></div></div>
            <span class="pct"><?= $pct ?>%</span>
          </div>
          <div class="coll-balance">Balance: <strong><?= money($bal) ?></strong></div>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php else: ?>
    <div class="card-x empty-state">No collection due anywhere for the selected period.</div>
  <?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
