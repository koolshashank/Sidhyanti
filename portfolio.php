<?php
$pageTitle = 'Portfolio Health';
$crumb = 'Accurate portfolio quality, arrears, preclosure & default tracking';
$active = 'portfolio';
require_once __DIR__ . '/includes/header.php';

$branchFilter = $_GET['branch'] ?? '';
$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$branchWhere = $branchFilter ? "AND l.BranchID = " . (int)$branchFilter : "";

/* ---------------------------------------------------------------------
   1. TOP-LINE PORTFOLIO SIZE
--------------------------------------------------------------------- */
$activeClients = $pdo->query("
  SELECT COUNT(DISTINCT l.MemberID) FROM Loan l
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE stg.StageName = 'Active' $branchWhere
")->fetchColumn();

$activeLoanCount = $pdo->query("
  SELECT COUNT(*) FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE stg.StageName = 'Active' $branchWhere
")->fetchColumn();

$grossLoanPortfolio = $pdo->query("
  SELECT COALESCE(SUM(rs.PrincipalDue),0)
  FROM Repayment_Schedule rs
  JOIN Loan l ON rs.LoanID = l.LoanID
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE rs.IsPaid = 0 AND stg.StageName = 'Active' $branchWhere
")->fetchColumn();

$lifetimeDisbursed = $pdo->query("
  SELECT COALESCE(SUM(l.LoanAmountSanctioned),0) FROM Loan l
  WHERE l.DisbursementDate IS NOT NULL $branchWhere
")->fetchColumn();

$lifetimeCollected = $pdo->query("
  SELECT COALESCE(SUM(c.TotalCollected),0) FROM Collection c
  JOIN Loan l ON c.LoanID = l.LoanID WHERE 1=1 $branchWhere
")->fetchColumn();

/* ---------------------------------------------------------------------
   2. PORTFOLIO AT RISK (PAR) — aging buckets, calculated from the
      oldest unpaid installment per active loan. This is the standard
      microfinance risk metric and is only possible now because real
      Collection transactions + IsPaid flags exist.
--------------------------------------------------------------------- */
$parSql = "
  ;WITH UnpaidAgg AS (
    SELECT rs.LoanID, MIN(rs.DueDate) AS OldestUnpaidDue, SUM(rs.PrincipalDue) AS Outstanding
    FROM Repayment_Schedule rs WHERE rs.IsPaid = 0 GROUP BY rs.LoanID
  ),
  LoanRisk AS (
    SELECT l.LoanID, l.BranchID, l.MemberID, u.Outstanding,
           DATEDIFF(DAY, u.OldestUnpaidDue, CAST(GETDATE() AS DATE)) AS DaysOverdue
    FROM Loan l
    JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
    JOIN UnpaidAgg u ON u.LoanID = l.LoanID
    WHERE stg.StageName = 'Active' $branchWhere
  )
  SELECT
    CASE
      WHEN DaysOverdue <= 0 THEN 'Current (on-time)'
      WHEN DaysOverdue BETWEEN 1 AND 30 THEN 'PAR 1-30'
      WHEN DaysOverdue BETWEEN 31 AND 60 THEN 'PAR 31-60'
      WHEN DaysOverdue BETWEEN 61 AND 90 THEN 'PAR 61-90'
      ELSE 'PAR 90+'
    END AS Bucket,
    COUNT(*) AS LoanCount,
    SUM(Outstanding) AS OutstandingAmount
  FROM LoanRisk
  GROUP BY CASE
      WHEN DaysOverdue <= 0 THEN 'Current (on-time)'
      WHEN DaysOverdue BETWEEN 1 AND 30 THEN 'PAR 1-30'
      WHEN DaysOverdue BETWEEN 31 AND 60 THEN 'PAR 31-60'
      WHEN DaysOverdue BETWEEN 61 AND 90 THEN 'PAR 61-90'
      ELSE 'PAR 90+'
    END
";
$parRows = $pdo->query($parSql)->fetchAll();
$parByBucket = [];
foreach ($parRows as $r) { $parByBucket[$r['Bucket']] = $r; }
$bucketOrder = ['Current (on-time)','PAR 1-30','PAR 31-60','PAR 61-90','PAR 90+'];
$totalAtRiskAmount = 0; $totalAtRiskLoans = 0;
foreach (['PAR 1-30','PAR 31-60','PAR 61-90','PAR 90+'] as $b) {
    $totalAtRiskAmount += $parByBucket[$b]['OutstandingAmount'] ?? 0;
    $totalAtRiskLoans += $parByBucket[$b]['LoanCount'] ?? 0;
}
$par30Amount = ($parByBucket['PAR 1-30']['OutstandingAmount'] ?? 0) + ($parByBucket['PAR 31-60']['OutstandingAmount'] ?? 0) + ($parByBucket['PAR 61-90']['OutstandingAmount'] ?? 0) + ($parByBucket['PAR 90+']['OutstandingAmount'] ?? 0);
$par30Pct = $grossLoanPortfolio > 0 ? round(($par30Amount / $grossLoanPortfolio) * 100, 1) : 0;
$defaultAmount = $parByBucket['PAR 90+']['OutstandingAmount'] ?? 0;
$defaultLoanCount = $parByBucket['PAR 90+']['LoanCount'] ?? 0;

/* ---------------------------------------------------------------------
   3. PRECLOSED & WRITTEN-OFF (explicit settlement outcomes)
--------------------------------------------------------------------- */
$preclosed = $pdo->query("
  SELECT COUNT(*) AS cnt, COALESCE(SUM(l.LoanAmountSanctioned),0) AS amt
  FROM Loan l JOIN Settlement_Type_Master st ON l.SettlementTypeID = st.SettlementTypeID
  WHERE st.SettlementTypeName = 'Preclose' $branchWhere
")->fetch();

$writtenOff = $pdo->query("
  SELECT COUNT(*) AS cnt, COALESCE(SUM(l.LoanAmountSanctioned),0) AS amt
  FROM Loan l JOIN Settlement_Type_Master st ON l.SettlementTypeID = st.SettlementTypeID
  WHERE st.SettlementTypeName = 'Written Off' $branchWhere
")->fetch();

$closedNormal = $pdo->query("
  SELECT COUNT(*) FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE stg.StageName = 'Closed' $branchWhere
")->fetchColumn();

/* ---------------------------------------------------------------------
   4. CLIENT STATUS BREAKDOWN (for the pie chart)
--------------------------------------------------------------------- */
$onTimeCount = $parByBucket['Current (on-time)']['LoanCount'] ?? 0;
$arrearsCount = $totalAtRiskLoans;
$statusBreakdown = [
    'On-time Active' => $onTimeCount,
    'In Arrears (PAR 1-90)' => $arrearsCount - $defaultLoanCount,
    'Default (PAR 90+)' => $defaultLoanCount,
    'Preclosed' => (int)$preclosed['cnt'],
    'Written Off' => (int)$writtenOff['cnt'],
    'Closed (fully repaid)' => (int)$closedNormal,
];

/* ---------------------------------------------------------------------
   5. BRANCH-WISE PORTFOLIO QUALITY
--------------------------------------------------------------------- */
$branchQualitySql = "
  ;WITH UnpaidAgg AS (
    SELECT rs.LoanID, MIN(rs.DueDate) AS OldestUnpaidDue, SUM(rs.PrincipalDue) AS Outstanding
    FROM Repayment_Schedule rs WHERE rs.IsPaid = 0 GROUP BY rs.LoanID
  )
  SELECT b.BranchID, b.BranchName,
    COUNT(DISTINCT CASE WHEN stg.StageName='Active' THEN l.LoanID END) AS ActiveLoans,
    COALESCE(SUM(CASE WHEN stg.StageName='Active' THEN u.Outstanding END),0) AS Outstanding,
    COALESCE(SUM(CASE WHEN stg.StageName='Active' AND DATEDIFF(DAY,u.OldestUnpaidDue,CAST(GETDATE() AS DATE)) > 0 THEN u.Outstanding END),0) AS AtRiskAmount,
    COUNT(DISTINCT CASE WHEN stg.StageName='Active' AND DATEDIFF(DAY,u.OldestUnpaidDue,CAST(GETDATE() AS DATE)) > 0 THEN l.LoanID END) AS AtRiskLoans
  FROM Branch b
  LEFT JOIN Loan l ON l.BranchID = b.BranchID
  LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  LEFT JOIN UnpaidAgg u ON u.LoanID = l.LoanID
  WHERE b.BranchName NOT LIKE '%HEAD OFFICE%'
  GROUP BY b.BranchID, b.BranchName
  ORDER BY b.BranchName
";
$branchQuality = $pdo->query($branchQualitySql)->fetchAll();

/* ---------------------------------------------------------------------
   6. AT-RISK CLIENT LIST (actionable, worst-first)
--------------------------------------------------------------------- */
$atRiskListSql = "
  ;WITH UnpaidAgg AS (
    SELECT rs.LoanID, MIN(rs.DueDate) AS OldestUnpaidDue, SUM(rs.PrincipalDue) AS Outstanding
    FROM Repayment_Schedule rs WHERE rs.IsPaid = 0 GROUP BY rs.LoanID
  )
  SELECT TOP 30 l.LoanID, l.LoanNo, m.FirstName, m.LastName, m.MobileNo, b.BranchName,
         o.FullName AS OfficerName, u.Outstanding, DATEDIFF(DAY,u.OldestUnpaidDue,CAST(GETDATE() AS DATE)) AS DaysOverdue
  FROM Loan l
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  JOIN UnpaidAgg u ON u.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Staff o ON l.LoanOfficerStaffID = o.StaffID
  WHERE stg.StageName = 'Active' AND u.OldestUnpaidDue < CAST(GETDATE() AS DATE) $branchWhere
  ORDER BY DaysOverdue DESC
";
$atRiskList = $pdo->query($atRiskListSql)->fetchAll();
?>

<div class="filters mb-3">
  <form method="GET">
    <select name="branch" onchange="this.form.submit()">
      <option value="">All Branches</option>
      <?php foreach ($branches as $b): ?>
        <option value="<?= $b['BranchID'] ?>" <?= $branchFilter==$b['BranchID']?'selected':'' ?>><?= htmlspecialchars($b['BranchName']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<!-- TOP-LINE KPIs -->
<div class="row g-3 mb-1">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box bg-navy"><i class="bi bi-people-fill"></i></div>
      <div class="num"><?= number_format($activeClients) ?></div>
      <div class="lbl">Active Clients</div>
      <div class="delta up"><?= number_format($activeLoanCount) ?> active loans</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box bg-gold"><i class="bi bi-briefcase-fill"></i></div>
      <div class="num"><?= money($grossLoanPortfolio) ?></div>
      <div class="lbl">Gross Loan Portfolio</div>
      <div class="delta up">Outstanding on active loans</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box bg-teal"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="num"><?= money($lifetimeDisbursed) ?></div>
      <div class="lbl">Lifetime Disbursed</div>
      <div class="delta up"><?= money($lifetimeCollected) ?> collected</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box bg-danger-soft"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div class="num"><?= $par30Pct ?>%</div>
      <div class="lbl">Portfolio at Risk (PAR)</div>
      <div class="delta down"><?= money($totalAtRiskAmount) ?> across <?= $totalAtRiskLoans ?> loans</div>
    </div>
  </div>
</div>

<!-- RISK / STATUS KPIs -->
<div class="row g-3 mt-1 mb-1">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box" style="background:#e2a13a;"><i class="bi bi-clock"></i></div>
      <div class="num"><?= number_format($arrearsCount) ?></div>
      <div class="lbl">Clients in Arrears</div>
      <div class="delta down">Any overdue installment</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box" style="background:#d64545;"><i class="bi bi-exclamation-octagon-fill"></i></div>
      <div class="num"><?= number_format($defaultLoanCount) ?></div>
      <div class="lbl">In Default (PAR 90+)</div>
      <div class="delta down"><?= money($defaultAmount) ?> at risk</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box bg-navy"><i class="bi bi-check-circle-fill"></i></div>
      <div class="num"><?= number_format($preclosed['cnt']) ?></div>
      <div class="lbl">Preclosed Loans</div>
      <div class="delta up"><?= money($preclosed['amt']) ?> total value</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card">
      <div class="icon-box" style="background:#384158;"><i class="bi bi-x-lg"></i></div>
      <div class="num"><?= number_format($writtenOff['cnt']) ?></div>
      <div class="lbl">Written Off</div>
      <div class="delta down"><?= money($writtenOff['amt']) ?> total value</div>
    </div>
  </div>
</div>

<!-- PAR AGING TABLE -->
<div class="row g-3 mt-1">
  <div class="col-lg-7">
    <div class="card-x">
      <div class="section-title">Portfolio at Risk — Aging Breakdown</div>
      <table class="table-x">
        <tr><th>Bucket</th><th>Loans</th><th>Outstanding Amount</th><th>% of GLP</th></tr>
        <?php foreach ($bucketOrder as $b):
          $row = $parByBucket[$b] ?? ['LoanCount'=>0,'OutstandingAmount'=>0];
          $pct = $grossLoanPortfolio > 0 ? round(($row['OutstandingAmount'] / $grossLoanPortfolio) * 100, 1) : 0;
        ?>
        <tr>
          <td><?= $b === 'Current (on-time)' ? '<span class="badge badge-success">'.$b.'</span>' : ($b==='PAR 90+' ? '<span class="badge badge-danger">'.$b.'</span>' : '<span class="badge badge-warning">'.$b.'</span>') ?></td>
          <td><?= number_format($row['LoanCount']) ?></td>
          <td><?= money($row['OutstandingAmount']) ?></td>
          <td><?= $pct ?>%</td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card-x">
      <div class="section-title">Client Status Breakdown</div>
      <canvas id="statusChart" height="220"></canvas>
    </div>
  </div>
</div>

<!-- BRANCH-WISE QUALITY -->
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card-x">
      <div class="section-title">Branch-wise Portfolio Quality</div>
      <table class="table-x">
        <tr><th>Branch</th><th>Active Loans</th><th>Outstanding</th><th>At-Risk Loans</th><th>At-Risk Amount</th><th>PAR %</th></tr>
        <?php foreach ($branchQuality as $bq):
          $bqPct = $bq['Outstanding'] > 0 ? round(($bq['AtRiskAmount'] / $bq['Outstanding']) * 100, 1) : 0;
        ?>
        <tr>
          <td><a href="?branch=<?= $bq['BranchID'] ?>"><?= htmlspecialchars($bq['BranchName']) ?></a></td>
          <td><?= $bq['ActiveLoans'] ?></td>
          <td><?= money($bq['Outstanding']) ?></td>
          <td><?= $bq['AtRiskLoans'] ?></td>
          <td><?= money($bq['AtRiskAmount']) ?></td>
          <td><span class="badge badge-<?= $bqPct > 10 ? 'danger' : ($bqPct > 3 ? 'warning' : 'success') ?>"><?= $bqPct ?>%</span></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>

<!-- AT-RISK CLIENT LIST -->
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card-x">
      <div class="section-title">At-Risk Clients — Worst First <a href="reports.php" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">Full Reports</a></div>
      <?php if ($atRiskList): ?>
      <table class="table-x">
        <tr><th>Loan No</th><th>Client</th><th>Mobile</th><th>Branch</th><th>Officer</th><th>Outstanding</th><th>Days Overdue</th></tr>
        <?php foreach ($atRiskList as $r): ?>
        <tr>
          <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
          <td><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></td>
          <td><?= htmlspecialchars($r['MobileNo']) ?></td>
          <td><?= htmlspecialchars($r['BranchName']) ?></td>
          <td><?= htmlspecialchars($r['OfficerName'] ?? '-') ?></td>
          <td><?= money($r['Outstanding']) ?></td>
          <td><span class="badge badge-<?= $r['DaysOverdue'] > 90 ? 'danger' : ($r['DaysOverdue'] > 30 ? 'warning' : 'secondary') ?>"><?= $r['DaysOverdue'] ?> days</span></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <div class="empty-state">No clients currently in arrears <i class="bi bi-stars"></i></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const statusData = <?= json_encode($statusBreakdown) ?>;
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: Object.keys(statusData),
    datasets: [{ data: Object.values(statusData), backgroundColor: ['#1f9d55','#e2a13a','#d64545','#0b2545','#384158','#0f8b8d'] }]
  },
  options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
