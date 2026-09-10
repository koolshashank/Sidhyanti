<?php
$pageTitle = 'Reports';
$crumb = 'Collections, portfolio & arrears analytics';
$active = 'reports';
require_once __DIR__ . '/includes/header.php';

$collectionByMonth = $pdo->query("SELECT CollectionYear, CollectionMonth, MonthLabel, TotalCollected FROM vw_Monthly_Collections ORDER BY CollectionYear, CollectionMonth")->fetchAll();

$disbursementByMonth = $pdo->query("
  SELECT YEAR(DisbursementDate) AS DYear, MONTH(DisbursementDate) AS DMonth,
         SUM(COALESCE(LoanAmountSanctioned, LoanAmountApplied)) AS TotalDisbursed
  FROM Loan WHERE DisbursementDate IS NOT NULL
  GROUP BY YEAR(DisbursementDate), MONTH(DisbursementDate)
")->fetchAll();

// Merge collections + disbursement into one chronological series keyed by year-month,
// since they come from two different sources (a reporting view vs. the Loan table).
$collByKey = [];
foreach ($collectionByMonth as $r) {
    $key = $r['CollectionYear'] . '-' . str_pad($r['CollectionMonth'], 2, '0', STR_PAD_LEFT);
    $collByKey[$key] = ['label' => $r['MonthLabel'], 'collected' => (float)$r['TotalCollected']];
}
$disbByKey = [];
foreach ($disbursementByMonth as $r) {
    $key = $r['DYear'] . '-' . str_pad($r['DMonth'], 2, '0', STR_PAD_LEFT);
    $disbByKey[$key] = (float)$r['TotalDisbursed'];
}
$allMonthKeys = array_unique(array_merge(array_keys($collByKey), array_keys($disbByKey)));
sort($allMonthKeys);
$monthlyTrend = [];
foreach ($allMonthKeys as $key) {
    [$ky, $km] = explode('-', $key);
    $label = $collByKey[$key]['label'] ?? date('M Y', mktime(0, 0, 0, (int)$km, 1, (int)$ky));
    $monthlyTrend[] = ['label' => $label, 'collected' => $collByKey[$key]['collected'] ?? 0, 'disbursed' => $disbByKey[$key] ?? 0];
}
$monthlyTrend = array_slice($monthlyTrend, -6);

$officerPerf = $pdo->query("SELECT * FROM vw_Officer_Performance WHERE TotalLoansSourced > 0 ORDER BY TotalAmountSourced DESC")->fetchAll();

$overdueByBranch = $pdo->query("
  SELECT b.BranchName AS name, COUNT(*) AS overdue_count, COALESCE(SUM(o.EMIAmount),0) AS overdue_amount
  FROM vw_Overdue_Installments o
  JOIN Branch b ON o.BranchName = b.BranchName
  GROUP BY b.BranchName
  ORDER BY overdue_amount DESC
")->fetchAll();

$portfolioQuality = $pdo->query("
  SELECT CASE WHEN IsPaid = 1 THEN 'Paid'
              WHEN DueDate < CAST(GETDATE() AS DATE) THEN 'Overdue'
              ELSE 'Pending' END AS status, COUNT(*) AS c
  FROM Repayment_Schedule
  GROUP BY CASE WHEN IsPaid = 1 THEN 'Paid'
                WHEN DueDate < CAST(GETDATE() AS DATE) THEN 'Overdue'
                ELSE 'Pending' END
")->fetchAll();

$avgTAT = $pdo->query("SELECT AVG(CAST(DaysToDisburse AS FLOAT)) FROM vw_Loan_TAT")->fetchColumn();
$lifetimeCollected = (float)$pdo->query("SELECT COALESCE(SUM(TotalCollected),0) FROM Collection")->fetchColumn();
$activeOfficerCount = count($officerPerf);

/* ---------------------------------------------------------------------
   ARREARS — aging bucket breakdown, computed from the oldest unpaid
   installment per currently-active loan (same "flow into arrears"
   logic used on Portfolio Health, extended with finer buckets here).
--------------------------------------------------------------------- */
$arrearBucketSql = "
  ;WITH UnpaidAgg AS (
    SELECT rs.LoanID, MIN(rs.DueDate) AS OldestUnpaidDue, SUM(rs.PrincipalDue) AS Outstanding
    FROM Repayment_Schedule rs WHERE rs.IsPaid = 0 GROUP BY rs.LoanID
  ),
  LoanRisk AS (
    SELECT l.LoanID, u.Outstanding, DATEDIFF(DAY, u.OldestUnpaidDue, CAST(GETDATE() AS DATE)) AS DaysOverdue
    FROM Loan l
    JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
    JOIN UnpaidAgg u ON u.LoanID = l.LoanID
    WHERE stg.StageName = 'Active'
  )
  SELECT
    CASE
      WHEN DaysOverdue <= 0 THEN 'Current'
      WHEN DaysOverdue BETWEEN 1 AND 30 THEN '1-30 days'
      WHEN DaysOverdue BETWEEN 31 AND 60 THEN '31-60 days'
      WHEN DaysOverdue BETWEEN 61 AND 90 THEN '61-90 days'
      WHEN DaysOverdue BETWEEN 91 AND 180 THEN '91-180 days'
      ELSE '180+ days'
    END AS Bucket,
    COUNT(*) AS LoanCount, SUM(Outstanding) AS OutstandingAmount
  FROM LoanRisk
  GROUP BY CASE
      WHEN DaysOverdue <= 0 THEN 'Current'
      WHEN DaysOverdue BETWEEN 1 AND 30 THEN '1-30 days'
      WHEN DaysOverdue BETWEEN 31 AND 60 THEN '31-60 days'
      WHEN DaysOverdue BETWEEN 61 AND 90 THEN '61-90 days'
      WHEN DaysOverdue BETWEEN 91 AND 180 THEN '91-180 days'
      ELSE '180+ days'
    END
";
$bucketRows = $pdo->query($arrearBucketSql)->fetchAll();
$bucketOrder = ['Current', '1-30 days', '31-60 days', '61-90 days', '91-180 days', '180+ days'];
$bucketByName = [];
foreach ($bucketRows as $r) { $bucketByName[$r['Bucket']] = $r; }
$totalActiveLoansAtRisk = array_sum(array_map(fn($b) => $bucketByName[$b]['LoanCount'] ?? 0, $bucketOrder));
$grossOutstanding = array_sum(array_map(fn($b) => (float)($bucketByName[$b]['OutstandingAmount'] ?? 0), $bucketOrder));
$totalParLoans = $totalActiveLoansAtRisk - ($bucketByName['Current']['LoanCount'] ?? 0);
$totalParAmount = $grossOutstanding - (float)($bucketByName['Current']['OutstandingAmount'] ?? 0);
$parPct = $grossOutstanding > 0 ? round($totalParAmount / $grossOutstanding * 100, 1) : 0;

/* ---------------------------------------------------------------------
   NEW ARREAR CLIENTS — loans whose very first missed installment (the
   start of their current unbroken default streak) falls inside the
   selected window. This is the "flow into arrears" metric: a client
   who wasn't in arrears before this window and has just entered it.
--------------------------------------------------------------------- */
$arrPeriod = $_GET['arr_period'] ?? '30';
if (!in_array($arrPeriod, ['7', '15', '30', '60', '90', 'custom'], true)) $arrPeriod = '30';

$todayDt = new DateTime('today');
if ($arrPeriod === 'custom') {
    $arrFrom = $_GET['arr_from'] ?? (clone $todayDt)->modify('-30 days')->format('Y-m-d');
    $arrTo = $_GET['arr_to'] ?? $todayDt->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrFrom)) $arrFrom = (clone $todayDt)->modify('-30 days')->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrTo)) $arrTo = $todayDt->format('Y-m-d');
    if ($arrTo < $arrFrom) { [$arrFrom, $arrTo] = [$arrTo, $arrFrom]; }
} else {
    $arrFrom = (clone $todayDt)->modify('-' . (int)$arrPeriod . ' days')->format('Y-m-d');
    $arrTo = $todayDt->format('Y-m-d');
}

