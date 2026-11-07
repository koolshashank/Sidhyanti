import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/** GET /api/branches/lookups -- states + districts for the Add/Edit form. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const [states, districts] = await Promise.all([
    pool.request().query(`SELECT StateID AS id, StateName AS name FROM State_Master ORDER BY StateName`),
    pool.request().query(`SELECT DistrictID AS id, DistrictName AS name, StateID AS stateId FROM District_Master ORDER BY DistrictName`),
  ]);
  res.json({ success: true, data: { states: states.recordset, districts: districts.recordset } });
});

/**
 * GET /api/branches
 * Every branch's portfolio summary, same vw_Branch_Portfolio_Summary view
 * the Dashboard uses, so the numbers always agree.
 */
router.get('/', async (_req, res) => {
  const pool = await getPool();
  const result = await pool.request().query(`
    SELECT b.BranchID AS branchId, b.BranchCode AS branchCode, b.BranchName AS branchName, b.Address AS address,
           b.DistrictID AS districtId, d.DistrictName AS districtName, b.StateID AS stateId, st.StateName AS stateName,
           b.Pincode AS pincode, b.Phone AS phone, b.Email AS email, b.DateOpened AS dateOpened, b.IsActive AS isActive,
           s.TotalMembers AS totalMembers, s.ActiveCenters AS activeCenters, s.ActiveLoans AS activeLoans,
           s.OutstandingPortfolio AS outstandingPortfolio, s.LifetimeDisbursed AS lifetimeDisbursed
    FROM Branch b
    JOIN vw_Branch_Portfolio_Summary s ON s.BranchID = b.BranchID
    LEFT JOIN District_Master d ON b.DistrictID = d.DistrictID
    LEFT JOIN State_Master st ON b.StateID = st.StateID
    WHERE b.BranchName NOT LIKE '%HEAD OFFICE%'
    ORDER BY b.BranchName
  `);
  res.json({ success: true, data: { items: result.recordset } });
});

/** GET /api/branches/:id -- single record, for prefilling the edit form. */
router.get('/:id', async (req, res) => {
  const pool = await getPool();
  const result = await pool.request().input('id', sql.Int, Number(req.params.id)).query(`
    SELECT BranchID AS branchId, BranchCode AS branchCode, BranchName AS branchName, Address AS address,
           DistrictID AS districtId, StateID AS stateId, Pincode AS pincode, Phone AS phone, Email AS email,
           DateOpened AS dateOpened, IsActive AS isActive
    FROM Branch WHERE BranchID = @id
  `);
  const branch = result.recordset[0];
  if (!branch) return res.status(404).json({ success: false, message: 'Branch not found' });
  res.json({ success: true, data: branch });
});

interface BranchPayload {
  branchCode: string;
  branchName: string;
  address?: string;
  districtId?: number | null;
  stateId?: number | null;
  pincode?: string;
  phone?: string;
  email?: string;
  dateOpened?: string | null;
  isActive: boolean;
}

function validate(body: Partial<BranchPayload>): string | null {
  if (!body.branchCode?.trim()) return 'Branch Code is required';
  if (!body.branchName?.trim()) return 'Branch Name is required';
  return null;
}

/** POST /api/branches -- create. */
router.post('/', async (req, res) => {
  const body = req.body as BranchPayload;
  const error = validate(body);
  if (error) return res.status(422).json({ success: false, message: error });

  const pool = await getPool();
  try {
    const result = await pool
      .request()
      .input('branchCode', sql.NVarChar, body.branchCode.trim())
      .input('branchName', sql.NVarChar, body.branchName.trim())
      .input('address', sql.NVarChar, body.address?.trim() || null)
      .input('districtId', sql.Int, body.districtId || null)
      .input('stateId', sql.Int, body.stateId || null)
      .input('pincode', sql.NVarChar, body.pincode?.trim() || null)
      .input('phone', sql.NVarChar, body.phone?.trim() || null)
      .input('email', sql.NVarChar, body.email?.trim() || null)
      .input('dateOpened', sql.Date, body.dateOpened || new Date())
      .input('isActive', sql.Bit, body.isActive ? 1 : 0)
      .input('createdBy', sql.Int, req.staff!.staffId).query(`
        DECLARE @Inserted TABLE (BranchID INT);
        INSERT INTO Branch (BranchCode, BranchName, Address, DistrictID, StateID, Pincode, Phone, Email, DateOpened, IsActive, CreatedAt, CreatedBy)
          OUTPUT INSERTED.BranchID INTO @Inserted
          VALUES (@branchCode, @branchName, @address, @districtId, @stateId, @pincode, @phone, @email, @dateOpened, @isActive, GETDATE(), @createdBy);
        SELECT BranchID FROM @Inserted;
      `);
    res.status(201).json({ success: true, data: { branchId: result.recordset[0].BranchID } });
  } catch (err) {
    console.error(err);
    res.status(409).json({ success: false, message: 'Could not create branch -- the branch code may already be in use' });
  }
});

/** PUT /api/branches/:id -- update. */
router.put('/:id', async (req, res) => {
  const branchId = Number(req.params.id);
  const body = req.body as BranchPayload;
  const error = validate(body);
  if (error) return res.status(422).json({ success: false, message: error });

  const pool = await getPool();
  try {
    const result = await pool
      .request()
      .input('id', sql.Int, branchId)
      .input('branchCode', sql.NVarChar, body.branchCode.trim())
      .input('branchName', sql.NVarChar, body.branchName.trim())
      .input('address', sql.NVarChar, body.address?.trim() || null)
      .input('districtId', sql.Int, body.districtId || null)
      .input('stateId', sql.Int, body.stateId || null)
      .input('pincode', sql.NVarChar, body.pincode?.trim() || null)
      .input('phone', sql.NVarChar, body.phone?.trim() || null)
      .input('email', sql.NVarChar, body.email?.trim() || null)
      .input('dateOpened', sql.Date, body.dateOpened || null)
      .input('isActive', sql.Bit, body.isActive ? 1 : 0)
      .input('updatedBy', sql.Int, req.staff!.staffId).query(`
        UPDATE Branch SET
          BranchCode = @branchCode, BranchName = @branchName, Address = @address, DistrictID = @districtId,
          StateID = @stateId, Pincode = @pincode, Phone = @phone, Email = @email, DateOpened = @dateOpened,
          IsActive = @isActive, UpdatedAt = GETDATE(), UpdatedBy = @updatedBy
        WHERE BranchID = @id
      `);
    if (result.rowsAffected[0] === 0) {
      return res.status(404).json({ success: false, message: 'Branch not found' });
    }
    res.json({ success: true, data: { branchId } });
  } catch (err) {
    console.error(err);
    res.status(409).json({ success: false, message: 'Could not update branch -- the branch code may already be in use' });
  }
});

export default router;
