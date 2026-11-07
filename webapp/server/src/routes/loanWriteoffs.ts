import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAdmin, requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth, requireAdmin);

/** GET /api/loan-writeoffs -- pending NPA write-off requests + decision history. Admin/SuperAdmin only. */
router.get('/', async (_req, res) => {
  const pool = await getPool();
  const [pending, history] = await Promise.all([
    pool.request().query(`
      SELECT wr.RequestID AS requestId, wr.LoanID AS loanId, wr.RequestReason AS requestReason, wr.OutstandingAmount AS outstandingAmount,
             wr.CreatedAt AS createdAt, l.LoanNo AS loanNo, m.FirstName AS firstName, m.LastName AS lastName,
             b.BranchName AS branchName, c.CenterName AS centerName, req.FullName AS requestedByName
      FROM Loan_WriteOff_Request wr
      JOIN Loan l ON wr.LoanID = l.LoanID
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Center c ON l.CenterID = c.CenterID
      LEFT JOIN Staff req ON wr.RequestedByStaffID = req.StaffID
      WHERE wr.Status = 'Pending'
      ORDER BY wr.CreatedAt ASC
    `),
    pool.request().query(`
      SELECT wr.RequestID AS requestId, wr.LoanID AS loanId, wr.Status AS status, wr.ReviewedAt AS reviewedAt,
             wr.OutstandingAmount AS outstandingAmount, wr.ReviewRemarks AS reviewRemarks,
             l.LoanNo AS loanNo, m.FirstName AS firstName, m.LastName AS lastName, rev.FullName AS reviewedByName
      FROM Loan_WriteOff_Request wr
      JOIN Loan l ON wr.LoanID = l.LoanID
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Staff rev ON wr.ReviewedByStaffID = rev.StaffID
      WHERE wr.Status != 'Pending'
      ORDER BY wr.ReviewedAt DESC
    `),
  ]);
  res.json({ success: true, data: { pending: pending.recordset, history: history.recordset } });
});

/**
 * POST /api/loan-writeoffs/:id/review
 * Approve closes the loan (CurrentStageID='Closed') and tags it
 * SettlementTypeID='Written Off' -- no Repayment_Schedule rows are touched,
 * same as loan_writeoffs.php (closing the stage is what removes it from
 * Active-loan collection worklists).
 */
router.post('/:id/review', async (req, res) => {
  const requestId = Number(req.params.id);
  const action = req.body.action as 'approve' | 'reject';
  const remarks = (req.body.remarks as string | undefined)?.trim() || null;
  if (!['approve', 'reject'].includes(action)) {
    return res.status(400).json({ success: false, message: 'Invalid action' });
  }

  const pool = await getPool();
  const reqRow = (
    await pool.request().input('id', sql.Int, requestId).query(`SELECT RequestID, LoanID FROM Loan_WriteOff_Request WHERE RequestID = @id AND Status = 'Pending'`)
  ).recordset[0];
  if (!reqRow) return res.status(404).json({ success: false, message: 'Write-off request not found or already reviewed' });

  const newStatus = action === 'approve' ? 'Approved' : 'Rejected';
  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    const updateReq = new sql.Request(transaction);
    updateReq.input('status', sql.NVarChar, newStatus);
    updateReq.input('staffId', sql.Int, req.staff!.staffId);
    updateReq.input('remarks', sql.NVarChar, remarks);
    updateReq.input('id', sql.Int, requestId);
    await updateReq.query(`
      UPDATE Loan_WriteOff_Request SET Status = @status, ReviewedByStaffID = @staffId, ReviewedAt = GETDATE(), ReviewRemarks = @remarks WHERE RequestID = @id
    `);
    if (action === 'approve') {
      const loanReq = new sql.Request(transaction);
      loanReq.input('loanId', sql.Int, reqRow.LoanID);
      await loanReq.query(`
        UPDATE Loan SET
          CurrentStageID = (SELECT StageID FROM Loan_Stage_Master WHERE StageName = 'Closed'),
          SettlementTypeID = (SELECT SettlementTypeID FROM Settlement_Type_Master WHERE SettlementTypeName = 'Written Off')
        WHERE LoanID = @loanId
      `);
    }
    await transaction.commit();
  } catch (err) {
    await transaction.rollback();
    throw err;
  }

  res.json({ success: true, data: { status: newStatus } });
});

export default router;
