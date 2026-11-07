import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/** GET /api/members/lookups -- dropdown data for the Members list filters. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const [branches, stages, officers] = await Promise.all([
    pool.request().query(`SELECT BranchID AS id, BranchName AS name FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`),
    pool.request().query(`SELECT StageID AS id, StageName AS name FROM Lead_Stage_Master ORDER BY SortOrder`),
    pool.request().query(`SELECT StaffID AS id, FullName AS name FROM Staff WHERE IsActive = 1 ORDER BY FullName`),
  ]);
  res.json({
    success: true,
    data: { branches: branches.recordset, stages: stages.recordset, officers: officers.recordset },
  });
});

/**
 * GET /api/members
 * Query: page, perPage, branch, stage, officer, q
 */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(100, Math.max(1, Number(req.query.perPage) || 20));
  const branch = req.query.branch ? Number(req.query.branch) : null;
  const stage = req.query.stage ? Number(req.query.stage) : null;
  const officer = req.query.officer ? Number(req.query.officer) : null;
  const q = (req.query.q as string | undefined)?.trim();

  const conditions: string[] = [];
  if (branch) conditions.push('m.BranchID = @branch');
  if (stage) conditions.push('m.CurrentStageID = @stage');
  if (officer) conditions.push('m.AssignedToStaffID = @officer');
  if (q) conditions.push('(m.FirstName LIKE @q OR m.LastName LIKE @q OR m.MobileNo LIKE @q OR m.EnrollmentNo LIKE @q)');
  const whereSql = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';

  const pool = await getPool();

  function bindFilters(request: ReturnType<typeof pool.request>) {
    if (branch) request.input('branch', sql.Int, branch);
    if (stage) request.input('stage', sql.Int, stage);
    if (officer) request.input('officer', sql.Int, officer);
    if (q) request.input('q', sql.NVarChar, `%${q}%`);
    return request;
  }

  if (req.query.format === 'csv') {
    const csvResult = await bindFilters(pool.request()).query(`
      SELECT m.EnrollmentNo AS enrollmentNo, m.FirstName AS firstName, m.LastName AS lastName, m.MobileNo AS mobileNo,
             b.BranchName AS branchName, c.CenterName AS centerName, ls.StageName AS stageName, st.FullName AS assignee
      FROM Member m
      LEFT JOIN Branch b ON m.BranchID = b.BranchID
      LEFT JOIN Center c ON m.CenterID = c.CenterID
      LEFT JOIN Lead_Stage_Master ls ON m.CurrentStageID = ls.StageID
      LEFT JOIN Staff st ON m.AssignedToStaffID = st.StaffID
      ${whereSql}
      ORDER BY m.MemberID DESC
    `);
    const header = ['Enrollment No', 'Name', 'Mobile', 'Branch', 'Center', 'Stage', 'Assigned To'];
    const escape = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = csvResult.recordset.map((r) =>
      [r.enrollmentNo, `${r.firstName} ${r.lastName}`.trim(), r.mobileNo, r.branchName, r.centerName, r.stageName, r.assignee ?? 'Unassigned']
        .map(escape)
        .join(','),
    );
    const csv = [header.map(escape).join(','), ...rows].join('\r\n');
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', 'attachment; filename="members.csv"');
    return res.send(csv);
  }

  const total = (await bindFilters(pool.request()).query(`SELECT COUNT(*) AS total FROM Member m ${whereSql}`)).recordset[0].total;

  const listReq = bindFilters(pool.request());
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);
  const listResult = await listReq.query(`
    SELECT m.MemberID AS memberId, m.EnrollmentNo AS enrollmentNo, m.FirstName AS firstName, m.LastName AS lastName,
           m.MobileNo AS mobileNo, m.Gender AS gender, m.CreatedAt AS createdAt,
           b.BranchName AS branchName, c.CenterName AS centerName,
           ls.StageName AS stageName, st.FullName AS assignee,
           ISNULL(al.activeLoanCount, 0) AS activeLoanCount, al.activeLoanAmount AS activeLoanAmount
    FROM Member m
    LEFT JOIN Branch b ON m.BranchID = b.BranchID
    LEFT JOIN Center c ON m.CenterID = c.CenterID
    LEFT JOIN Lead_Stage_Master ls ON m.CurrentStageID = ls.StageID
    LEFT JOIN Staff st ON m.AssignedToStaffID = st.StaffID
    LEFT JOIN (
      SELECT l.MemberID, COUNT(*) AS activeLoanCount, SUM(COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied)) AS activeLoanAmount
      FROM Loan l
      JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      WHERE stg.StageName NOT IN ('Closed', 'Rejected', 'Preclosed')
      GROUP BY l.MemberID
    ) al ON al.MemberID = m.MemberID
    ${whereSql}
    ORDER BY m.MemberID DESC
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  const statsResult = await pool.request().query(`
    SELECT
      (SELECT COUNT(*) FROM Member) AS totalMembers,
      (SELECT COUNT(*) FROM Member m JOIN Lead_Stage_Master s ON m.CurrentStageID = s.StageID WHERE s.StageName = 'New Lead') AS newLeads,
      (SELECT COUNT(*) FROM Member m JOIN Lead_Stage_Master s ON m.CurrentStageID = s.StageID WHERE s.StageName = 'Converted') AS converted
  `);
  const stats = statsResult.recordset[0];
  const conversionRate = stats.totalMembers > 0 ? Math.round((stats.converted / stats.totalMembers) * 1000) / 10 : 0;

  res.json({
    success: true,
    data: {
      items: listResult.recordset,
      total,
      page,
      perPage,
      stats: { ...stats, conversionRate },
    },
  });
});

/**
 * GET /api/members/:id
 * Full profile for the Member Detail page -- vw_Member_Full (same view the
 * PHP member_view.php uses) plus loans, KYC documents and bank details.
 * The client photo isn't Member.Photo (never populated in the migrated
 * data) -- it's the latest Member_KYC_Document row of type
 * 'Client Photograph', same lookup member_view.php itself does.
 */
router.get('/:id', async (req, res) => {
  const memberId = Number(req.params.id);
  const pool = await getPool();

  const profileResult = await pool.request().input('id', sql.Int, memberId).query(`
    SELECT * FROM vw_Member_Full WHERE MemberID = @id
  `);
  const profile = profileResult.recordset[0];
  if (!profile) return res.status(404).json({ success: false, message: 'Member not found' });

  const [photoResult, loansResult, kycResult, bankResult, journeyResult, coBorrowersResult, centerResult] = await Promise.all([
    pool.request().input('id', sql.Int, memberId).query(`
      SELECT TOP 1 k.DocFilePath FROM Member_KYC_Document k
      JOIN KYC_Document_Type_Master dt ON k.DocTypeID = dt.DocTypeID
      WHERE k.MemberID = @id AND dt.DocTypeName = 'Client Photograph' AND k.CoBorrowerID IS NULL
      ORDER BY k.UploadedAt DESC
    `),
    pool.request().input('id', sql.Int, memberId).query(`
      SELECT l.LoanID AS loanId, l.LoanNo AS loanNo, l.LoanAmountSanctioned AS loanAmountSanctioned,
             l.LoanAmountApplied AS loanAmountApplied, l.ApplicationDate AS applicationDate,
             l.DisbursementDate AS disbursementDate, stg.StageName AS stageName
      FROM Loan l
      LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      WHERE l.MemberID = @id
      ORDER BY l.LoanID DESC
    `),
    pool.request().input('id', sql.Int, memberId).query(`
      SELECT k.KYCDocID AS kycDocId, dt.DocTypeName AS docTypeName, k.DocNumber AS docNumber,
             k.DocFilePath AS docFilePath, k.IsVerified AS isVerified, k.UploadedAt AS uploadedAt
      FROM Member_KYC_Document k
      JOIN KYC_Document_Type_Master dt ON k.DocTypeID = dt.DocTypeID
      WHERE k.MemberID = @id AND k.CoBorrowerID IS NULL
      ORDER BY k.UploadedAt DESC
    `),
    pool.request().input('id', sql.Int, memberId).query(`
      SELECT BankName AS bankName, IFSCCode AS ifscCode, BankBranch AS bankBranch,
             AccountNumber AS accountNumber, BeneficiaryName AS beneficiaryName
      FROM Member_Bank_Details WHERE MemberID = @id
    `),
    pool.request().input('id', sql.Int, memberId).query(MEMBER_JOURNEY_SQL),
    pool.request().input('id', sql.Int, memberId).query(`
      SELECT CoBorrowerID AS coBorrowerId, FirstName AS firstName, MiddleName AS middleName, LastName AS lastName,
             RelationToMember AS relationToMember, Gender AS gender, DOB AS dob, MaritalStatus AS maritalStatus,
             MobileNo AS mobileNo, Address AS address, VillageCity AS villageCity, Pincode AS pincode,
             AadharNo AS aadharNo, PANNo AS panNo, VoterID AS voterId
      FROM Co_Borrower WHERE MemberID = @id ORDER BY CoBorrowerID
    `),
    pool.request().input('id', sql.Int, memberId).query(`
      SELECT c.CenterCode AS centerCode, c.MeetingDay AS meetingDay, c.MeetingTime AS meetingTime,
             c.CenterPlace AS place, c.Address AS address, c.ContactPerson AS contactPerson, c.ContactNo AS contactNo
      FROM Member m JOIN Center c ON m.CenterID = c.CenterID
      WHERE m.MemberID = @id
    `),
  ]);

  const journey = journeyResultFrom(journeyResult);

  res.json({
    success: true,
    data: {
      profile,
      photoUrl: photoResult.recordset[0]?.DocFilePath ?? null,
      loans: loansResult.recordset,
      kycDocuments: kycResult.recordset,
      bankDetails: bankResult.recordset[0] ?? null,
      journey,
      coBorrowers: coBorrowersResult.recordset,
      center: centerResult.recordset[0] ?? null,
    },
  });
});

/**
 * Every touchpoint across the lead + all its loans, newest first -- same
 * unified timeline member_view.php builds (lead creation, stage changes,
 * interactions, notes, KYC uploads, loan applied/stage-changed, GRT, TVR,
 * disbursement, payments, tasks). One @id param reused across every UNION
 * branch (named params don't need repeating like PHP's positional `?`s do).
 */
const MEMBER_JOURNEY_SQL = `
  SELECT CAST(COALESCE(mm.EnrollmentDate, mm.CreatedAt) AS DATETIME) AS eventDate,
         'Lead Created' AS eventType,
         CAST('Lead created' AS NVARCHAR(MAX)) AS eventTitle,
         CAST(NULL AS NVARCHAR(MAX)) AS eventDetail,
         CAST(NULL AS NVARCHAR(50)) AS relatedLoanNo,
         cs.FullName AS performedBy,
         CAST(NULL AS DECIMAL(18,2)) AS amount
  FROM Member mm
  LEFT JOIN Staff cs ON mm.CreatedBy = cs.StaffID
  WHERE mm.MemberID = @id

  UNION ALL
  SELECT CAST(h.ChangedAt AS DATETIME), 'Stage Change',
         CAST(COALESCE(f.StageName,'Created') + ' -> ' + t.StageName + CASE WHEN h.ChangeDirection = 'Backward' THEN ' (moved back)' ELSE '' END AS NVARCHAR(MAX)),
         CAST(CASE WHEN h.Remarks IS NOT NULL AND h.Remarks <> 'No reason given' THEN h.Remarks ELSE NULL END AS NVARCHAR(MAX)),
         CAST(NULL AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Lead_Stage_History h
  LEFT JOIN Lead_Stage_Master f ON h.FromStageID = f.StageID
  LEFT JOIN Lead_Stage_Master t ON h.ToStageID = t.StageID
  LEFT JOIN Staff s ON h.ChangedByStaffID = s.StaffID
  WHERE h.MemberID = @id

  UNION ALL
  SELECT CAST(i.InteractionDate AS DATETIME), 'Interaction',
         CAST(ISNULL(it.TypeName,'Interaction') + ' - ' + ISNULL(io.OutcomeName,'') AS NVARCHAR(MAX)),
         CAST(i.Remarks AS NVARCHAR(MAX)),
         CAST(NULL AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Interaction i
  LEFT JOIN Interaction_Type_Master it ON i.InteractionTypeID = it.TypeID
  LEFT JOIN Interaction_Outcome_Master io ON i.OutcomeID = io.OutcomeID
  LEFT JOIN Staff s ON i.StaffID = s.StaffID
  WHERE i.MemberID = @id

  UNION ALL
  SELECT CAST(n.CreatedAt AS DATETIME), 'Note',
         CAST('Note added' AS NVARCHAR(MAX)), CAST(n.NoteText AS NVARCHAR(MAX)),
         CAST(NULL AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Note n
  LEFT JOIN Staff s ON n.StaffID = s.StaffID
  WHERE n.RelatedEntityType = 'Member' AND n.RelatedEntityID = @id

  UNION ALL
  SELECT CAST(k.UploadedAt AS DATETIME), 'KYC Document',
         CAST(dt.DocTypeName + ' uploaded' AS NVARCHAR(MAX)),
         CAST(CASE WHEN k.IsVerified = 1 THEN 'Verified' ELSE 'Pending verification' END AS NVARCHAR(MAX)),
         CAST(NULL AS NVARCHAR(50)), vs.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Member_KYC_Document k
  JOIN KYC_Document_Type_Master dt ON k.DocTypeID = dt.DocTypeID
  LEFT JOIN Staff vs ON k.VerifiedByStaffID = vs.StaffID
  WHERE k.MemberID = @id AND k.UploadedAt IS NOT NULL

  UNION ALL
  SELECT CAST(l.ApplicationDate AS DATETIME), 'Loan Applied',
         CAST('Loan application submitted' AS NVARCHAR(MAX)), CAST(NULL AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), cs2.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Loan l
  LEFT JOIN Staff cs2 ON l.CreatedBy = cs2.StaffID
  WHERE l.MemberID = @id AND l.ApplicationDate IS NOT NULL

  UNION ALL
  SELECT CAST(h.ChangedAt AS DATETIME), 'Loan Stage Change',
         CAST(COALESCE(f.StageName,'Created') + ' -> ' + t.StageName + CASE WHEN h.ChangeDirection = 'Backward' THEN ' (moved back)' ELSE '' END AS NVARCHAR(MAX)),
         CAST(CASE WHEN h.Remarks IS NOT NULL AND h.Remarks <> 'No reason given' THEN h.Remarks ELSE NULL END AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Loan_Stage_History h
  JOIN Loan l ON h.LoanID = l.LoanID
  LEFT JOIN Loan_Stage_Master f ON h.FromStageID = f.StageID
  LEFT JOIN Loan_Stage_Master t ON h.ToStageID = t.StageID
  LEFT JOIN Staff s ON h.ChangedByStaffID = s.StaffID
  WHERE l.MemberID = @id

  UNION ALL
  SELECT CAST(g.GRTDate AS DATETIME), 'GRT',
         CAST('GRT ' + CASE gs.GRTStatusName WHEN 'P' THEN 'Passed' WHEN 'F' THEN 'Failed' ELSE gs.GRTStatusName END AS NVARCHAR(MAX)),
         CAST(g.Remarks AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM GRT_Details g
  JOIN Loan l ON g.LoanID = l.LoanID
  LEFT JOIN GRT_Status_Master gs ON g.GRTStatusID = gs.GRTStatusID
  LEFT JOIN Staff s ON g.ConductedByStaffID = s.StaffID
  WHERE l.MemberID = @id AND g.GRTDate IS NOT NULL

  UNION ALL
  SELECT CAST(tv.TVRDate AS DATETIME), 'TVR',
         CAST('TVR ' + ts.TVRStatusName AS NVARCHAR(MAX)),
         CAST(tv.VerificationNotes AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), s.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM TVR_Details tv
  JOIN Loan l ON tv.LoanID = l.LoanID
  LEFT JOIN TVR_Status_Master ts ON tv.TVRStatusID = ts.TVRStatusID
  LEFT JOIN Staff s ON tv.ConductedByStaffID = s.StaffID
  WHERE l.MemberID = @id AND tv.TVRDate IS NOT NULL

  UNION ALL
  SELECT CAST(l.DisbursementDate AS DATETIME), 'Disbursement',
         CAST('Loan disbursed' AS NVARCHAR(MAX)), CAST(NULL AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), CAST(NULL AS NVARCHAR(200)),
         COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied)
  FROM Loan l
  WHERE l.MemberID = @id AND l.DisbursementDate IS NOT NULL

  UNION ALL
  SELECT CAST(c.CollectionDate AS DATETIME), 'Payment',
         CAST('EMI collected' AS NVARCHAR(MAX)), CAST(NULL AS NVARCHAR(MAX)),
         CAST(l.LoanNo AS NVARCHAR(50)), s.FullName, c.TotalCollected
  FROM Collection c
  JOIN Loan l ON c.LoanID = l.LoanID
  LEFT JOIN Staff s ON c.CollectedByStaffID = s.StaffID
  WHERE l.MemberID = @id

  UNION ALL
  SELECT CAST(t.CreatedAt AS DATETIME), 'Task Created',
         CAST(t.Title AS NVARCHAR(MAX)), CAST(t.Description AS NVARCHAR(MAX)),
         CAST(ml.LoanNo AS NVARCHAR(50)), cs3.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Task t
  LEFT JOIN Loan ml ON t.RelatedEntityType = 'Loan' AND t.RelatedEntityID = ml.LoanID
  LEFT JOIN Staff cs3 ON t.CreatedByStaffID = cs3.StaffID
  WHERE (t.RelatedEntityType = 'Member' AND t.RelatedEntityID = @id)
     OR (t.RelatedEntityType = 'Loan' AND t.RelatedEntityID IN (SELECT LoanID FROM Loan WHERE MemberID = @id))

  UNION ALL
  SELECT CAST(t.CompletedAt AS DATETIME), 'Task Completed',
         CAST(t.Title AS NVARCHAR(MAX)), CAST(NULL AS NVARCHAR(MAX)),
         CAST(ml.LoanNo AS NVARCHAR(50)), asf.FullName, CAST(NULL AS DECIMAL(18,2))
  FROM Task t
  LEFT JOIN Loan ml ON t.RelatedEntityType = 'Loan' AND t.RelatedEntityID = ml.LoanID
  LEFT JOIN Staff asf ON t.AssignedToStaffID = asf.StaffID
  WHERE t.CompletedAt IS NOT NULL
    AND ((t.RelatedEntityType = 'Member' AND t.RelatedEntityID = @id)
     OR (t.RelatedEntityType = 'Loan' AND t.RelatedEntityID IN (SELECT LoanID FROM Loan WHERE MemberID = @id)))

  ORDER BY eventDate DESC
`;

function journeyResultFrom(result: { recordset: Record<string, unknown>[] }) {
  return result.recordset
    .filter((r) => r.eventDate != null)
    .map((r) => ({
      eventDate: r.eventDate,
      eventType: r.eventType,
      eventTitle: r.eventTitle,
      eventDetail: r.eventDetail ?? (r.amount != null ? `Amount: ${r.amount}` : null),
      relatedLoanNo: r.relatedLoanNo,
      performedBy: r.performedBy,
      amount: r.amount,
    }));
}

export default router;
