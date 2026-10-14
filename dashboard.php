<?php
$pageTitle = 'Dashboard';
$crumb = 'Overview of members, loans & collections';
$active = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$totalMembers = $pdo->query("SELECT COUNT(*) FROM Member")->fetchColumn();
$newLeads = $pdo->query("SELECT MemberCount FROM vw_Lead_Funnel WHERE StageName = 'New Lead'")->fetchColumn();
$activeLoans = $pdo->query("SELECT LoanCount FROM vw_Loan_Pipeline WHERE StageName = 'Active'")->fetchColumn();

$portfolio = $pdo->query("SELECT COALESCE(SUM(OutstandingPortfolio),0) FROM vw_Branch_Portfolio_Summary WHERE BranchName NOT LIKE '%HEAD OFFICE%'")->fetchColumn();
$disbursedTotal = $pdo->query("SELECT COALESCE(SUM(LifetimeDisbursed),0) FROM vw_Branch_Portfolio_Summary WHERE BranchName NOT LIKE '%HEAD OFFICE%'")->fetchColumn();
$overdueCount = $pdo->query("SELECT COUNT(*) FROM vw_Overdue_Installments")->fetchColumn();

$tasksToday = $pdo->query("
  SELECT COUNT(*) FROM Task t JOIN Task_Status_Master s ON t.StatusID = s.StatusID
  WHERE t.DueDate = CAST(GETDATE() AS DATE) AND s.StatusName IN ('Open','In Progress')
")->fetchColumn();

$stageData = $pdo->query("SELECT StageName AS stage, LoanCount AS c FROM vw_Loan_Pipeline ORDER BY SortOrder")->fetchAll();
// A loan is "Disbursed" once it has an actual disbursement date, even after its
// current stage has since moved on to Active/Closed/Preclosed — the pipeline's
// CurrentStageID rarely sits on "Disbursed" itself, so count it by date instead.
$disbursedLoanCount = (int)$pdo->query("SELECT COUNT(*) FROM Loan WHERE DisbursementDate IS NOT NULL")->fetchColumn();
foreach ($stageData as &$stageRow) {
    if ($stageRow['stage'] === 'Disbursed') { $stageRow['c'] = $disbursedLoanCount; }
}
unset($stageRow);
$leadStageData = $pdo->query("SELECT StageName AS lead_stage, MemberCount AS c FROM vw_Lead_Funnel ORDER BY SortOrder")->fetchAll();
$branchPortfolio = $pdo->query("SELECT BranchName AS name, OutstandingPortfolio AS portfolio FROM vw_Branch_Portfolio_Summary WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();

$recentLeads = $pdo->query("
  SELECT TOP 6 MemberID, FirstName, LastName, BranchName AS branch_name, CurrentStage AS lead_stage
  FROM vw_Member_Full ORDER BY MemberID DESC
")->fetchAll();

$overdueLoans = $pdo->query("SELECT TOP 6 * FROM vw_Overdue_Installments ORDER BY DueDate ASC")->fetchAll();

$todayTasks = $pdo->query("
  SELECT TOP 6 t.Title, u.FullName AS assignee, p.PriorityName
  FROM Task t
  LEFT JOIN Staff u ON t.AssignedToStaffID = u.StaffID
  LEFT JOIN Task_Priority_Master p ON t.PriorityID = p.PriorityID
  WHERE t.DueDate = CAST(GETDATE() AS DATE)
  ORDER BY p.SortOrder
")->fetchAll();

$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim($u['FullName'] ?? 'there'))[0];

/* ---- Detail rows behind each stat card's "View" modal ---- */
$modalMembers = $pdo->query("
  SELECT TOP 50 MemberID, EnrollmentNo, FirstName, LastName, MobileNo, BranchName, CurrentStage
  FROM vw_Member_Full ORDER BY MemberID DESC
")->fetchAll();

$modalActiveLoans = $pdo->query("
  SELECT TOP 50 l.LoanID, l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, l.EMIAmount,
         m.FirstName, m.LastName, b.BranchName, officer.FullName AS OfficerName
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  WHERE stg.StageName = 'Active'
  ORDER BY l.ApplicationDate DESC
")->fetchAll();

$modalDisbursed = $pdo->query("
  SELECT TOP 50 l.LoanID, l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, l.DisbursementDate,
         m.FirstName, m.LastName, b.BranchName
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  WHERE l.DisbursementDate IS NOT NULL
  ORDER BY l.DisbursementDate DESC
")->fetchAll();

$modalOverdue = $pdo->query("SELECT TOP 50 * FROM vw_Overdue_Installments ORDER BY DaysOverdue DESC")->fetchAll();

/* =======================================================================
   BUSINESS SNAPSHOT — today's & this month's collection numbers.
   "Today" figures read from Daily_Collection_Sheet (the live daily
   collection sheet); month-to-date and outstanding figures read from
   Repayment_Schedule / Collection since the sheet only ever holds the
   current day's rows.
======================================================================= */
$snapTodayDue = (float)$pdo->query("SELECT COALESCE(SUM(EMIAmount),0) FROM Daily_Collection_Sheet WHERE CollectionDueDate = CAST(GETDATE() AS DATE)")->fetchColumn();
$snapTodayCollection = (float)$pdo->query("SELECT COALESCE(SUM(CollectionAmount),0) FROM Daily_Collection_Sheet WHERE CollectionDueDate = CAST(GETDATE() AS DATE)")->fetchColumn();

// "Active Loans" here means loans still actually running — Active-stage AND
// still carrying at least one unpaid installment (a loan's stage doesn't
// always get moved off "Active" the moment its schedule is fully paid).
$snapActiveLoans = (int)$pdo->query("
  SELECT COUNT(*) FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE stg.StageName = 'Active' AND EXISTS (SELECT 1 FROM Repayment_Schedule rs WHERE rs.LoanID = l.LoanID AND rs.IsPaid = 0)
")->fetchColumn();

$snapMonthDisbCount = (int)$pdo->query("SELECT COUNT(*) FROM Loan WHERE YEAR(DisbursementDate)=YEAR(GETDATE()) AND MONTH(DisbursementDate)=MONTH(GETDATE())")->fetchColumn();
$snapMonthDue = (float)$pdo->query("SELECT COALESCE(SUM(EMIAmount),0) FROM Repayment_Schedule WHERE YEAR(DueDate)=YEAR(GETDATE()) AND MONTH(DueDate)=MONTH(GETDATE())")->fetchColumn();
$snapMonthCollection = (float)$pdo->query("SELECT COALESCE(SUM(TotalCollected),0) FROM Collection WHERE YEAR(CollectionDate)=YEAR(GETDATE()) AND MONTH(CollectionDate)=MONTH(GETDATE())")->fetchColumn();
$snapMonthPrincipal = (float)$pdo->query("SELECT COALESCE(SUM(PrincipalCollected),0) FROM Collection WHERE YEAR(CollectionDate)=YEAR(GETDATE()) AND MONTH(CollectionDate)=MONTH(GETDATE())")->fetchColumn();
$snapMonthInterest = (float)$pdo->query("SELECT COALESCE(SUM(InterestCollected),0) FROM Collection WHERE YEAR(CollectionDate)=YEAR(GETDATE()) AND MONTH(CollectionDate)=MONTH(GETDATE())")->fetchColumn();

$snapArrearClients = (int)$pdo->query("
  SELECT COUNT(DISTINCT l.MemberID)
  FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  JOIN Repayment_Schedule rs ON rs.LoanID = l.LoanID
  WHERE stg.StageName = 'Active' AND rs.IsPaid = 0 AND rs.DueDate < CAST(GETDATE() AS DATE)
")->fetchColumn();

$snapPOS = (float)$pdo->query("
  SELECT COALESCE(SUM(rs.PrincipalDue),0) FROM Repayment_Schedule rs
  JOIN Loan l ON rs.LoanID = l.LoanID JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE stg.StageName = 'Active' AND rs.IsPaid = 0
")->fetchColumn();
$snapInterestOutstanding = (float)$pdo->query("
  SELECT COALESCE(SUM(rs.InterestDue),0) FROM Repayment_Schedule rs
  JOIN Loan l ON rs.LoanID = l.LoanID JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  WHERE stg.StageName = 'Active' AND rs.IsPaid = 0
")->fetchColumn();

/* ---- Comparison figures for the KPI delta badges (yesterday / last month) ---- */
$cmpYesterdayDue = (float)$pdo->query("SELECT COALESCE(SUM(EMIAmount),0) FROM Daily_Collection_Sheet WHERE CollectionDueDate = CAST(DATEADD(DAY,-1,GETDATE()) AS DATE)")->fetchColumn();
$cmpYesterdayCollection = (float)$pdo->query("SELECT COALESCE(SUM(CollectionAmount),0) FROM Daily_Collection_Sheet WHERE CollectionDueDate = CAST(DATEADD(DAY,-1,GETDATE()) AS DATE)")->fetchColumn();
$cmpLastMonthDisbCount = (int)$pdo->query("SELECT COUNT(*) FROM Loan WHERE YEAR(DisbursementDate)=YEAR(DATEADD(MONTH,-1,GETDATE())) AND MONTH(DisbursementDate)=MONTH(DATEADD(MONTH,-1,GETDATE()))")->fetchColumn();
$cmpLastMonthDue = (float)$pdo->query("SELECT COALESCE(SUM(EMIAmount),0) FROM Repayment_Schedule WHERE YEAR(DueDate)=YEAR(DATEADD(MONTH,-1,GETDATE())) AND MONTH(DueDate)=MONTH(DATEADD(MONTH,-1,GETDATE()))")->fetchColumn();
$cmpLastMonthCollection = (float)$pdo->query("SELECT COALESCE(SUM(TotalCollected),0) FROM Collection WHERE YEAR(CollectionDate)=YEAR(DATEADD(MONTH,-1,GETDATE())) AND MONTH(CollectionDate)=MONTH(DATEADD(MONTH,-1,GETDATE()))")->fetchColumn();
$cmpLastMonthPrincipal = (float)$pdo->query("SELECT COALESCE(SUM(PrincipalCollected),0) FROM Collection WHERE YEAR(CollectionDate)=YEAR(DATEADD(MONTH,-1,GETDATE())) AND MONTH(CollectionDate)=MONTH(DATEADD(MONTH,-1,GETDATE()))")->fetchColumn();
$cmpLastMonthInterest = (float)$pdo->query("SELECT COALESCE(SUM(InterestCollected),0) FROM Collection WHERE YEAR(CollectionDate)=YEAR(DATEADD(MONTH,-1,GETDATE())) AND MONTH(CollectionDate)=MONTH(DATEADD(MONTH,-1,GETDATE()))")->fetchColumn();

$deltaTodayDue = pct_delta($snapTodayDue, $cmpYesterdayDue);
$deltaTodayCollection = pct_delta($snapTodayCollection, $cmpYesterdayCollection);
$deltaMonthDisb = pct_delta($snapMonthDisbCount, $cmpLastMonthDisbCount);
$deltaMonthDue = pct_delta($snapMonthDue, $cmpLastMonthDue);
$deltaMonthCollection = pct_delta($snapMonthCollection, $cmpLastMonthCollection);
$deltaMonthPrincipal = pct_delta($snapMonthPrincipal, $cmpLastMonthPrincipal);
$deltaMonthInterest = pct_delta($snapMonthInterest, $cmpLastMonthInterest);

/* ---- Detail rows behind each snapshot card's "View" modal ---- */
$modalTodaySheet = $pdo->query("
  SELECT TOP 50 dcs.LoanID, dcs.BorrowerName, dcs.CoBorrowerName, b.BranchName, c.CenterName,
         dcs.EMIAmount, dcs.CollectionStatus, dcs.CollectionAmount
  FROM Daily_Collection_Sheet dcs
  LEFT JOIN Branch b ON dcs.BranchID = b.BranchID
  LEFT JOIN Center c ON dcs.CenterID = c.CenterID
  WHERE dcs.CollectionDueDate = CAST(GETDATE() AS DATE)
  ORDER BY dcs.CollectionStatus, dcs.BorrowerName
")->fetchAll();

$modalSnapActiveLoans = $pdo->query("
  SELECT TOP 50 l.LoanID, l.LoanNo, m.FirstName, m.LastName, b.BranchName, officer.FullName AS OfficerName,
         (SELECT COALESCE(SUM(PrincipalDue),0) FROM Repayment_Schedule WHERE LoanID = l.LoanID AND IsPaid = 0) AS Outstanding
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
  WHERE stg.StageName = 'Active' AND EXISTS (SELECT 1 FROM Repayment_Schedule rs WHERE rs.LoanID = l.LoanID AND rs.IsPaid = 0)
  ORDER BY Outstanding DESC
")->fetchAll();

$modalMonthDisbursed = $pdo->query("
  SELECT TOP 50 l.LoanID, l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, l.DisbursementDate,
         m.FirstName, m.LastName, b.BranchName
  FROM Loan l
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  WHERE YEAR(l.DisbursementDate)=YEAR(GETDATE()) AND MONTH(l.DisbursementDate)=MONTH(GETDATE())
  ORDER BY l.DisbursementDate DESC
")->fetchAll();

$modalMonthDue = $pdo->query("
  SELECT TOP 50 rs.DueDate, rs.EMIAmount, l.LoanID, l.LoanNo, m.FirstName, m.LastName, b.BranchName
  FROM Repayment_Schedule rs
  JOIN Loan l ON rs.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  WHERE YEAR(rs.DueDate)=YEAR(GETDATE()) AND MONTH(rs.DueDate)=MONTH(GETDATE())
  ORDER BY rs.DueDate
")->fetchAll();

$modalMonthCollection = $pdo->query("
  SELECT TOP 50 c.CollectionDate, c.PrincipalCollected, c.InterestCollected, c.TotalCollected,
         l.LoanID, l.LoanNo, m.FirstName, m.LastName, b.BranchName
  FROM Collection c
  JOIN Loan l ON c.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  WHERE YEAR(c.CollectionDate)=YEAR(GETDATE()) AND MONTH(c.CollectionDate)=MONTH(GETDATE())
  ORDER BY c.CollectionDate DESC
")->fetchAll();

$modalArrearClients = $pdo->query("
  ;WITH UnpaidAgg AS (
    SELECT rs.LoanID, MIN(rs.DueDate) AS OldestUnpaidDue, SUM(rs.PrincipalDue) AS Outstanding
    FROM Repayment_Schedule rs WHERE rs.IsPaid = 0 GROUP BY rs.LoanID
  )
  SELECT TOP 50 l.LoanID, l.LoanNo, m.FirstName, m.LastName, m.MobileNo, b.BranchName, u.Outstanding,
         DATEDIFF(DAY, u.OldestUnpaidDue, CAST(GETDATE() AS DATE)) AS DaysOverdue
  FROM Loan l
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  JOIN UnpaidAgg u ON u.LoanID = l.LoanID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  WHERE stg.StageName = 'Active' AND u.OldestUnpaidDue < CAST(GETDATE() AS DATE)
  ORDER BY DaysOverdue DESC
")->fetchAll();

$modalOutstanding = $pdo->query("
  SELECT TOP 50 l.LoanID, l.LoanNo, m.FirstName, m.LastName, b.BranchName,
         SUM(rs.PrincipalDue) AS PrincipalOutstanding, SUM(rs.InterestDue) AS InterestOutstanding
  FROM Repayment_Schedule rs
  JOIN Loan l ON rs.LoanID = l.LoanID
  JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
  JOIN Member m ON l.MemberID = m.MemberID
  LEFT JOIN Branch b ON l.BranchID = b.BranchID
  WHERE stg.StageName = 'Active' AND rs.IsPaid = 0
  GROUP BY l.LoanID, l.LoanNo, m.FirstName, m.LastName, b.BranchName
  ORDER BY PrincipalOutstanding DESC
")->fetchAll();

/**
 * Small colored delta pill, e.g. "↑ 10.4%". Returns '' when there's no
 * honest baseline to compare against (see pct_delta()), rather than
 * showing a fabricated number.
 */
function kpi_delta_html($delta) {
    if ($delta === null) return '';
    if (abs($delta) < 0.05) {
        return '<span class="kpi-delta flat">→ 0.0%</span>';
    }
    $cls = $delta >= 0 ? 'up' : 'down';
    $arrow = $delta >= 0 ? '↑' : '↓';
    return '<span class="kpi-delta ' . $cls . '">' . $arrow . ' ' . number_format(abs($delta), 1) . '%</span>';
}

$kpiAccents = [
    'navy'    => ['var(--navy)', '#eef1f6'],
    'gold'    => ['var(--gold)', '#fdf6e8'],
    'teal'    => ['var(--teal)', '#e8f7f7'],
    'success' => ['var(--success)', '#eafaf0'],
    'danger'  => ['var(--danger)', '#fdeeee'],
];

$primaryCards = [
    ['lbl' => "Today's Due", 'val' => money($snapTodayDue), 'icon' => 'bullseye', 'accent' => 'teal', 'modal' => 'modalTodaySheet', 'delta' => $deltaTodayDue],
    ['lbl' => "Today's Collection", 'val' => money($snapTodayCollection), 'icon' => 'cash', 'accent' => 'navy', 'modal' => 'modalTodaySheet', 'delta' => $deltaTodayCollection],
    ['lbl' => 'Active Loans', 'val' => number_format($snapActiveLoans), 'icon' => 'briefcase-fill', 'accent' => 'gold', 'modal' => 'modalSnapActiveLoans', 'delta' => null],
    ['lbl' => 'Current Month Disbursement', 'val' => number_format($snapMonthDisbCount), 'icon' => 'bank', 'accent' => 'success', 'modal' => 'modalMonthDisbursed', 'delta' => $deltaMonthDisb],
];

$secondaryCards = [
    ['lbl' => 'Current Month Due', 'val' => money($snapMonthDue), 'icon' => 'calendar-event-fill', 'accent' => 'navy', 'modal' => 'modalMonthDue', 'delta' => $deltaMonthDue],
    ['lbl' => 'Current Month Collection', 'val' => money($snapMonthCollection), 'icon' => 'cash-coin', 'accent' => 'teal', 'modal' => 'modalMonthCollection', 'delta' => $deltaMonthCollection],
    ['lbl' => 'Current Month Principal Collected', 'val' => money($snapMonthPrincipal), 'icon' => 'receipt', 'accent' => 'gold', 'modal' => 'modalMonthCollection', 'delta' => $deltaMonthPrincipal],
    ['lbl' => 'Current Month Interest Collected', 'val' => money($snapMonthInterest), 'icon' => 'graph-up-arrow', 'accent' => 'navy', 'modal' => 'modalMonthCollection', 'delta' => $deltaMonthInterest],
    ['lbl' => 'Arrear Clients', 'val' => number_format($snapArrearClients), 'icon' => 'exclamation-octagon-fill', 'accent' => 'danger', 'modal' => 'modalArrearClients', 'delta' => null],
    ['lbl' => 'POS', 'val' => number_format($snapPOS), 'icon' => 'bar-chart-fill', 'accent' => 'gold', 'modal' => 'modalOutstanding', 'delta' => null],
    ['lbl' => 'Interest Outstanding', 'val' => number_format($snapInterestOutstanding), 'icon' => 'exclamation-triangle-fill', 'accent' => 'danger', 'modal' => 'modalOutstanding', 'delta' => null],
];
?>

<div class="dash-greeting">
  <div>
    <h2><?= $greeting ?>, <?= htmlspecialchars($firstName) ?> <i class="bi bi-emoji-smile"></i></h2>
    <p>Here's what's happening across your branches today.</p>
  </div>
  <div class="date-pill"><i class="bi bi-calendar-event-fill"></i> <?= date('D, d M Y') ?></div>
</div>

<div class="kpi-row-primary">
  <?php foreach ($primaryCards as $pc): [$accentColor, $accentSoft] = $kpiAccents[$pc['accent']]; ?>
  <div class="card-x kpi-card" style="--accent:<?= $accentColor ?>;--accent-soft:<?= $accentSoft ?>;">
    <div class="kpi-card-top">
      <div class="icon-box"><i class="bi bi-<?= htmlspecialchars($pc['icon']) ?>"></i></div>
      <?= kpi_delta_html($pc['delta']) ?>
    </div>
    <div class="kpi-value"><?= $pc['val'] ?></div>
    <div class="kpi-label"><?= htmlspecialchars($pc['lbl']) ?></div>
    <button type="button" class="kpi-view-link" data-bs-toggle="modal" data-bs-target="#<?= $pc['modal'] ?>">View details ↗</button>
  </div>
  <?php endforeach; ?>

  <div class="card-x kpi-hero">
    <div class="kpi-hero-top">
      <div>
        <div class="kpi-hero-label">Total Business Portfolio</div>
        <div class="kpi-hero-value"><?= money($portfolio) ?></div>
      </div>
      <div class="kpi-hero-icon"><i class="bi bi-bar-chart-fill"></i></div>
    </div>
    <div class="kpi-hero-stats">
      <div><strong><?= number_format($overdueCount) ?></strong><span>Overdue Installments</span></div>
      <div><strong><?= number_format($totalMembers) ?></strong><span>Total Members</span></div>
      <div><strong><?= money($disbursedTotal) ?></strong><span>Lifetime Disbursed</span></div>
    </div>
  </div>
</div>

<div class="section-title" style="margin-bottom:12px;"><span class="section-title-icon"><i class="bi bi-clipboard-check"></i></span>Business Snapshot</div>
<div class="kpi-grid-secondary mb-1">
  <?php foreach ($secondaryCards as $sc): [$accentColor, $accentSoft] = $kpiAccents[$sc['accent']]; ?>
  <div class="card-x kpi-card kpi-card-sm" style="--accent:<?= $accentColor ?>;--accent-soft:<?= $accentSoft ?>;">
    <div class="kpi-card-top">
      <div class="icon-box"><i class="bi bi-<?= htmlspecialchars($sc['icon']) ?>"></i></div>
      <?= kpi_delta_html($sc['delta']) ?>
    </div>
    <div class="kpi-value"><?= $sc['val'] ?></div>
    <div class="kpi-label"><?= htmlspecialchars($sc['lbl']) ?></div>
    <button type="button" class="kpi-view-link" data-bs-toggle="modal" data-bs-target="#<?= $sc['modal'] ?>">View details ↗</button>
  </div>
  <?php endforeach; ?>
</div>

<?php if (false): /* -- Temporarily hidden per request; change to `if (true)` to bring back the Members/Active Loans/Disbursed/Overdue row -- */ ?>
<div class="row g-3 mb-1">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card stat-card-sm" style="--accent:var(--navy);--accent-soft:#eef1f6;">
      <div class="stat-card-top">
        <div class="icon-box bg-navy"><i class="bi bi-people-fill"></i></div>
        <button type="button" class="stat-view-btn" data-bs-toggle="modal" data-bs-target="#modalMembers">View ↗</button>
      </div>
      <div class="num"><?= number_format($totalMembers) ?></div>
      <div class="lbl">Total Members</div>
      <div class="delta up">▲ <?= (int)$newLeads ?> new leads</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card stat-card-sm" style="--accent:var(--gold);--accent-soft:#fdf6e8;">
      <div class="stat-card-top">
        <div class="icon-box bg-gold"><i class="bi bi-briefcase-fill"></i></div>
        <button type="button" class="stat-view-btn" data-bs-toggle="modal" data-bs-target="#modalActiveLoans">View ↗</button>
      </div>
      <div class="num"><?= number_format((int)$activeLoans) ?></div>
      <div class="lbl">Active Loans</div>
      <div class="delta up">Portfolio: <?= money($portfolio) ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card stat-card-sm" style="--accent:var(--teal);--accent-soft:#e8f7f7;">
      <div class="stat-card-top">
        <div class="icon-box bg-teal"><i class="bi bi-graph-up-arrow"></i></div>
        <button type="button" class="stat-view-btn" data-bs-toggle="modal" data-bs-target="#modalDisbursed">View ↗</button>
      </div>
      <div class="num"><?= money($disbursedTotal) ?></div>
      <div class="lbl">Total Disbursed</div>
      <div class="delta up">Lifetime disbursement</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card stat-card-sm" style="--accent:var(--danger);--accent-soft:#fdeeee;">
      <div class="stat-card-top">
        <div class="icon-box bg-danger-soft"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <button type="button" class="stat-view-btn" data-bs-toggle="modal" data-bs-target="#modalOverdue">View ↗</button>
      </div>
      <div class="num"><?= number_format($overdueCount) ?></div>
      <div class="lbl">Overdue Installments</div>
      <div class="delta down">▼ <?= (int)$tasksToday ?> tasks due today</div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3 mt-1">
  <div class="col-lg-7">
    <div class="card-x">
      <div class="section-title"><span class="section-title-icon"><i class="bi bi-bar-chart-fill"></i></span>Loan Pipeline by Stage</div>
      <div style="height:260px;"><canvas id="stageChart"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card-x">
      <div class="section-title"><span class="section-title-icon"><i class="bi bi-pie-chart-fill"></i></span>Lead Funnel</div>
      <div style="height:260px;"><canvas id="leadChart"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card-x">
      <div class="section-title"><span class="section-title-icon"><i class="bi bi-building"></i></span>Portfolio by Branch</div>
      <div style="height:230px;"><canvas id="branchChart"></canvas></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card-x">
      <div class="section-title"><span><span class="section-title-icon"><i class="bi bi-check-circle-fill"></i></span>Tasks Due Today</span> <a href="tasks.php" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View All</a></div>
      <?php if ($todayTasks): ?>
        <table class="table-x">
          <tr><th>Title</th><th>Assignee</th><th>Priority</th></tr>
          <?php foreach ($todayTasks as $t): ?>
          <tr><td><?= htmlspecialchars($t['Title']) ?></td><td><?= htmlspecialchars($t['assignee'] ?? 'Unassigned') ?></td><td><?= priority_badge($t['PriorityName']) ?></td></tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?>
        <div class="empty-state"><i class="bi bi-stars"></i> No tasks scheduled for today</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-6">
    <div class="card-x">
      <div class="section-title"><span><span class="section-title-icon"><i class="bi bi-people-fill"></i></span>Recently Added Leads</span> <a href="members.php" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View All</a></div>
      <table class="table-x">
        <tr><th>Name</th><th>Branch</th><th>Stage</th></tr>
        <?php foreach ($recentLeads as $m): ?>
        <tr>
          <td><a href="member_view.php?id=<?= $m['MemberID'] ?>"><?= htmlspecialchars($m['FirstName'].' '.$m['LastName']) ?></a></td>
          <td><?= htmlspecialchars($m['branch_name']) ?></td>
          <td><?= stage_badge($m['lead_stage']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card-x">
      <div class="section-title"><span><span class="section-title-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>Overdue Collections</span> <a href="portfolio.php" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">Portfolio Health</a></div>
      <?php if ($overdueLoans): ?>
      <table class="table-x">
        <tr><th>Loan No</th><th>Member</th><th>Due Date</th><th>EMI</th></tr>
        <?php foreach ($overdueLoans as $o): ?>
        <tr>
          <td><a href="loan_view.php?id=<?= $o['LoanID'] ?>"><?= htmlspecialchars($o['LoanNo']) ?></a></td>
          <td><?= htmlspecialchars($o['FirstName'].' '.$o['LastName']) ?></td>
          <td><?= fdate($o['DueDate']) ?></td>
          <td><?= money($o['EMIAmount']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
        <div class="empty-state"><i class="bi bi-stars"></i> No overdue installments</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ---- Stat card detail modals ---- -->
<div class="modal fade" id="modalMembers" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-people-fill"></i> Total Members — <?= number_format($totalMembers) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <table class="table-x">
          <tr><th>Member</th><th>Mobile</th><th>Branch</th><th>Stage</th></tr>
          <?php foreach ($modalMembers as $m): ?>
          <tr>
            <td>
              <a href="member_view.php?id=<?= $m['MemberID'] ?>"><?= htmlspecialchars($m['FirstName'].' '.$m['LastName']) ?></a>
              <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($m['EnrollmentNo']) ?></div>
            </td>
            <td><?= htmlspecialchars($m['MobileNo']) ?></td>
            <td><?= htmlspecialchars($m['BranchName']) ?></td>
            <td><?= stage_badge($m['CurrentStage']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
      <div class="modal-footer">
        <span style="font-size:11.5px;color:var(--text-muted);margin-right:auto;">Showing latest 50 of <?= number_format($totalMembers) ?></span>
        <a href="members.php" class="btn-navy">View All Members</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalActiveLoans" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-briefcase-fill"></i> Active Loans — <?= number_format((int)$activeLoans) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($modalActiveLoans): ?>
        <table class="table-x">
          <tr><th>Loan No</th><th>Member</th><th>Branch</th><th>Officer</th><th>Amount</th><th>EMI</th></tr>
          <?php foreach ($modalActiveLoans as $l): ?>
          <tr>
            <td><a href="loan_view.php?id=<?= $l['LoanID'] ?>"><?= htmlspecialchars($l['LoanNo']) ?></a></td>
            <td><?= htmlspecialchars($l['FirstName'].' '.$l['LastName']) ?></td>
            <td><?= htmlspecialchars($l['BranchName'] ?? '-') ?></td>
            <td><?= htmlspecialchars($l['OfficerName'] ?? 'Unassigned') ?></td>
            <td style="font-weight:700;color:var(--navy);"><?= money($l['LoanAmountSanctioned'] ?: $l['LoanAmountApplied']) ?></td>
            <td><?= $l['EMIAmount'] ? money($l['EMIAmount']) : '-' ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <div class="empty-state">No active loans.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <span style="font-size:11.5px;color:var(--text-muted);margin-right:auto;">Showing latest 50 of <?= number_format((int)$activeLoans) ?></span>
        <a href="loans.php?stage=Active" class="btn-navy">View All in Pipeline</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalDisbursed" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-graph-up-arrow"></i> Total Disbursed — <?= money($disbursedTotal) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($modalDisbursed): ?>
        <table class="table-x">
          <tr><th>Loan No</th><th>Member</th><th>Branch</th><th>Amount</th><th>Disbursed On</th></tr>
          <?php foreach ($modalDisbursed as $l): ?>
          <tr>
            <td><a href="loan_view.php?id=<?= $l['LoanID'] ?>"><?= htmlspecialchars($l['LoanNo']) ?></a></td>
            <td><?= htmlspecialchars($l['FirstName'].' '.$l['LastName']) ?></td>
            <td><?= htmlspecialchars($l['BranchName'] ?? '-') ?></td>
            <td style="font-weight:700;color:var(--navy);"><?= money($l['LoanAmountSanctioned'] ?: $l['LoanAmountApplied']) ?></td>
            <td><?= fdate($l['DisbursementDate']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <div class="empty-state">No disbursed loans yet.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <span style="font-size:11.5px;color:var(--text-muted);margin-right:auto;">Showing latest 50 disbursed loans</span>
        <a href="loans.php?stage=Disbursed" class="btn-navy">View All in Pipeline</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalOverdue" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Overdue Installments — <?= number_format($overdueCount) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($modalOverdue): ?>
        <table class="table-x">
          <tr><th>Loan No</th><th>Member</th><th>Branch</th><th>Due Date</th><th>EMI</th><th>Overdue</th></tr>
          <?php foreach ($modalOverdue as $o): ?>
          <tr>
            <td><a href="loan_view.php?id=<?= $o['LoanID'] ?>"><?= htmlspecialchars($o['LoanNo']) ?></a></td>
            <td><?= htmlspecialchars($o['FirstName'].' '.$o['LastName']) ?></td>
            <td><?= htmlspecialchars($o['BranchName'] ?? '-') ?></td>
            <td><?= fdate($o['DueDate']) ?></td>
            <td><?= money($o['EMIAmount']) ?></td>
            <td><span class="badge badge-<?= $o['DaysOverdue'] > 90 ? 'danger' : ($o['DaysOverdue'] > 30 ? 'warning' : 'secondary') ?>"><?= (int)$o['DaysOverdue'] ?> days</span></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <div class="empty-state"><i class="bi bi-stars"></i> No overdue installments</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <span style="font-size:11.5px;color:var(--text-muted);margin-right:auto;">Showing latest 50 of <?= number_format($overdueCount) ?></span>
        <a href="portfolio.php" class="btn-navy">Portfolio Health</a>
      </div>
    </div>
  </div>
</div>

<!-- ---- Business snapshot detail modals ---- -->
<div class="modal fade" id="modalTodaySheet" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clipboard-check"></i> Today's Collection Sheet — Due <?= money($snapTodayDue) ?> / Collected <?= money($snapTodayCollection) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($modalTodaySheet): ?>
        <table class="table-x">
          <tr><th>Borrower</th><th>Branch</th><th>Center</th><th>Due</th><th>Status</th><th>Collected</th></tr>
          <?php foreach ($modalTodaySheet as $r): ?>
          <tr>
            <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars(trim($r['BorrowerName'])) ?></a></td>
            <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
            <td><?= money($r['EMIAmount']) ?></td>
            <td><?= stage_badge($r['CollectionStatus'] === 'Collected' ? 'Paid' : ($r['CollectionStatus'] === 'Preclosed' ? 'Paid' : 'Pending')) ?></td>
            <td><?= $r['CollectionAmount'] ? money($r['CollectionAmount']) : '-' ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <div class="empty-state">No collection due today.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <span style="font-size:11.5px;color:var(--text-muted);margin-right:auto;">Showing up to 50 rows for today</span>
        <a href="collection.php" class="btn-navy">Open Collection</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalSnapActiveLoans" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-briefcase-fill"></i> Active Loans — <?= number_format($snapActiveLoans) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($modalSnapActiveLoans): ?>
        <table class="table-x">
          <tr><th>Loan No</th><th>Member</th><th>Branch</th><th>Officer</th><th>Outstanding</th></tr>
          <?php foreach ($modalSnapActiveLoans as $l): ?>
          <tr>
            <td><a href="loan_view.php?id=<?= $l['LoanID'] ?>"><?= htmlspecialchars($l['LoanNo']) ?></a></td>
            <td><?= htmlspecialchars($l['FirstName'].' '.$l['LastName']) ?></td>
            <td><?= htmlspecialchars($l['BranchName'] ?? '-') ?></td>
            <td><?= htmlspecialchars($l['OfficerName'] ?? 'Unassigned') ?></td>
            <td style="font-weight:700;color:var(--navy);"><?= money($l['Outstanding']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <div class="empty-state">No active loans with a running balance.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <span style="font-size:11.5px;color:var(--text-muted);margin-right:auto;">Showing latest 50 of <?= number_format($snapActiveLoans) ?></span>
        <a href="loans.php?stage=Active" class="btn-navy">View All in Pipeline</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalMonthDisbursed" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-bank"></i> Current Month Disbursement — <?= number_format($snapMonthDisbCount) ?> loans</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($modalMonthDisbursed): ?>
        <table class="table-x">
          <tr><th>Loan No</th><th>Member</th><th>Branch</th><th>Amount</th><th>Disbursed On</th></tr>
          <?php foreach ($modalMonthDisbursed as $l): ?>
          <tr>
            <td><a href="loan_view.php?id=<?= $l['LoanID'] ?>"><?= htmlspecialchars($l['LoanNo']) ?></a></td>
            <td><?= htmlspecialchars($l['FirstName'].' '.$l['LastName']) ?></td>
            <td><?= htmlspecialchars($l['BranchName'] ?? '-') ?></td>
            <td style="font-weight:700;color:var(--navy);"><?= money($l['LoanAmountSanctioned'] ?: $l['LoanAmountApplied']) ?></td>
            <td><?= fdate($l['DisbursementDate']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <div class="empty-state">No loans disbursed this month yet.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a href="loans.php?stage=Disbursed" class="btn-navy">View All in Pipeline</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalMonthDue" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-calendar-event-fill"></i> Current Month Due — <?= money($snapMonthDue) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($modalMonthDue): ?>
        <table class="table-x">
          <tr><th>Due Date</th><th>Loan No</th><th>Member</th><th>Branch</th><th>EMI</th></tr>
          <?php foreach ($modalMonthDue as $r): ?>
          <tr>
            <td><?= fdate($r['DueDate']) ?></td>
            <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
            <td><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></td>
            <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
            <td><?= money($r['EMIAmount']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <div class="empty-state">No installments due this month.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <span style="font-size:11.5px;color:var(--text-muted);margin-right:auto;">Showing up to 50 rows for this month</span>
        <a href="collection.php?period=month" class="btn-navy">Open Collection</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalMonthCollection" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cash-coin"></i> Current Month Collection — <?= money($snapMonthCollection) ?> (Principal <?= money($snapMonthPrincipal) ?> + Interest <?= money($snapMonthInterest) ?>)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($modalMonthCollection): ?>
        <table class="table-x">
          <tr><th>Date</th><th>Loan No</th><th>Member</th><th>Branch</th><th>Principal</th><th>Interest</th><th>Total</th></tr>
          <?php foreach ($modalMonthCollection as $r): ?>
          <tr>
            <td><?= fdate($r['CollectionDate']) ?></td>
            <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
            <td><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></td>
            <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
            <td><?= money($r['PrincipalCollected']) ?></td>
            <td><?= money($r['InterestCollected']) ?></td>
            <td style="font-weight:700;color:var(--navy);"><?= money($r['TotalCollected']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <div class="empty-state">No collections recorded this month yet.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <span style="font-size:11.5px;color:var(--text-muted);margin-right:auto;">Showing latest 50 collections this month</span>
        <a href="reports.php" class="btn-navy">Full Reports</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalArrearClients" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-exclamation-octagon-fill"></i> Arrear Clients — <?= number_format($snapArrearClients) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($modalArrearClients): ?>
        <table class="table-x">
          <tr><th>Loan No</th><th>Client</th><th>Mobile</th><th>Branch</th><th>Outstanding</th><th>Days Overdue</th></tr>
          <?php foreach ($modalArrearClients as $r): ?>
          <tr>
            <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
            <td><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></td>
            <td><?= htmlspecialchars($r['MobileNo']) ?></td>
            <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
            <td style="font-weight:700;color:var(--danger);"><?= money($r['Outstanding']) ?></td>
            <td><span class="badge badge-<?= $r['DaysOverdue'] > 90 ? 'danger' : ($r['DaysOverdue'] > 30 ? 'warning' : 'secondary') ?>"><?= (int)$r['DaysOverdue'] ?> days</span></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <div class="empty-state"><i class="bi bi-stars"></i> No clients currently in arrears</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <span style="font-size:11.5px;color:var(--text-muted);margin-right:auto;">Showing latest 50 of <?= number_format($snapArrearClients) ?></span>
        <a href="reports.php#new-arrears" class="btn-navy">Arrears Report</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalOutstanding" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-bar-chart-fill"></i> Outstanding — Principal <?= money($snapPOS) ?> / Interest <?= money($snapInterestOutstanding) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($modalOutstanding): ?>
        <table class="table-x">
          <tr><th>Loan No</th><th>Member</th><th>Branch</th><th>Principal Outstanding</th><th>Interest Outstanding</th></tr>
          <?php foreach ($modalOutstanding as $r): ?>
          <tr>
            <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
            <td><?= htmlspecialchars($r['FirstName'].' '.$r['LastName']) ?></td>
            <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
            <td style="font-weight:700;color:var(--navy);"><?= money($r['PrincipalOutstanding']) ?></td>
            <td><?= money($r['InterestOutstanding']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php else: ?>
          <div class="empty-state">No outstanding balances on active loans.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <span style="font-size:11.5px;color:var(--text-muted);margin-right:auto;">Showing top 50 by principal outstanding</span>
        <a href="portfolio.php" class="btn-navy">Portfolio Health</a>
      </div>
    </div>
  </div>
</div>

<script>
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#6b7688';
Chart.defaults.plugins.tooltip.backgroundColor = '#0b2545';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.tooltip.titleFont = { family: "'Inter', sans-serif", weight: '600' };
Chart.defaults.plugins.tooltip.bodyFont = { family: "'Inter', sans-serif" };

function barGradient(ctx, c1, c2) {
  const g = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height || 220);
  g.addColorStop(0, c1); g.addColorStop(1, c2);
  return g;
}

const stageCtx = document.getElementById('stageChart').getContext('2d');
const stageData = <?= json_encode($stageData) ?>;
new Chart(stageCtx, {
  type: 'bar',
  data: { labels: stageData.map(s => s.stage), datasets: [{ label: 'Loans', data: stageData.map(s => s.c), backgroundColor: barGradient(stageCtx, '#13315c', '#0b2545'), borderRadius: 8, maxBarThickness: 42 }] },
  options: {
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#eef1f6' } },
      x: { grid: { display: false } }
    }
  }
});

const leadCtx = document.getElementById('leadChart').getContext('2d');
const leadData = <?= json_encode($leadStageData) ?>;
new Chart(leadCtx, {
  type: 'doughnut',
  data: {
    labels: leadData.map(s => s.lead_stage),
    datasets: [{
      data: leadData.map(s => s.c),
      backgroundColor: ['#0b2545', '#c8952c', '#0f8b8d', '#1f9d55', '#e2a13a', '#d64545'],
      borderColor: '#fff', borderWidth: 3, hoverOffset: 6
    }]
  },
  options: {
    maintainAspectRatio: false,
    cutout: '68%',
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14, font: { size: 11.5, weight: '600' } } } }
  }
});

const branchCtx = document.getElementById('branchChart').getContext('2d');
const branchData = <?= json_encode($branchPortfolio) ?>;
new Chart(branchCtx, {
  type: 'bar',
  data: { labels: branchData.map(b => b.name), datasets: [{ label: 'Outstanding Portfolio (₹)', data: branchData.map(b => b.portfolio), backgroundColor: '#c8952c', hoverBackgroundColor: '#e8b95a', borderRadius: 8, maxBarThickness: 22 }] },
  options: {
    indexAxis: 'y',
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { grid: { color: '#eef1f6' } }, y: { grid: { display: false } } }
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