function reportsUrl($overrides = []) {
    global $arrPeriod, $arrFrom, $arrTo;
    $effPeriod = $overrides['arr_period'] ?? $arrPeriod;
    $q = ['arr_period' => $effPeriod];
    if ($effPeriod === 'custom') {
        $q['arr_from'] = $overrides['arr_from'] ?? $arrFrom;
        $q['arr_to'] = $overrides['arr_to'] ?? $arrTo;
    }
    return 'reports.php?' . http_build_query($q) . '#new-arrears';
}

$newArrearSql = "
  ;WITH UnpaidAgg AS (
    SELECT rs.LoanID, MIN(rs.DueDate) AS FirstArrearDate, SUM(rs.PrincipalDue) AS Outstanding
    FROM Repayment_Schedule rs WHERE rs.IsPaid = 0 GROUP BY rs.LoanID
  )
  SELECT l.LoanID, l.LoanNo, m.FirstName, m.LastName, m.MobileNo, b.BranchName, c.CenterName,
         officer.FullName AS OfficerName, u.FirstArrearDate, u.Outstanding,
         DATEDIFF(DAY, u.FirstArrearDate, CAST(GETDATE() AS DATE)) AS DaysOverdue
  FROM Loan l
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  JOIN UnpaidAgg u ON u.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Center c ON l.CenterID = c.CenterID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  WHERE stg.StageName = 'Active'
    AND u.FirstArrearDate < CAST(GETDATE() AS DATE)
    AND u.FirstArrearDate BETWEEN ? AND ?
  ORDER BY u.FirstArrearDate DESC
