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

/* ---------------------------------------------------------------------
   TABS — the dashboard above stays "Overview"; everything below is a
   separate operational report, each with its own query run only when
   that tab is actually open.
--------------------------------------------------------------------- */
$reportTabs = ['overview', 'center_collection', 'today_pending', 'today_disbursed', 'due_disbursement', 'arrears_clients', 'defaults', 'arrear_collection', 'default_collection', 'today_preclose'];
$reportTab = $_GET['tab'] ?? 'overview';
if (!in_array($reportTab, $reportTabs, true)) $reportTab = 'overview';

function reportTabUrl($tab) {
    return 'reports.php?tab=' . urlencode($tab);
}

if ($reportTab === 'center_collection') {
    $ccFrom = $_GET['cc_from'] ?? (new DateTime('today'))->modify('-30 days')->format('Y-m-d');
    $ccTo = $_GET['cc_to'] ?? (new DateTime('today'))->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ccFrom)) $ccFrom = (new DateTime('today'))->modify('-30 days')->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ccTo)) $ccTo = (new DateTime('today'))->format('Y-m-d');
    if ($ccTo < $ccFrom) { [$ccFrom, $ccTo] = [$ccTo, $ccFrom]; }

    $centerCollectionStmt = $pdo->prepare("
      SELECT c.CenterName, b.BranchName, COUNT(DISTINCT dcs.LoanID) AS ClientCount,
        SUM(dcs.EMIAmount) AS DueAmount, COALESCE(SUM(dcs.CollectionAmount),0) AS CollectedAmount
      FROM Daily_Collection_Sheet dcs
      JOIN Center c ON dcs.CenterID = c.CenterID
      LEFT JOIN Branch b ON dcs.BranchID = b.BranchID
      WHERE dcs.CollectionDueDate BETWEEN ? AND ?
      GROUP BY c.CenterName, b.BranchName
      ORDER BY b.BranchName, c.CenterName
    ");
    $centerCollectionStmt->execute([$ccFrom, $ccTo]);
    $centerCollectionRows = $centerCollectionStmt->fetchAll();
}

