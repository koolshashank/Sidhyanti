import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';
import { shiftDueDateToWeekday } from '../utils/dates.js';
import { notifyStaff } from '../utils/notify.js';
import { roleHasPermission } from '../utils/permissions.js';

const router = Router();
router.use(requireAuth);

const DAY_NAMES: Record<number, string> = {
  1: 'Monday',
  2: 'Tuesday',
  3: 'Wednesday',
  4: 'Thursday',
  5: 'Friday',
  6: 'Saturday',
  7: 'Sunday',
};

/** GET /api/centers/lookups -- dropdown data for the Centers list/edit form. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const [branches, staff] = await Promise.all([
    pool.request().query(`SELECT BranchID AS id, BranchName AS name FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`),
    pool.request().query(`SELECT StaffID AS id, FullName AS name, BranchID AS branchId FROM Staff WHERE IsActive = 1 ORDER BY FullName`),
  ]);
  res.json({ success: true, data: { branches: branches.recordset, staff: staff.recordset } });
});

/**
 * GET /api/centers
 * Query: page, perPage, branch, day (1-7, Center.MeetingDay's own numbering --
 * see centers.php), status (active|inactive), q
 */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(100, Math.max(1, Number(req.query.perPage) || 20));
  const branch = req.query.branch ? Number(req.query.branch) : null;
  const day = req.query.day ? Number(req.query.day) : null;
  const status = req.query.status as string | undefined;
  const q = (req.query.q as string | undefined)?.trim();

  const conditions: string[] = [];
  if (branch) conditions.push('c.BranchID = @branch');
  if (day) conditions.push('c.MeetingDay = @day');
  if (status === 'active') conditions.push('c.IsActive = 1');
  else if (status === 'inactive') conditions.push('c.IsActive = 0');
  if (q) conditions.push('(c.CenterName LIKE @q OR c.CenterCode LIKE @q OR c.ContactPerson LIKE @q OR c.ContactNo LIKE @q)');
  const whereSql = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';

  const pool = await getPool();
  function bindFilters(request: ReturnType<typeof pool.request>) {
    if (branch) request.input('branch', sql.Int, branch);
    if (day) request.input('day', sql.Int, day);
    if (q) request.input('q', sql.NVarChar, `%${q}%`);
    return request;
  }

  const total = (await bindFilters(pool.request()).query(`SELECT COUNT(*) AS total FROM Center c ${whereSql}`)).recordset[0].total;

  const listReq = bindFilters(pool.request());
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);
  const listResult = await listReq.query(`
    SELECT c.CenterID AS centerId, c.CenterCode AS centerCode, c.CenterName AS centerName, c.CenterPlace AS centerPlace,
           c.Address AS address, c.BranchID AS branchId, b.BranchName AS branchName, c.ContactPerson AS contactPerson,
           c.ContactNo AS contactNo, c.MeetingDay AS meetingDay, c.MeetingTime AS meetingTime, c.ManagedByStaffID AS managedByStaffId,
           u.FullName AS managerName, c.IsActive AS isActive, c.Latitude AS latitude, c.Longitude AS longitude,
           (SELECT COUNT(*) FROM Member m WHERE m.CenterID = c.CenterID) AS memberCount
    FROM Center c
    LEFT JOIN Branch b ON c.BranchID = b.BranchID
    LEFT JOIN Staff u ON c.ManagedByStaffID = u.StaffID
    ${whereSql}
    ORDER BY b.BranchName, c.CenterName
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  const statsResult = await pool.request().query(`
    SELECT
      (SELECT COUNT(*) FROM Center) AS totalCenters,
      (SELECT COUNT(*) FROM Center WHERE IsActive = 1) AS activeCenters,
      (SELECT COUNT(DISTINCT BranchID) FROM Center) AS branchesCovered,
      (SELECT COUNT(*) FROM Member WHERE CenterID IS NOT NULL) AS totalMembersInCenters
  `);

  res.json({
    success: true,
    data: { items: listResult.recordset, total, page, perPage, stats: statsResult.recordset[0] },
  });
});

/** GET /api/centers/:id -- single record for the edit form. */
router.get('/:id', async (req, res) => {
  const pool = await getPool();
  const result = await pool.request().input('id', sql.Int, Number(req.params.id)).query(`
    SELECT CenterID AS centerId, CenterCode AS centerCode, CenterName AS centerName, BranchID AS branchId,
           Address AS address, MeetingDay AS meetingDay, MeetingTime AS meetingTime, ManagedByStaffID AS managedByStaffId,
           IsActive AS isActive, DissolvedDate AS dissolvedDate, ContactPerson AS contactPerson, ContactNo AS contactNo,
           CenterPlace AS centerPlace, Latitude AS latitude, Longitude AS longitude
    FROM Center WHERE CenterID = @id
  `);
  const center = result.recordset[0];
  if (!center) return res.status(404).json({ success: false, message: 'Center not found' });
  res.json({ success: true, data: center });
});

interface CenterPayload {
  centerCode: string;
  centerName: string;
  branchId: number;
  address?: string;
  meetingDay?: number | null;
  meetingTime?: string;
  managedByStaffId?: number | null;
  isActive: boolean;
  dissolvedDate?: string | null;
  contactPerson?: string;
  contactNo?: string;
  centerPlace?: string;
  latitude?: string;
  longitude?: string;
}

/** PUT /api/centers/:id -- update (same fields centers.php's inline edit form saves). */
router.put('/:id', async (req, res) => {
  const centerId = Number(req.params.id);
  const body = req.body as CenterPayload;
  if (!body.centerCode?.trim()) return res.status(422).json({ success: false, message: 'Center Code is required' });
  if (!body.centerName?.trim()) return res.status(422).json({ success: false, message: 'Center Name is required' });
  if (!body.branchId) return res.status(422).json({ success: false, message: 'Branch is required' });

  const pool = await getPool();
  try {
    const result = await pool
      .request()
      .input('id', sql.Int, centerId)
      .input('centerCode', sql.NVarChar, body.centerCode.trim())
      .input('centerName', sql.NVarChar, body.centerName.trim())
      .input('branchId', sql.Int, body.branchId)
      .input('address', sql.NVarChar, body.address?.trim() || null)
      .input('meetingDay', sql.Int, body.meetingDay || null)
      .input('meetingTime', sql.NVarChar, body.meetingTime?.trim() || null)
      .input('managedByStaffId', sql.Int, body.managedByStaffId || null)
      .input('isActive', sql.Bit, body.isActive ? 1 : 0)
      .input('dissolvedDate', sql.Date, body.dissolvedDate || null)
      .input('contactPerson', sql.NVarChar, body.contactPerson?.trim() || null)
      .input('contactNo', sql.NVarChar, body.contactNo?.trim() || null)
      .input('centerPlace', sql.NVarChar, body.centerPlace?.trim() || null)
      .input('latitude', sql.NVarChar, body.latitude?.trim() || null)
      .input('longitude', sql.NVarChar, body.longitude?.trim() || null).query(`
        UPDATE Center SET
          CenterCode = @centerCode, CenterName = @centerName, BranchID = @branchId, Address = @address,
          MeetingDay = @meetingDay, MeetingTime = @meetingTime, ManagedByStaffID = @managedByStaffId,
          IsActive = @isActive, DissolvedDate = @dissolvedDate, ContactPerson = @contactPerson,
          ContactNo = @contactNo, CenterPlace = @centerPlace, Latitude = @latitude, Longitude = @longitude
        WHERE CenterID = @id
      `);
    if (result.rowsAffected[0] === 0) {
      return res.status(404).json({ success: false, message: 'Center not found' });
    }
    res.json({ success: true, data: { centerId } });
  } catch (err) {
    console.error(err);
    res.status(409).json({ success: false, message: 'Could not update center -- the center code may already be in use' });
  }
});

/** GET /api/centers/:id/history -- recent meeting-shift and transfer history, newest first. */
router.get('/:id/history', async (req, res) => {
  const centerId = Number(req.params.id);
  const pool = await getPool();
  const [shifts, transfers] = await Promise.all([
    pool.request().input('id', sql.Int, centerId).query(`
      SELECT h.ShiftID AS shiftId, h.OldMeetingDay AS oldMeetingDay, h.NewMeetingDay AS newMeetingDay,
             h.OldMeetingTime AS oldMeetingTime, h.NewMeetingTime AS newMeetingTime,
             h.SchedulesShiftedCount AS schedulesShiftedCount, h.LoansAffectedCount AS loansAffectedCount,
             h.Remark AS remark, h.ShiftedAt AS shiftedAt, s.FullName AS shiftedByName
      FROM Center_Meeting_Shift_History h
      LEFT JOIN Staff s ON h.ShiftedByStaffID = s.StaffID
      WHERE h.CenterID = @id ORDER BY h.ShiftedAt DESC
    `),
    pool.request().input('id', sql.Int, centerId).query(`
      SELECT t.TransferID AS transferId, os.FullName AS oldStaffName, ns.FullName AS newStaffName,
             t.MembersTransferredCount AS membersTransferredCount, t.LoansTransferredCount AS loansTransferredCount,
             t.Remark AS remark, t.TransferredAt AS transferredAt, ts.FullName AS transferredByName
      FROM Center_Transfer_History t
      LEFT JOIN Staff os ON t.OldStaffID = os.StaffID
      LEFT JOIN Staff ns ON t.NewStaffID = ns.StaffID
      LEFT JOIN Staff ts ON t.TransferredByStaffID = ts.StaffID
      WHERE t.CenterID = @id ORDER BY t.TransferredAt DESC
    `),
  ]);
  res.json({ success: true, data: { shifts: shifts.recordset, transfers: transfers.recordset } });
});

/**
 * POST /api/centers/:id/shift-meeting
 * Body: { newDay (1-7), newTime?, remark? }
 * Same realignment centers.php's shift-meeting handler does: moves the
 * center's own MeetingDay/MeetingTime, then (if the old day was a known
 * weekday and is actually changing) rolls every still-open loan's upcoming
 * due dates in this center onto the new weekday, logs the change, and
 * notifies the center manager + affected loan officers.
 */
router.post('/:id/shift-meeting', async (req, res) => {
  if (!(await roleHasPermission(req.staff!, 'shift_center_meeting'))) {
    return res.status(403).json({ success: false, message: "You do not have permission to shift a center's meeting" });
  }

  const centerId = Number(req.params.id);
  const newDay = Number(req.body.newDay);
  const newTime = (req.body.newTime as string | undefined)?.trim() || null;
  const remark = (req.body.remark as string | undefined)?.trim() || null;
  if (!newDay || newDay < 1 || newDay > 7) {
    return res.status(422).json({ success: false, message: 'Please select a valid meeting day' });
  }

  const pool = await getPool();
  const centerResult = await pool.request().input('id', sql.Int, centerId).query(`
    SELECT CenterID, CenterName, MeetingDay, MeetingTime, ManagedByStaffID FROM Center WHERE CenterID = @id
  `);
  const center = centerResult.recordset[0];
  if (!center) return res.status(404).json({ success: false, message: 'Center not found' });

  const oldDay: string | null = center.MeetingDay;
  const oldDayNum = oldDay !== null && /^\d+$/.test(oldDay) && Number(oldDay) >= 1 && Number(oldDay) <= 7 ? Number(oldDay) : null;

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    await new sql.Request(transaction)
      .input('day', sql.Int, newDay)
      .input('time', sql.NVarChar, newTime)
      .input('id', sql.Int, centerId)
      .query('UPDATE Center SET MeetingDay = @day, MeetingTime = @time WHERE CenterID = @id');

    let shiftedCount = 0;
    const affectedOfficerIds = new Set<number>();

    if (oldDayNum !== null && oldDayNum !== newDay) {
      const loansResult = await new sql.Request(transaction).input('centerId', sql.Int, centerId).query(`
        SELECT l.LoanID, l.LoanOfficerStaffID FROM Loan l
        JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
        WHERE l.CenterID = @centerId AND stg.IsFinal = 0
      `);
      for (const loan of loansResult.recordset) {
        const scheduleResult = await new sql.Request(transaction).input('loanId', sql.Int, loan.LoanID).query(`
          SELECT ScheduleID, DueDate FROM Repayment_Schedule
          WHERE LoanID = @loanId AND IsPaid = 0 AND DueDate >= CAST(GETDATE() AS DATE)
        `);
        if (scheduleResult.recordset.length === 0) continue;
        for (const row of scheduleResult.recordset) {
          const newDue = shiftDueDateToWeekday(new Date(row.DueDate), newDay);
          const oldDueIso = new Date(row.DueDate).toISOString().slice(0, 10);
          if (newDue !== oldDueIso) {
            await new sql.Request(transaction)
              .input('newDue', sql.Date, newDue)
              .input('scheduleId', sql.Int, row.ScheduleID)
              .query('UPDATE Repayment_Schedule SET DueDate = @newDue WHERE ScheduleID = @scheduleId');
            await new sql.Request(transaction)
              .input('newDue', sql.Date, newDue)
              .input('scheduleId', sql.Int, row.ScheduleID)
              .query(`
                UPDATE Daily_Collection_Sheet SET CollectionDueDate = @newDue, UpdatedAt = GETDATE()
                WHERE ScheduleID = @scheduleId AND CollectionStatus IS NULL
              `);
            shiftedCount++;
          }
        }
        if (loan.LoanOfficerStaffID) affectedOfficerIds.add(loan.LoanOfficerStaffID);
      }
    }

    await new sql.Request(transaction)
      .input('centerId', sql.Int, centerId)
      .input('oldDay', sql.NVarChar, oldDay)
      .input('newDay', sql.Int, newDay)
      .input('oldTime', sql.NVarChar, center.MeetingTime)
      .input('newTime', sql.NVarChar, newTime)
      .input('shiftedCount', sql.Int, shiftedCount)
      .input('loansAffected', sql.Int, affectedOfficerIds.size)
      .input('remark', sql.NVarChar, remark)
      .input('shiftedBy', sql.Int, req.staff!.staffId).query(`
        INSERT INTO Center_Meeting_Shift_History
          (CenterID, OldMeetingDay, NewMeetingDay, OldMeetingTime, NewMeetingTime, SchedulesShiftedCount, LoansAffectedCount, Remark, ShiftedByStaffID)
        VALUES (@centerId, @oldDay, @newDay, @oldTime, @newTime, @shiftedCount, @loansAffected, @remark, @shiftedBy)
      `);

    await transaction.commit();

    const notifyIds = new Set<number>(affectedOfficerIds);
    if (center.ManagedByStaffID) notifyIds.add(center.ManagedByStaffID);
    notifyIds.delete(req.staff!.staffId);
    const msg = `Meeting for ${center.CenterName} has moved to ${DAY_NAMES[newDay]}${newTime ? ' at ' + newTime : ''}. ${shiftedCount} upcoming repayment date(s) updated accordingly.`;
    for (const sid of notifyIds) {
      await notifyStaff(pool, sid, 'Center Meeting Shifted', msg, 1, 'Center', centerId);
    }

    res.json({ success: true, data: { shiftedCount } });
  } catch (err) {
    await transaction.rollback();
    console.error(err);
    res.status(500).json({ success: false, message: 'Could not shift the meeting. Please try again.' });
  }
});

/**
 * POST /api/centers/:id/transfer
 * Body: { newStaffId, remark? }
 * Same reassignment centers.php's transfer handler does: moves the
 * center's manager, every member in it, every still-open loan's officer,
 * and today's open Daily_Collection_Sheet rows to the new (same-branch)
 * staff member, logs it, and notifies the old + new staff.
 */
router.post('/:id/transfer', async (req, res) => {
  if (!(await roleHasPermission(req.staff!, 'transfer_center'))) {
    return res.status(403).json({ success: false, message: 'You do not have permission to transfer a center' });
  }

  const centerId = Number(req.params.id);
  const newStaffId = Number(req.body.newStaffId);
  const remark = (req.body.remark as string | undefined)?.trim() || null;
  if (!newStaffId) return res.status(422).json({ success: false, message: 'Please select an employee' });

  const pool = await getPool();
  const centerResult = await pool.request().input('id', sql.Int, centerId).query(`
    SELECT CenterID, CenterName, BranchID, ManagedByStaffID FROM Center WHERE CenterID = @id
  `);
  const center = centerResult.recordset[0];
  if (!center) return res.status(404).json({ success: false, message: 'Center not found' });

  const newStaffResult = await pool.request().input('id', sql.Int, newStaffId).query(`
    SELECT StaffID, FullName, BranchID FROM Staff WHERE StaffID = @id AND IsActive = 1
  `);
  const newStaff = newStaffResult.recordset[0];
  if (!newStaff || newStaff.BranchID !== center.BranchID) {
    return res.status(422).json({ success: false, message: 'Please select an active employee from the same branch as this center' });
  }
  if (center.ManagedByStaffID === newStaffId) {
    return res.status(422).json({ success: false, message: 'This center is already managed by that employee' });
  }

  let oldStaffName = 'Unassigned';
  if (center.ManagedByStaffID) {
    const oldStaffResult = await pool.request().input('id', sql.Int, center.ManagedByStaffID).query('SELECT FullName FROM Staff WHERE StaffID = @id');
    oldStaffName = oldStaffResult.recordset[0]?.FullName ?? 'Unassigned';
  }

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    await new sql.Request(transaction)
      .input('staffId', sql.Int, newStaffId)
      .input('id', sql.Int, centerId)
      .query('UPDATE Center SET ManagedByStaffID = @staffId WHERE CenterID = @id');

    const memberResult = await new sql.Request(transaction)
      .input('staffId', sql.Int, newStaffId)
      .input('centerId', sql.Int, centerId)
      .query('UPDATE Member SET AssignedToStaffID = @staffId WHERE CenterID = @centerId');
    const membersMoved = memberResult.rowsAffected[0];

    const loanResult = await new sql.Request(transaction)
      .input('staffId', sql.Int, newStaffId)
      .input('centerId', sql.Int, centerId).query(`
        UPDATE l SET l.LoanOfficerStaffID = @staffId
        FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
        WHERE l.CenterID = @centerId AND stg.IsFinal = 0
      `);
    const loansMoved = loanResult.rowsAffected[0];

    await new sql.Request(transaction)
      .input('staffId', sql.Int, newStaffId)
      .input('centerId', sql.Int, centerId).query(`
        UPDATE Daily_Collection_Sheet SET OfficerStaffID = @staffId, UpdatedAt = GETDATE()
        WHERE CenterID = @centerId AND CollectionStatus IS NULL
      `);

    await new sql.Request(transaction)
      .input('centerId', sql.Int, centerId)
      .input('oldStaffId', sql.Int, center.ManagedByStaffID)
      .input('newStaffId', sql.Int, newStaffId)
      .input('membersMoved', sql.Int, membersMoved)
      .input('loansMoved', sql.Int, loansMoved)
      .input('remark', sql.NVarChar, remark)
      .input('transferredBy', sql.Int, req.staff!.staffId).query(`
        INSERT INTO Center_Transfer_History (CenterID, OldStaffID, NewStaffID, MembersTransferredCount, LoansTransferredCount, Remark, TransferredByStaffID)
        VALUES (@centerId, @oldStaffId, @newStaffId, @membersMoved, @loansMoved, @remark, @transferredBy)
      `);

    await transaction.commit();

    const notifyIds = new Set<number>();
    if (center.ManagedByStaffID) notifyIds.add(center.ManagedByStaffID);
    notifyIds.add(newStaffId);
    notifyIds.delete(req.staff!.staffId);
    const msg = `Center ${center.CenterName} has been transferred from ${oldStaffName} to ${newStaff.FullName}. ${membersMoved} member(s) and ${loansMoved} active loan(s) moved with it.`;
    for (const sid of notifyIds) {
      await notifyStaff(pool, sid, 'Center Transferred', msg, 1, 'Center', centerId);
    }

    res.json({ success: true, data: { membersMoved, loansMoved } });
  } catch (err) {
    await transaction.rollback();
    console.error(err);
    res.status(500).json({ success: false, message: 'Could not transfer the center. Please try again.' });
  }
});

export default router;