";
$newArrearStmt = $pdo->prepare($newArrearSql);
$newArrearStmt->execute([$arrFrom, $arrTo]);
$newArrearClients = $newArrearStmt->fetchAll();
$newArrearCount = count($newArrearClients);
$newArrearAmount = array_sum(array_map(fn($r) => (float)$r['Outstanding'], $newArrearClients));

$arrPeriodLabels = ['7' => 'Last 7 Days', '15' => 'Last 15 Days', '30' => 'Last 30 Days', '60' => 'Last 60 Days', '90' => 'Last 90 Days', 'custom' => 'Custom Range'];
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy">⏱️</div>
      <div class="stat-body">
        <div class="num"><?= $avgTAT ? round($avgTAT, 1) : '-' ?> days</div>
        <div class="lbl">Avg. Turn-Around-Time</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal">💰</div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= money($lifetimeCollected) ?></div>
        <div class="lbl">Lifetime Collected</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold">👤</div>
      <div class="stat-body">
        <div class="num"><?= number_format($activeOfficerCount) ?></div>
        <div class="lbl">Active Loan Officers</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft">⚠️</div>
      <div class="stat-body">
        <div class="num"><?= $parPct ?>%</div>
        <div class="lbl">Portfolio at Risk</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card-x mb-3">
      <div class="section-title">Monthly Collections vs Disbursement</div>
      <div style="height:190px;"><canvas id="collChart"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card-x mb-3">
      <div class="section-title">Repayment Status Mix</div>
      <div style="height:190px;"><canvas id="qualChart"></canvas></div>
    </div>
  </div>
</div>

<!-- ARREARS AGING BUCKETS -->
<div class="card-x mb-3">
  <div class="section-title">Arrears — Aging Buckets <span class="badge badge-danger"><?= number_format($totalParLoans) ?> loans at risk</span></div>
  <div class="table-scroll">
  <table class="table-x">
    <tr><th>Bucket</th><th>Loans</th><th>Outstanding Amount</th><th>% of Portfolio</th><th></th></tr>
    <?php foreach ($bucketOrder as $bName):
      $row = $bucketByName[$bName] ?? ['LoanCount' => 0, 'OutstandingAmount' => 0];
      $bPct = $grossOutstanding > 0 ? round($row['OutstandingAmount'] / $grossOutstanding * 100, 1) : 0;
      $barColor = $bName === 'Current' ? '#1f9d55' : ($bName === '1-30 days' ? '#e2a13a' : ($bName === '31-60 days' ? '#e2833a' : ($bName === '61-90 days' ? '#d66a45' : ($bName === '91-180 days' ? '#c43d3d' : '#8a1f1f'))));
    ?>
    <tr>
      <td><?= $bName === 'Current' ? '<span class="badge badge-success">Current</span>' : '<span class="badge badge-warning">' . $bName . '</span>' ?></td>
      <td><?= number_format($row['LoanCount']) ?></td>
      <td style="font-weight:700;"><?= money($row['OutstandingAmount']) ?></td>
      <td><?= $bPct ?>%</td>
      <td style="min-width:140px;">
        <div class="coll-progress-track" style="height:8px;background:var(--border);border-radius:4px;overflow:hidden;">
          <div style="height:100%;width:<?= min(100, $bPct) ?>%;background:<?= $barColor ?>;"></div>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>