if ($reportTab === 'today_pending') {
    $todayPendingRows = $pdo->query("
      SELECT dcs.LoanID, l.LoanNo, dcs.BorrowerName, dcs.CoBorrowerName, b.BranchName, c.CenterName,
             officer.FullName AS OfficerName, dcs.EMIAmount
      FROM Daily_Collection_Sheet dcs
      JOIN Loan l ON dcs.LoanID = l.LoanID
      LEFT JOIN Branch b ON dcs.BranchID = b.BranchID
      LEFT JOIN Center c ON dcs.CenterID = c.CenterID
      LEFT JOIN Staff officer ON dcs.OfficerStaffID = officer.StaffID
      WHERE dcs.CollectionDueDate = CAST(GETDATE() AS DATE) AND dcs.CollectionStatus = 'Pending'
      ORDER BY b.BranchName, c.CenterName, dcs.BorrowerName
    ")->fetchAll();
    $todayPendingTotal = array_sum(array_map(fn($r) => (float)$r['EMIAmount'], $todayPendingRows));
}

if ($reportTab === 'today_disbursed') {
    $todayDisbursedRows = $pdo->query("
      SELECT l.LoanID, l.LoanNo, m.FirstName, m.LastName, b.BranchName, c.CenterName,
             officer.FullName AS OfficerName, COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied) AS Amount,
             l.DisbursementMode
      FROM Loan l
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Center c ON l.CenterID = c.CenterID
      LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
      WHERE CAST(l.DisbursementDate AS DATE) = CAST(GETDATE() AS DATE)
      ORDER BY b.BranchName, c.CenterName
    ")->fetchAll();
    $todayDisbursedTotal = array_sum(array_map(fn($r) => (float)$r['Amount'], $todayDisbursedRows));
}

if ($reportTab === 'due_disbursement') {
    $dueDisbursementRows = $pdo->query("
      SELECT l.LoanID, l.LoanNo, m.FirstName, m.LastName, b.BranchName, c.CenterName,
             officer.FullName AS OfficerName, COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied) AS Amount,
             l.ApprovalDate
      FROM Loan l
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Center c ON l.CenterID = c.CenterID
      LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
      LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      WHERE stg.StageName = 'Approved'
      ORDER BY l.ApprovalDate ASC
    ")->fetchAll();
    $dueDisbursementTotal = array_sum(array_map(fn($r) => (float)$r['Amount'], $dueDisbursementRows));
}

if ($reportTab === 'arrears_clients') {
    // Every currently-active/disbursed loan with at least one unpaid, past-due
    // installment -- how many installments are broken, which one it broke
    // from, and the total amount (principal) outstanding on those installments.
    $arrearsClientsRows = $pdo->query("
      ;WITH Broken AS (
        SELECT rs.LoanID, MIN(rs.InstallmentNo) AS FirstBrokenInstallment,
               COUNT(*) AS BrokenCount, SUM(rs.PrincipalDue) AS BrokenAmount, MIN(rs.DueDate) AS FirstBrokenDate
        FROM Repayment_Schedule rs
        WHERE rs.IsPaid = 0 AND rs.DueDate < CAST(GETDATE() AS DATE)
        GROUP BY rs.LoanID
      )
      SELECT l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo, b.BranchName, c.CenterName,
             officer.FullName AS OfficerName, br.FirstBrokenInstallment, br.BrokenCount, br.BrokenAmount,
             DATEDIFF(DAY, br.FirstBrokenDate, CAST(GETDATE() AS DATE)) AS DaysOverdue
      FROM Broken br
      JOIN Loan l ON br.LoanID = l.LoanID
      JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Center c ON l.CenterID = c.CenterID
      LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
      WHERE stg.StageName IN ('Active', 'Disbursed')
      ORDER BY br.BrokenAmount DESC
    ")->fetchAll();
    $arrearsClientsTotal = array_sum(array_map(fn($r) => (float)$r['BrokenAmount'], $arrearsClientsRows));
}

if ($reportTab === 'defaults') {
    // Loan has run past its full scheduled tenure (its last installment's
    // due date is in the past) but is still sitting in Active/Disbursed with
    // unpaid installments -- principal + interest still owed.
    $defaultRows = $pdo->query("
      ;WITH Matured AS (
        SELECT rs.LoanID, MAX(rs.DueDate) AS LastDueDate,
               SUM(CASE WHEN rs.IsPaid = 0 THEN rs.PrincipalDue + rs.InterestDue ELSE 0 END) AS Outstanding
        FROM Repayment_Schedule rs
        GROUP BY rs.LoanID
        HAVING MAX(rs.DueDate) < CAST(GETDATE() AS DATE)
           AND SUM(CASE WHEN rs.IsPaid = 0 THEN rs.PrincipalDue + rs.InterestDue ELSE 0 END) > 0
      )
      SELECT l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo, b.BranchName, c.CenterName,
             officer.FullName AS OfficerName, mt.LastDueDate, mt.Outstanding,
             DATEDIFF(DAY, mt.LastDueDate, CAST(GETDATE() AS DATE)) AS DaysPastTenure
      FROM Matured mt
      JOIN Loan l ON mt.LoanID = l.LoanID
      JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Center c ON l.CenterID = c.CenterID
      LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
      WHERE stg.StageName IN ('Active', 'Disbursed')
      ORDER BY mt.Outstanding DESC
    ")->fetchAll();
    $defaultTotal = array_sum(array_map(fn($r) => (float)$r['Outstanding'], $defaultRows));
}

if ($reportTab === 'arrear_collection') {
    // Every payment actually recovered against a broken (overdue-at-the-time)
    // installment within the date range -- tracks recovery performance on
    // arrears, as opposed to the Arrears Clients tab's current worklist.
    $acFrom = $_GET['ac_from'] ?? (new DateTime('today'))->modify('-30 days')->format('Y-m-d');
    $acTo = $_GET['ac_to'] ?? (new DateTime('today'))->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $acFrom)) $acFrom = (new DateTime('today'))->modify('-30 days')->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $acTo)) $acTo = (new DateTime('today'))->format('Y-m-d');
    if ($acTo < $acFrom) { [$acFrom, $acTo] = [$acTo, $acFrom]; }

    $arrearCollectionStmt = $pdo->prepare("
      SELECT c.CollectionDate, l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo,
             b.BranchName, ctr.CenterName, officer.FullName AS OfficerName,
             rs.InstallmentNo, rs.DueDate, c.TotalCollected,
             DATEDIFF(DAY, rs.DueDate, c.CollectionDate) AS DaysLate
      FROM Collection c
      JOIN Repayment_Schedule rs ON c.ScheduleID = rs.ScheduleID
      JOIN Loan l ON c.LoanID = l.LoanID
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Center ctr ON l.CenterID = ctr.CenterID
      LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
      WHERE c.CollectionDate BETWEEN ? AND ? AND c.CollectionDate > rs.DueDate
      ORDER BY c.CollectionDate DESC
    ");
    $arrearCollectionStmt->execute([$acFrom, $acTo]);
    $arrearCollectionRows = $arrearCollectionStmt->fetchAll();
    $arrearCollectionTotal = array_sum(array_map(fn($r) => (float)$r['TotalCollected'], $arrearCollectionRows));
}

