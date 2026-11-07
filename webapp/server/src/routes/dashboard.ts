import { Router } from 'express';
import { getPool } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

/**
 * GET /api/dashboard/summary
 * Same underlying views/tables the PHP dashboard.php uses (vw_Loan_Pipeline,
 * vw_Lead_Funnel, vw_Branch_Portfolio_Summary, vw_Overdue_Installments) --
 * one aggregated payload instead of a dozen round trips, since the React
 * dashboard renders it all on one screen.
 */
router.get('/summary', requireAuth, async (_req, res) => {
  const pool = await getPool();

  const [
    kpis,
    loanPipeline,
    leadFunnel,
    branchPortfolio,
    todayVsYesterday,
    monthVsLastMonth,
    recentOverdue,
    recentLeads,
  ] = await Promise.all([
    pool.request().query(`
      SELECT
        (SELECT COUNT(*) FROM Member) AS totalMembers,
        (SELECT COALESCE(LoanCount,0) FROM vw_Loan_Pipeline WHERE StageName = 'Active') AS activeLoans,
        (SELECT COALESCE(SUM(OutstandingPortfolio),0) FROM vw_Branch_Portfolio_Summary WHERE BranchName NOT LIKE '%HEAD OFFICE%') AS outstandingPortfolio,
        (SELECT COALESCE(SUM(LifetimeDisbursed),0) FROM vw_Branch_Portfolio_Summary WHERE BranchName NOT LIKE '%HEAD OFFICE%') AS lifetimeDisbursed,
        (SELECT COUNT(*) FROM vw_Overdue_Installments) AS overdueCount,
        (SELECT COALESCE(SUM(EMIAmount),0) FROM vw_Overdue_Installments) AS overdueAmount
    `),
    pool.request().query(`SELECT StageName AS stage, LoanCount AS count, TotalAmount AS amount FROM vw_Loan_Pipeline ORDER BY SortOrder`),
    pool.request().query(`SELECT StageName AS stage, MemberCount AS count FROM vw_Lead_Funnel ORDER BY SortOrder`),
    pool.request().query(`
      SELECT BranchName AS branch, OutstandingPortfolio AS portfolio, LifetimeDisbursed AS disbursed
      FROM vw_Branch_Portfolio_Summary WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY OutstandingPortfolio DESC
    `),
    pool.request().query(`
      SELECT
        (SELECT COALESCE(SUM(EMIAmount),0) FROM Daily_Collection_Sheet WHERE CollectionDueDate = CAST(GETDATE() AS DATE)) AS todayDue,
        (SELECT COALESCE(SUM(CollectionAmount),0) FROM Daily_Collection_Sheet WHERE CollectionDueDate = CAST(GETDATE() AS DATE)) AS todayCollected,
        (SELECT COALESCE(SUM(EMIAmount),0) FROM Daily_Collection_Sheet WHERE CollectionDueDate = CAST(DATEADD(DAY,-1,GETDATE()) AS DATE)) AS yesterdayDue,
        (SELECT COALESCE(SUM(CollectionAmount),0) FROM Daily_Collection_Sheet WHERE CollectionDueDate = CAST(DATEADD(DAY,-1,GETDATE()) AS DATE)) AS yesterdayCollected
    `),
    pool.request().query(`
      SELECT
        (SELECT COUNT(*) FROM Loan WHERE YEAR(DisbursementDate)=YEAR(GETDATE()) AND MONTH(DisbursementDate)=MONTH(GETDATE())) AS monthDisbCount,
        (SELECT COALESCE(SUM(TotalCollected),0) FROM Collection WHERE YEAR(CollectionDate)=YEAR(GETDATE()) AND MONTH(CollectionDate)=MONTH(GETDATE())) AS monthCollection,
        (SELECT COUNT(*) FROM Loan WHERE YEAR(DisbursementDate)=YEAR(DATEADD(MONTH,-1,GETDATE())) AND MONTH(DisbursementDate)=MONTH(DATEADD(MONTH,-1,GETDATE()))) AS lastMonthDisbCount,
        (SELECT COALESCE(SUM(TotalCollected),0) FROM Collection WHERE YEAR(CollectionDate)=YEAR(DATEADD(MONTH,-1,GETDATE())) AND MONTH(CollectionDate)=MONTH(DATEADD(MONTH,-1,GETDATE()))) AS lastMonthCollection
    `),
    pool.request().query(`
      SELECT TOP 6 LoanID AS loanId, LoanNo AS loanNo, FirstName AS firstName, LastName AS lastName,
             BranchName AS branchName, DueDate AS dueDate, EMIAmount AS emiAmount, DaysOverdue AS daysOverdue
      FROM vw_Overdue_Installments ORDER BY DaysOverdue DESC
    `),
    pool.request().query(`
      SELECT TOP 6 MemberID AS memberId, FirstName AS firstName, LastName AS lastName,
             BranchName AS branchName, CurrentStage AS stageName
      FROM vw_Member_Full ORDER BY MemberID DESC
    `),
  ]);

  res.json({
    success: true,
    data: {
      kpis: kpis.recordset[0],
      loanPipeline: loanPipeline.recordset,
      leadFunnel: leadFunnel.recordset,
      branchPortfolio: branchPortfolio.recordset,
      todayVsYesterday: todayVsYesterday.recordset[0],
      monthVsLastMonth: monthVsLastMonth.recordset[0],
      recentOverdue: recentOverdue.recordset,
      recentLeads: recentLeads.recordset,
    },
  });
});

export default router;