<!-- NEW ARREAR CLIENTS -->
<div class="card-x mb-3" id="new-arrears">
  <div class="section-title">
    New Arrear Clients
    <span style="font-weight:400;font-size:12px;color:var(--text-muted);">— clients entering arrears for the first time in the selected window</span>
  </div>
  <div class="stage-tabs" style="margin:14px 0;">
    <?php foreach (['7', '15', '30', '60', '90'] as $p): ?>
      <a class="stage-tab <?= $arrPeriod === $p ? 'active' : '' ?>" href="<?= reportsUrl(['arr_period' => $p]) ?>"><?= $arrPeriodLabels[$p] ?></a>
    <?php endforeach; ?>
    <a class="stage-tab <?= $arrPeriod === 'custom' ? 'active' : '' ?>" href="#customArrRange" data-bs-toggle="collapse">Custom Range</a>
  </div>
  <div class="collapse <?= $arrPeriod === 'custom' ? 'show' : '' ?>" id="customArrRange">
    <form method="GET" class="d-flex gap-3 align-items-end flex-wrap mb-3" style="background:#f7f9fc;border-radius:10px;padding:14px;">
      <input type="hidden" name="arr_period" value="custom">
      <div>
        <label class="form-label">From</label>
        <input type="date" class="form-control" name="arr_from" value="<?= htmlspecialchars($arrFrom) ?>" required>
      </div>
      <div>
        <label class="form-label">To</label>
        <input type="date" class="form-control" name="arr_to" value="<?= htmlspecialchars($arrTo) ?>" required>
      </div>
      <button class="btn-navy" type="submit">Apply</button>
    </form>
  </div>

  <div style="display:flex;gap:24px;flex-wrap:wrap;background:#fdeeee;border:1px solid #f6caca;border-radius:10px;padding:14px 18px;margin-bottom:16px;">
    <div><div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;">New Arrear Clients</div><div style="font-weight:800;font-size:19px;color:var(--danger);"><?= number_format($newArrearCount) ?></div></div>
    <div><div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Amount Newly at Risk</div><div style="font-weight:800;font-size:19px;color:var(--danger);"><?= money($newArrearAmount) ?></div></div>
    <div><div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Window</div><div style="font-weight:800;font-size:19px;"><?= fdate($arrFrom) ?> – <?= fdate($arrTo) ?></div></div>
  </div>

  <?php if ($newArrearClients): ?>
  <div class="table-scroll">
  <table class="table-x">
    <tr><th>Loan No</th><th>Client</th><th>Branch</th><th>Center</th><th>Officer</th><th>First Arrear Date</th><th>Days Overdue</th><th>Amount</th></tr>
    <?php foreach ($newArrearClients as $r): ?>
    <tr>
      <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
      <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?></td>
      <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($r['OfficerName'] ?? 'Unassigned') ?></td>
      <td><?= fdate($r['FirstArrearDate']) ?></td>
      <td><span class="badge badge-<?= $r['DaysOverdue'] > 90 ? 'danger' : ($r['DaysOverdue'] > 30 ? 'warning' : 'secondary') ?>"><?= (int)$r['DaysOverdue'] ?> days</span></td>
      <td style="font-weight:700;color:var(--danger);"><?= money($r['Outstanding']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php else: ?>
    <div class="empty-state">No clients newly entered arrears in this window 🎉</div>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card-x">
      <div class="section-title">Loan Officer Performance</div>
      <div class="table-scroll">
      <table class="table-x">
        <tr><th>Officer</th><th>Loans</th><th>Total Amount</th><th>Active</th></tr>
        <?php foreach ($officerPerf as $o): ?>
        <tr><td><?= htmlspecialchars($o['FullName']) ?></td><td><?= $o['TotalLoansSourced'] ?></td><td><?= money($o['TotalAmountSourced']) ?></td><td><?= $o['ActiveLoans'] ?></td></tr>
        <?php endforeach; ?>
      </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card-x">
      <div class="section-title">Overdue by Branch</div>
      <?php if ($overdueByBranch): ?>
      <table class="table-x">
        <tr><th>Branch</th><th>Overdue Installments</th><th>Amount at Risk</th></tr>
        <?php foreach ($overdueByBranch as $o): ?>
        <tr><td><?= htmlspecialchars($o['name']) ?></td><td><?= number_format($o['overdue_count']) ?></td><td><?= money($o['overdue_amount']) ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <div class="empty-state">No overdue installments across branches 🎉</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const trend = <?= json_encode($monthlyTrend) ?>;
new Chart(document.getElementById('collChart'), {
  type: 'bar',
  data: {
    labels: trend.map(t=>t.label),
    datasets: [
      { label: 'Disbursed (₹)', data: trend.map(t=>t.disbursed), backgroundColor: '#c8952c', borderRadius: 5, maxBarThickness: 22 },
      { label: 'Collected (₹)', data: trend.map(t=>t.collected), backgroundColor: '#0b2545', borderRadius: 5, maxBarThickness: 22 },
    ]
  },
  options: {
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
    scales: { y: { beginAtZero: true, grid: { color: '#eef1f6' } }, x: { grid: { display: false } } }
  }
});
const qual = <?= json_encode($portfolioQuality) ?>;
new Chart(document.getElementById('qualChart'), {
  type: 'pie',
  data: { labels: qual.map(q=>q.status), datasets: [{ data: qual.map(q=>q.c), backgroundColor: ['#1f9d55','#e2a13a','#d64545','#0f8b8d'] }] },
  options: { maintainAspectRatio: false, plugins: { legend: { position:'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