if ($reportTab === 'default_collection') {
    // Every payment collected within the date range against a loan that is
    // (or was, at the time of some installment) past its full scheduled
    // tenure -- tracks recovery on the same loan population the Default
    // Loans tab lists as outstanding.
    $dcFrom = $_GET['dc_from'] ?? (new DateTime('today'))->modify('-30 days')->format('Y-m-d');
    $dcTo = $_GET['dc_to'] ?? (new DateTime('today'))->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dcFrom)) $dcFrom = (new DateTime('today'))->modify('-30 days')->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dcTo)) $dcTo = (new DateTime('today'))->format('Y-m-d');
    if ($dcTo < $dcFrom) { [$dcFrom, $dcTo] = [$dcTo, $dcFrom]; }

    $defaultCollectionStmt = $pdo->prepare("
      ;WITH Matured AS (
        SELECT rs.LoanID, MAX(rs.DueDate) AS LastDueDate
        FROM Repayment_Schedule rs
        GROUP BY rs.LoanID
        HAVING MAX(rs.DueDate) < CAST(GETDATE() AS DATE)
      )
      SELECT c.CollectionDate, l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo,
             b.BranchName, ctr.CenterName, officer.FullName AS OfficerName, mt.LastDueDate, c.TotalCollected
      FROM Collection c
      JOIN Matured mt ON c.LoanID = mt.LoanID
      JOIN Loan l ON c.LoanID = l.LoanID
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Center ctr ON l.CenterID = ctr.CenterID
      LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
      WHERE c.CollectionDate BETWEEN ? AND ?
      ORDER BY c.CollectionDate DESC
    ");
    $defaultCollectionStmt->execute([$dcFrom, $dcTo]);
    $defaultCollectionRows = $defaultCollectionStmt->fetchAll();
    $defaultCollectionTotal = array_sum(array_map(fn($r) => (float)$r['TotalCollected'], $defaultCollectionRows));
}

if ($reportTab === 'today_preclose') {
    // Every loan preclose actually approved within the date range, and how
    // much it precosed for -- defaults to today only (not a 30-day window
    // like the other date-range tabs) since this is meant as a daily
    // operational check, widened only if the Admin picks a range.
    $tpFrom = $_GET['tp_from'] ?? (new DateTime('today'))->format('Y-m-d');
    $tpTo = $_GET['tp_to'] ?? (new DateTime('today'))->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tpFrom)) $tpFrom = (new DateTime('today'))->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tpTo)) $tpTo = (new DateTime('today'))->format('Y-m-d');
    if ($tpTo < $tpFrom) { [$tpFrom, $tpTo] = [$tpTo, $tpFrom]; }

    $todayPrecloseStmt = $pdo->prepare("
      SELECT la.ReviewedAt, la.PrecloseQuoteAmount,
             l.LoanID, l.LoanNo, l.LoanCycle, m.FirstName, m.LastName, m.MobileNo,
             b.BranchName, ctr.CenterName, officer.FullName AS OfficerName, reviewer.FullName AS ApprovedByName
      FROM Loan_Approval la
      JOIN Loan l ON la.LoanID = l.LoanID
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Center ctr ON l.CenterID = ctr.CenterID
      LEFT JOIN Staff officer ON l.LoanOfficerStaffID = officer.StaffID
      LEFT JOIN Staff reviewer ON la.ReviewedByStaffID = reviewer.StaffID
      WHERE la.RequestType = 'Preclose' AND la.Status = 'Approved'
        AND CAST(la.ReviewedAt AS DATE) BETWEEN ? AND ?
      ORDER BY la.ReviewedAt DESC
    ");
    $todayPrecloseStmt->execute([$tpFrom, $tpTo]);
    $todayPrecloseRows = $todayPrecloseStmt->fetchAll();
    $todayPrecloseTotal = array_sum(array_map(fn($r) => (float)$r['PrecloseQuoteAmount'], $todayPrecloseRows));
}

if (($_GET['export'] ?? '') === 'csv') {
    require_once __DIR__ . '/includes/csv_export_helper.php';
    switch ($reportTab) {
        case 'center_collection':
            $ccExportRows = $centerCollectionRows ?? [];
            $ccExportDue = array_sum(array_map(fn($r) => (float)$r['DueAmount'], $ccExportRows));
            $ccExportCollected = array_sum(array_map(fn($r) => (float)$r['CollectedAmount'], $ccExportRows));
            $ccExportClients = array_sum(array_map(fn($r) => (int)$r['ClientCount'], $ccExportRows));
            export_csv_and_exit('center_wise_collection.csv', ['Branch', 'Center', 'Clients', 'Due Amount', 'Collected Amount', 'Balance', '% Collected'], array_merge(
                array_map(fn($r) => [
                    $r['BranchName'], $r['CenterName'], $r['ClientCount'], $r['DueAmount'], $r['CollectedAmount'], $r['DueAmount'] - $r['CollectedAmount'],
                    (float)$r['DueAmount'] > 0 ? round((float)$r['CollectedAmount'] / (float)$r['DueAmount'] * 100, 1) : 0,
                ], $ccExportRows),
                [['', 'TOTAL', $ccExportClients, $ccExportDue, $ccExportCollected, $ccExportDue - $ccExportCollected, $ccExportDue > 0 ? round($ccExportCollected / $ccExportDue * 100, 1) : 0]]
            ));
            break;
        case 'today_pending':
            export_csv_and_exit('today_pending_collection.csv', ['Loan No', 'Borrower', 'Co-Borrower', 'Branch', 'Center', 'Officer', 'EMI Amount'], array_map(fn($r) => [
                $r['LoanNo'], $r['BorrowerName'], $r['CoBorrowerName'], $r['BranchName'], $r['CenterName'], $r['OfficerName'] ?? 'Unassigned', $r['EMIAmount'],
            ], $todayPendingRows ?? []));
            break;
        case 'today_disbursed':
            export_csv_and_exit('today_disbursed.csv', ['Loan No', 'Member', 'Branch', 'Center', 'Officer', 'Amount', 'Mode'], array_map(fn($r) => [
                $r['LoanNo'], trim($r['FirstName'] . ' ' . $r['LastName']), $r['BranchName'], $r['CenterName'], $r['OfficerName'] ?? 'Unassigned', $r['Amount'], $r['DisbursementMode'],
            ], $todayDisbursedRows ?? []));
            break;
        case 'due_disbursement':
            export_csv_and_exit('due_disbursement.csv', ['Loan No', 'Member', 'Branch', 'Center', 'Officer', 'Amount', 'Approved On'], array_map(fn($r) => [
                $r['LoanNo'], trim($r['FirstName'] . ' ' . $r['LastName']), $r['BranchName'], $r['CenterName'], $r['OfficerName'] ?? 'Unassigned', $r['Amount'], fdate($r['ApprovalDate']),
            ], $dueDisbursementRows ?? []));
            break;
        case 'arrears_clients':
            export_csv_and_exit('arrears_clients.csv', ['Loan No', 'Cycle', 'Client', 'Mobile', 'Branch', 'Center', 'Officer', 'Broken From Inst.', 'Installments Broken', 'Days Overdue', 'Arrear Amount'], array_map(fn($r) => [
                $r['LoanNo'], $r['LoanCycle'], trim($r['FirstName'] . ' ' . $r['LastName']), $r['MobileNo'], $r['BranchName'], $r['CenterName'], $r['OfficerName'] ?? 'Unassigned',
                $r['FirstBrokenInstallment'], $r['BrokenCount'], $r['DaysOverdue'], $r['BrokenAmount'],
            ], $arrearsClientsRows ?? []));
            break;
        case 'defaults':
            export_csv_and_exit('default_loans.csv', ['Loan No', 'Cycle', 'Client', 'Mobile', 'Branch', 'Center', 'Officer', 'Tenure Ended On', 'Days Past Tenure', 'Outstanding (P+I)'], array_map(fn($r) => [
                $r['LoanNo'], $r['LoanCycle'], trim($r['FirstName'] . ' ' . $r['LastName']), $r['MobileNo'], $r['BranchName'], $r['CenterName'], $r['OfficerName'] ?? 'Unassigned',
                fdate($r['LastDueDate']), $r['DaysPastTenure'], $r['Outstanding'],
            ], $defaultRows ?? []));
            break;
        case 'arrear_collection':
            export_csv_and_exit('arrear_collection.csv', ['Collection Date', 'Loan No', 'Cycle', 'Client', 'Mobile', 'Branch', 'Center', 'Officer', 'Installment No', 'Due Date', 'Days Late', 'Amount Collected'], array_map(fn($r) => [
                fdate($r['CollectionDate']), $r['LoanNo'], $r['LoanCycle'], trim($r['FirstName'] . ' ' . $r['LastName']), $r['MobileNo'], $r['BranchName'], $r['CenterName'], $r['OfficerName'] ?? 'Unassigned',
                $r['InstallmentNo'], fdate($r['DueDate']), $r['DaysLate'], $r['TotalCollected'],
            ], $arrearCollectionRows ?? []));
            break;
        case 'default_collection':
            export_csv_and_exit('default_collection.csv', ['Collection Date', 'Loan No', 'Cycle', 'Client', 'Mobile', 'Branch', 'Center', 'Officer', 'Tenure Ended On', 'Amount Collected'], array_map(fn($r) => [
                fdate($r['CollectionDate']), $r['LoanNo'], $r['LoanCycle'], trim($r['FirstName'] . ' ' . $r['LastName']), $r['MobileNo'], $r['BranchName'], $r['CenterName'], $r['OfficerName'] ?? 'Unassigned',
                fdate($r['LastDueDate']), $r['TotalCollected'],
            ], $defaultCollectionRows ?? []));
            break;
        case 'today_preclose':
            export_csv_and_exit('today_preclose.csv', ['Preclose Date', 'Loan No', 'Cycle', 'Client', 'Mobile', 'Branch', 'Center', 'Officer', 'Approved By', 'Preclose Amount'], array_map(fn($r) => [
                fdate($r['ReviewedAt']), $r['LoanNo'], $r['LoanCycle'], trim($r['FirstName'] . ' ' . $r['LastName']), $r['MobileNo'], $r['BranchName'], $r['CenterName'], $r['OfficerName'] ?? 'Unassigned',
                $r['ApprovedByName'] ?? '-', $r['PrecloseQuoteAmount'],
            ], $todayPrecloseRows ?? []));
            break;
    }
}
?>
<ul class="nav nav-tabs-x" role="tablist" style="margin-bottom:16px;">
  <li class="nav-item"><a class="nav-link <?= $reportTab==='overview'?'active':'' ?>" href="<?= reportTabUrl('overview') ?>">Overview</a></li>
  <li class="nav-item"><a class="nav-link <?= $reportTab==='center_collection'?'active':'' ?>" href="<?= reportTabUrl('center_collection') ?>">Center-wise Collection</a></li>
  <li class="nav-item"><a class="nav-link <?= $reportTab==='today_pending'?'active':'' ?>" href="<?= reportTabUrl('today_pending') ?>">Today Pending Collection</a></li>
  <li class="nav-item"><a class="nav-link <?= $reportTab==='today_disbursed'?'active':'' ?>" href="<?= reportTabUrl('today_disbursed') ?>">Today Disbursed</a></li>
  <li class="nav-item"><a class="nav-link <?= $reportTab==='due_disbursement'?'active':'' ?>" href="<?= reportTabUrl('due_disbursement') ?>">Due Disbursement</a></li>
  <li class="nav-item"><a class="nav-link <?= $reportTab==='arrears_clients'?'active':'' ?>" href="<?= reportTabUrl('arrears_clients') ?>">Arrears Clients</a></li>
  <li class="nav-item"><a class="nav-link <?= $reportTab==='defaults'?'active':'' ?>" href="<?= reportTabUrl('defaults') ?>">Default Loans</a></li>
  <li class="nav-item"><a class="nav-link <?= $reportTab==='arrear_collection'?'active':'' ?>" href="<?= reportTabUrl('arrear_collection') ?>">Arrear Collection</a></li>
  <li class="nav-item"><a class="nav-link <?= $reportTab==='default_collection'?'active':'' ?>" href="<?= reportTabUrl('default_collection') ?>">Default Collection</a></li>
  <li class="nav-item"><a class="nav-link <?= $reportTab==='today_preclose'?'active':'' ?>" href="<?= reportTabUrl('today_preclose') ?>">Today Preclose</a></li>
</ul>

<?php if ($reportTab === 'overview'): ?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy"><i class="bi bi-stopwatch"></i></div>
      <div class="stat-body">
        <div class="num"><?= $avgTAT ? round($avgTAT, 1) : '-' ?> days</div>
        <div class="lbl">Avg. Turn-Around-Time</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal"><i class="bi bi-cash-coin"></i></div>
      <div class="stat-body">
        <div class="num" style="font-size:17px;"><?= money($lifetimeCollected) ?></div>
        <div class="lbl">Lifetime Collected</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold"><i class="bi bi-person-fill"></i></div>
      <div class="stat-body">
        <div class="num"><?= number_format($activeOfficerCount) ?></div>
        <div class="lbl">Active Loan Officers</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft"><i class="bi bi-exclamation-triangle-fill"></i></div>
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
    <div class="empty-state">No clients newly entered arrears in this window <i class="bi bi-stars"></i></div>
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
        <div class="empty-state">No overdue installments across branches <i class="bi bi-stars"></i></div>
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
<?php endif; ?>

<?php if ($reportTab === 'center_collection'): ?>
<div class="card-x mb-3">
  <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
    <input type="hidden" name="tab" value="center_collection">
    <div><label class="form-label">From</label><input type="date" class="form-control" name="cc_from" value="<?= htmlspecialchars($ccFrom) ?>" required></div>
    <div><label class="form-label">To</label><input type="date" class="form-control" name="cc_to" value="<?= htmlspecialchars($ccTo) ?>" required></div>
    <button class="btn-navy" type="submit">Apply</button>
  </form>
</div>
<div class="card-x">
  <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>Center-wise Collection — <?= fdate($ccFrom) ?> to <?= fdate($ccTo) ?></span>
    <a href="reports.php?tab=center_collection&cc_from=<?= urlencode($ccFrom) ?>&cc_to=<?= urlencode($ccTo) ?>&export=csv" class="btn-outline-x" style="font-size:12px;padding:5px 12px;"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <?php if ($centerCollectionRows): ?>
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Center</th><th>Branch</th><th>Clients</th><th>Due</th><th>Collected</th><th>Balance</th><th>%</th></tr>
      <?php
        $ccTotalClients = 0; $ccTotalDue = 0.0; $ccTotalCollected = 0.0;
        foreach ($centerCollectionRows as $r):
        $due = (float)$r['DueAmount']; $coll = (float)$r['CollectedAmount']; $bal = $due - $coll;
        $pct = $due > 0 ? round($coll / $due * 100, 1) : 0;
        $ccTotalClients += (int)$r['ClientCount']; $ccTotalDue += $due; $ccTotalCollected += $coll;
      ?>
      <tr>
        <td><strong><?= htmlspecialchars($r['CenterName']) ?></strong></td>
        <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
        <td><?= number_format($r['ClientCount']) ?></td>
        <td><?= money($due) ?></td>
        <td><?= money($coll) ?></td>
        <td><?= money($bal) ?></td>
        <td><?= $pct ?>%</td>
      </tr>
      <?php endforeach;
        $ccTotalBalance = $ccTotalDue - $ccTotalCollected;
        $ccTotalPct = $ccTotalDue > 0 ? round($ccTotalCollected / $ccTotalDue * 100, 1) : 0;
      ?>
      <tr style="font-weight:700;border-top:2px solid var(--border);background:var(--bg);">
        <td colspan="2">Total</td>
        <td><?= number_format($ccTotalClients) ?></td>
        <td><?= money($ccTotalDue) ?></td>
        <td style="color:var(--success);"><?= money($ccTotalCollected) ?></td>
        <td style="color:var(--danger);"><?= money($ccTotalBalance) ?></td>
        <td><?= $ccTotalPct ?>%</td>
      </tr>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state">No collection activity in this range.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($reportTab === 'today_pending'): ?>
<div class="card-x">
  <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>Today's Pending Collection <span class="badge badge-warning"><?= money($todayPendingTotal) ?> due</span></span>
    <a href="<?= reportTabUrl('today_pending') ?>&export=csv" class="btn-outline-x" style="font-size:12px;padding:5px 12px;"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <?php if ($todayPendingRows): ?>
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Loan No</th><th>Client</th><th>Branch</th><th>Center</th><th>Officer</th><th>EMI Due</th></tr>
      <?php foreach ($todayPendingRows as $r): ?>
      <tr>
        <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
        <td><?= htmlspecialchars($r['BorrowerName']) ?><?php if (trim($r['CoBorrowerName'] ?? '')): ?><div style="font-size:11px;color:var(--text-muted);">Co: <?= htmlspecialchars($r['CoBorrowerName']) ?></div><?php endif; ?></td>
        <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['OfficerName'] ?? 'Unassigned') ?></td>
        <td style="font-weight:700;"><?= money($r['EMIAmount']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state">No pending collections for today <i class="bi bi-stars"></i></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($reportTab === 'today_disbursed'): ?>
<div class="card-x">
  <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>Today's Disbursed Loans <span class="badge badge-success"><?= money($todayDisbursedTotal) ?> disbursed</span></span>
    <a href="<?= reportTabUrl('today_disbursed') ?>&export=csv" class="btn-outline-x" style="font-size:12px;padding:5px 12px;"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <?php if ($todayDisbursedRows): ?>
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Loan No</th><th>Client</th><th>Branch</th><th>Center</th><th>Officer</th><th>Mode</th><th>Amount</th></tr>
      <?php foreach ($todayDisbursedRows as $r): ?>
      <tr>
        <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
        <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?></td>
        <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['OfficerName'] ?? 'Unassigned') ?></td>
        <td><?= htmlspecialchars($r['DisbursementMode'] ?? '-') ?></td>
        <td style="font-weight:700;"><?= money($r['Amount']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state">No loans disbursed today yet.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($reportTab === 'due_disbursement'): ?>
<div class="card-x">
  <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>Due Disbursement — Approved, Awaiting Disbursal <span class="badge badge-warning"><?= money($dueDisbursementTotal) ?> pending</span></span>
    <a href="<?= reportTabUrl('due_disbursement') ?>&export=csv" class="btn-outline-x" style="font-size:12px;padding:5px 12px;"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <?php if ($dueDisbursementRows): ?>
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Loan No</th><th>Client</th><th>Branch</th><th>Center</th><th>Officer</th><th>Approved On</th><th>Amount</th></tr>
      <?php foreach ($dueDisbursementRows as $r): ?>
      <tr>
        <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
        <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?></td>
        <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['OfficerName'] ?? 'Unassigned') ?></td>
        <td><?= fdate($r['ApprovalDate']) ?></td>
        <td style="font-weight:700;"><?= money($r['Amount']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state">No loans awaiting disbursement <i class="bi bi-stars"></i></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($reportTab === 'arrears_clients'): ?>
<div class="card-x">
  <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>Arrears Clients <span class="badge badge-danger"><?= number_format(count($arrearsClientsRows)) ?> loans · <?= money($arrearsClientsTotal) ?></span></span>
    <a href="<?= reportTabUrl('arrears_clients') ?>&export=csv" class="btn-outline-x" style="font-size:12px;padding:5px 12px;"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <?php if ($arrearsClientsRows): ?>
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Loan No</th><th>Cycle</th><th>Client</th><th>Branch</th><th>Center</th><th>Officer</th><th>Broken From Inst.</th><th>Installments Broken</th><th>Days Overdue</th><th>Arrear Amount</th></tr>
      <?php foreach ($arrearsClientsRows as $r): ?>
      <tr>
        <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
        <td><?= $r['LoanCycle'] !== null ? (int)$r['LoanCycle'] : '-' ?></td>
        <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?><br><span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($r['MobileNo'] ?? '-') ?></span></td>
        <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['OfficerName'] ?? 'Unassigned') ?></td>
        <td>#<?= (int)$r['FirstBrokenInstallment'] ?></td>
        <td><?= (int)$r['BrokenCount'] ?></td>
        <td><span class="badge badge-<?= $r['DaysOverdue'] > 90 ? 'danger' : ($r['DaysOverdue'] > 30 ? 'warning' : 'secondary') ?>"><?= (int)$r['DaysOverdue'] ?> days</span></td>
        <td style="font-weight:700;color:var(--danger);"><?= money($r['BrokenAmount']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state">No clients currently in arrears <i class="bi bi-stars"></i></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($reportTab === 'defaults'): ?>
<div class="card-x">
  <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>
      Default Loans <span style="font-weight:400;font-size:12px;color:var(--text-muted);">— loan tenure has ended, balance (principal + interest) still outstanding</span>
      <span class="badge badge-danger"><?= number_format(count($defaultRows)) ?> loans · <?= money($defaultTotal) ?></span>
    </span>
    <a href="<?= reportTabUrl('defaults') ?>&export=csv" class="btn-outline-x" style="font-size:12px;padding:5px 12px;"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <?php if ($defaultRows): ?>
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Loan No</th><th>Cycle</th><th>Client</th><th>Branch</th><th>Center</th><th>Officer</th><th>Tenure Ended On</th><th>Days Past Tenure</th><th>Outstanding (P+I)</th></tr>
      <?php foreach ($defaultRows as $r): ?>
      <tr>
        <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
        <td><?= $r['LoanCycle'] !== null ? (int)$r['LoanCycle'] : '-' ?></td>
        <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?><br><span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($r['MobileNo'] ?? '-') ?></span></td>
        <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['OfficerName'] ?? 'Unassigned') ?></td>
        <td><?= fdate($r['LastDueDate']) ?></td>
        <td><span class="badge badge-danger"><?= (int)$r['DaysPastTenure'] ?> days</span></td>
        <td style="font-weight:700;color:var(--danger);"><?= money($r['Outstanding']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state">No defaulted loans <i class="bi bi-stars"></i></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($reportTab === 'arrear_collection'): ?>
<div class="card-x mb-3">
  <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
    <input type="hidden" name="tab" value="arrear_collection">
    <div><label class="form-label">From</label><input type="date" class="form-control" name="ac_from" value="<?= htmlspecialchars($acFrom) ?>" required></div>
    <div><label class="form-label">To</label><input type="date" class="form-control" name="ac_to" value="<?= htmlspecialchars($acTo) ?>" required></div>
    <button class="btn-navy" type="submit">Apply</button>
  </form>
</div>
<div class="card-x">
  <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>Arrear Collection — <?= fdate($acFrom) ?> to <?= fdate($acTo) ?> <span class="badge badge-success"><?= number_format(count($arrearCollectionRows)) ?> payments · <?= money($arrearCollectionTotal) ?></span></span>
    <a href="reports.php?tab=arrear_collection&ac_from=<?= urlencode($acFrom) ?>&ac_to=<?= urlencode($acTo) ?>&export=csv" class="btn-outline-x" style="font-size:12px;padding:5px 12px;"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <?php if ($arrearCollectionRows): ?>
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Collected On</th><th>Loan No</th><th>Cycle</th><th>Client</th><th>Branch</th><th>Center</th><th>Officer</th><th>Inst. No</th><th>Due Date</th><th>Days Late</th><th>Amount</th></tr>
      <?php foreach ($arrearCollectionRows as $r): ?>
      <tr>
        <td><?= fdate($r['CollectionDate']) ?></td>
        <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
        <td><?= $r['LoanCycle'] !== null ? (int)$r['LoanCycle'] : '-' ?></td>
        <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?><br><span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($r['MobileNo'] ?? '-') ?></span></td>
        <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['OfficerName'] ?? 'Unassigned') ?></td>
        <td>#<?= (int)$r['InstallmentNo'] ?></td>
        <td><?= fdate($r['DueDate']) ?></td>
        <td><span class="badge badge-<?= $r['DaysLate'] > 90 ? 'danger' : ($r['DaysLate'] > 30 ? 'warning' : 'secondary') ?>"><?= (int)$r['DaysLate'] ?> days</span></td>
        <td style="font-weight:700;color:var(--success);"><?= money($r['TotalCollected']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state">No arrear recoveries in this range.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($reportTab === 'default_collection'): ?>
<div class="card-x mb-3">
  <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
    <input type="hidden" name="tab" value="default_collection">
    <div><label class="form-label">From</label><input type="date" class="form-control" name="dc_from" value="<?= htmlspecialchars($dcFrom) ?>" required></div>
    <div><label class="form-label">To</label><input type="date" class="form-control" name="dc_to" value="<?= htmlspecialchars($dcTo) ?>" required></div>
    <button class="btn-navy" type="submit">Apply</button>
  </form>
</div>
<div class="card-x">
  <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>Default Collection — <?= fdate($dcFrom) ?> to <?= fdate($dcTo) ?> <span class="badge badge-success"><?= number_format(count($defaultCollectionRows)) ?> payments · <?= money($defaultCollectionTotal) ?></span></span>
    <a href="reports.php?tab=default_collection&dc_from=<?= urlencode($dcFrom) ?>&dc_to=<?= urlencode($dcTo) ?>&export=csv" class="btn-outline-x" style="font-size:12px;padding:5px 12px;"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <?php if ($defaultCollectionRows): ?>
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Collected On</th><th>Loan No</th><th>Cycle</th><th>Client</th><th>Branch</th><th>Center</th><th>Officer</th><th>Tenure Ended On</th><th>Amount</th></tr>
      <?php foreach ($defaultCollectionRows as $r): ?>
      <tr>
        <td><?= fdate($r['CollectionDate']) ?></td>
        <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
        <td><?= $r['LoanCycle'] !== null ? (int)$r['LoanCycle'] : '-' ?></td>
        <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?><br><span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($r['MobileNo'] ?? '-') ?></span></td>
        <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['OfficerName'] ?? 'Unassigned') ?></td>
        <td><?= fdate($r['LastDueDate']) ?></td>
        <td style="font-weight:700;color:var(--success);"><?= money($r['TotalCollected']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state">No default-loan recoveries in this range.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($reportTab === 'today_preclose'): ?>
<div class="card-x mb-3">
  <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
    <input type="hidden" name="tab" value="today_preclose">
    <div><label class="form-label">From</label><input type="date" class="form-control" name="tp_from" value="<?= htmlspecialchars($tpFrom) ?>" required></div>
    <div><label class="form-label">To</label><input type="date" class="form-control" name="tp_to" value="<?= htmlspecialchars($tpTo) ?>" required></div>
    <button class="btn-navy" type="submit">Apply</button>
  </form>
</div>
<div class="card-x">
  <div class="section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>Today Preclose — <?= fdate($tpFrom) ?> to <?= fdate($tpTo) ?> <span class="badge badge-success"><?= number_format(count($todayPrecloseRows)) ?> preclosed · <?= money($todayPrecloseTotal) ?></span></span>
    <a href="reports.php?tab=today_preclose&tp_from=<?= urlencode($tpFrom) ?>&tp_to=<?= urlencode($tpTo) ?>&export=csv" class="btn-outline-x" style="font-size:12px;padding:5px 12px;"><i class="bi bi-download"></i> Export CSV</a>
  </div>
  <?php if ($todayPrecloseRows): ?>
  <div class="table-scroll">
    <table class="table-x">
      <tr><th>Preclose Date</th><th>Loan No</th><th>Cycle</th><th>Client</th><th>Branch</th><th>Center</th><th>Officer</th><th>Approved By</th><th>Amount</th></tr>
      <?php foreach ($todayPrecloseRows as $r): ?>
      <tr>
        <td><?= fdate($r['ReviewedAt']) ?></td>
        <td><a href="loan_view.php?id=<?= $r['LoanID'] ?>"><?= htmlspecialchars($r['LoanNo']) ?></a></td>
        <td><?= $r['LoanCycle'] !== null ? (int)$r['LoanCycle'] : '-' ?></td>
        <td><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?><br><span style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($r['MobileNo'] ?? '-') ?></span></td>
        <td><?= htmlspecialchars($r['BranchName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['CenterName'] ?? '-') ?></td>
        <td><?= htmlspecialchars($r['OfficerName'] ?? 'Unassigned') ?></td>
        <td><?= htmlspecialchars($r['ApprovedByName'] ?? '-') ?></td>
        <td style="font-weight:700;color:var(--success);"><?= $r['PrecloseQuoteAmount'] !== null ? money($r['PrecloseQuoteAmount']) : '-' ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php else: ?>
    <div class="empty-state">No preclosures in this range.</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
