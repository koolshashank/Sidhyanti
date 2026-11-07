import { Router } from 'express';
import bcrypt from 'bcryptjs';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/**
 * GET /api/staff/lookups
 * Dropdown data for the Add/Edit Employee form.
 */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const [roles, designations, branches, managers] = await Promise.all([
    pool.request().query(`SELECT RoleID AS id, RoleName AS name FROM Role_Master ORDER BY RoleName`),
    pool.request().query(`
      SELECT DesignationID AS id, DesignationName AS name, RoleID AS roleId
      FROM Designation_Master WHERE DesignationName IS NOT NULL AND DesignationName <> '' ORDER BY DesignationName
    `),
    pool.request().query(`SELECT BranchID AS id, BranchName AS name FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`),
    pool.request().query(`SELECT StaffID AS id, FullName AS name FROM Staff WHERE IsActive = 1 ORDER BY FullName`),
  ]);
  res.json({
    success: true,
    data: {
      roles: roles.recordset,
      designations: designations.recordset,
      branches: branches.recordset,
      managers: managers.recordset,
    },
  });
});

/**
 * GET /api/staff
 * Query: page, perPage, branch, role, status (active|inactive), q
 */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(100, Math.max(1, Number(req.query.perPage) || 12));
  const branch = req.query.branch ? Number(req.query.branch) : null;
  const role = req.query.role ? Number(req.query.role) : null;
  const status = req.query.status as string | undefined;
  const q = (req.query.q as string | undefined)?.trim();

  const conditions: string[] = [];
  const pool = await getPool();

  if (branch) conditions.push('s.BranchID = @branch');
  if (role) conditions.push('s.RoleID = @role');
  if (status === 'active') conditions.push('s.IsActive = 1');
  else if (status === 'inactive') conditions.push('s.IsActive = 0');
  if (q) conditions.push('(s.FullName LIKE @q OR s.EmployeeCode LIKE @q OR s.Phone LIKE @q)');
  const whereSql = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';

  // Every request object below is bound with the same filter params it needs
  // (count query, list query) -- a mssql Request can't be re-executed, so
  // each gets its own instance rather than sharing one.
  const countReq = pool.request();
  if (branch) countReq.input('branch', sql.Int, branch);
  if (role) countReq.input('role', sql.Int, role);
  if (q) countReq.input('q', sql.NVarChar, `%${q}%`);
  const total = (await countReq.query(`SELECT COUNT(*) AS total FROM Staff s ${whereSql}`)).recordset[0].total;

  const listReq = pool.request();
  if (branch) listReq.input('branch', sql.Int, branch);
  if (role) listReq.input('role', sql.Int, role);
  if (q) listReq.input('q', sql.NVarChar, `%${q}%`);
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);

  const listResult = await listReq.query(`
    SELECT s.StaffID AS staffId, s.EmployeeCode AS employeeCode, s.FullName AS fullName, s.Username AS username,
           s.Email AS email, s.Phone AS phone, s.IsActive AS isActive, s.RoleID AS roleId,
           r.RoleName AS roleName, d.DesignationName AS designationName, s.BranchID AS branchId, b.BranchName AS branchName,
           (SELECT COUNT(*) FROM Member m WHERE m.AssignedToStaffID = s.StaffID) AS leadCount,
           (SELECT COUNT(*) FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
              WHERE l.LoanOfficerStaffID = s.StaffID AND stg.StageName = 'Active') AS activeLoans
    FROM Staff s
    LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
    LEFT JOIN Designation_Master d ON s.DesignationID = d.DesignationID
    LEFT JOIN Branch b ON s.BranchID = b.BranchID
    ${whereSql}
    ORDER BY b.BranchName, r.RoleID, s.FullName
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  const statsResult = await pool.request().query(`
    SELECT
      (SELECT COUNT(*) FROM Staff) AS totalStaff,
      (SELECT COUNT(*) FROM Staff WHERE IsActive = 1) AS activeStaff,
      (SELECT COUNT(DISTINCT BranchID) FROM Staff WHERE BranchID IS NOT NULL) AS branchesCovered,
      (SELECT COUNT(*) FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
         WHERE stg.StageName = 'Active' AND l.LoanOfficerStaffID IS NOT NULL) AS activeLoansManaged
  `);

  res.json({
    success: true,
    data: {
      items: listResult.recordset,
      total,
      page,
      perPage,
      stats: statsResult.recordset[0],
    },
  });
});

/** GET /api/staff/:id -- full record for prefilling the edit form. */
router.get('/:id', async (req, res) => {
  const pool = await getPool();
  const result = await pool.request().input('id', sql.Int, Number(req.params.id)).query(`
    SELECT StaffID AS staffId, EmployeeCode AS employeeCode, FullName AS fullName, Username AS username,
           Email AS email, Phone AS phone, RoleID AS roleId, DesignationID AS designationId, BranchID AS branchId,
           ReportingManagerID AS reportingManagerId, DateOfJoining AS dateOfJoining, IsActive AS isActive,
           Address AS address, City AS city, Pincode AS pincode, District AS district, State AS state
    FROM Staff WHERE StaffID = @id
  `);
  const staff = result.recordset[0];
  if (!staff) return res.status(404).json({ success: false, message: 'Employee not found' });
  res.json({ success: true, data: staff });
});

interface StaffPayload {
  fullName: string;
  username: string;
  password?: string;
  email?: string;
  phone?: string;
  roleId: number;
  designationId?: number | null;
  branchId?: number | null;
  reportingManagerId?: number | null;
  dateOfJoining?: string | null;
  isActive: boolean;
  address?: string;
  city?: string;
  pincode?: string;
  district?: string;
  state?: string;
}

function validatePayload(body: Partial<StaffPayload>): string | null {
  if (!body.fullName?.trim()) return 'Full Name is required';
  if (!body.username?.trim()) return 'Username is required';
  if (!body.roleId) return 'Role is required';
  return null;
}

/** POST /api/staff -- create, same EmployeeCode=EMP<id> convention as staff_add.php. */
router.post('/', async (req, res) => {
  const body = req.body as StaffPayload;
  const error = validatePayload(body);
  if (error) return res.status(422).json({ success: false, message: error });
  if (!body.password) return res.status(422).json({ success: false, message: 'Password is required' });

  const pool = await getPool();
  const passwordHash = await bcrypt.hash(body.password, 10);
  const tempCode = `TMP-${Date.now()}`;

  try {
    const result = await pool
      .request()
      .input('employeeCode', sql.NVarChar, tempCode)
      .input('fullName', sql.NVarChar, body.fullName.trim())
      .input('username', sql.NVarChar, body.username.trim())
      .input('passwordHash', sql.NVarChar, passwordHash)
      .input('email', sql.NVarChar, body.email?.trim() || null)
      .input('phone', sql.NVarChar, body.phone?.trim() || null)
      .input('roleId', sql.Int, body.roleId)
      .input('designationId', sql.Int, body.designationId || null)
      .input('branchId', sql.Int, body.branchId || null)
      .input('reportingManagerId', sql.Int, body.reportingManagerId || null)
      .input('dateOfJoining', sql.Date, body.dateOfJoining || null)
      .input('isActive', sql.Bit, body.isActive ? 1 : 0)
      .input('address', sql.NVarChar, body.address?.trim() || null)
      .input('city', sql.NVarChar, body.city?.trim() || null)
      .input('pincode', sql.NVarChar, body.pincode?.trim() || null)
      .input('district', sql.NVarChar, body.district?.trim() || null)
      .input('state', sql.NVarChar, body.state?.trim() || null)
      .input('createdBy', sql.Int, req.staff!.staffId).query(`
        DECLARE @Inserted TABLE (StaffID INT);
        INSERT INTO Staff
          (EmployeeCode, FullName, Username, PasswordHash, Email, Phone, RoleID, DesignationID,
           BranchID, ReportingManagerID, DateOfJoining, IsActive, Address, City, Pincode, District, State, CreatedBy)
          OUTPUT INSERTED.StaffID INTO @Inserted
          VALUES (@employeeCode, @fullName, @username, @passwordHash, @email, @phone, @roleId, @designationId,
                  @branchId, @reportingManagerId, @dateOfJoining, @isActive, @address, @city, @pincode, @district, @state, @createdBy);
        SELECT StaffID FROM @Inserted;
      `);

    const newId = result.recordset[0].StaffID;
    await pool.request().input('code', sql.NVarChar, `EMP${newId}`).input('id', sql.Int, newId).query(`
      UPDATE Staff SET EmployeeCode = @code WHERE StaffID = @id
    `);

    res.status(201).json({ success: true, data: { staffId: newId } });
  } catch (err) {
    console.error(err);
    res.status(409).json({ success: false, message: 'Could not create employee -- the username may already be in use' });
  }
});

/** PUT /api/staff/:id -- update; password only changes when a new one is provided. */
router.put('/:id', async (req, res) => {
  const staffId = Number(req.params.id);
  const body = req.body as StaffPayload;
  const error = validatePayload(body);
  if (error) return res.status(422).json({ success: false, message: error });

  const pool = await getPool();
  const request = pool
    .request()
    .input('id', sql.Int, staffId)
    .input('fullName', sql.NVarChar, body.fullName.trim())
    .input('username', sql.NVarChar, body.username.trim())
    .input('email', sql.NVarChar, body.email?.trim() || null)
    .input('phone', sql.NVarChar, body.phone?.trim() || null)
    .input('roleId', sql.Int, body.roleId)
    .input('designationId', sql.Int, body.designationId || null)
    .input('branchId', sql.Int, body.branchId || null)
    .input('reportingManagerId', sql.Int, body.reportingManagerId || null)
    .input('dateOfJoining', sql.Date, body.dateOfJoining || null)
    .input('isActive', sql.Bit, body.isActive ? 1 : 0)
    .input('address', sql.NVarChar, body.address?.trim() || null)
    .input('city', sql.NVarChar, body.city?.trim() || null)
    .input('pincode', sql.NVarChar, body.pincode?.trim() || null)
    .input('district', sql.NVarChar, body.district?.trim() || null)
    .input('state', sql.NVarChar, body.state?.trim() || null);

  let passwordClause = '';
  if (body.password) {
    request.input('passwordHash', sql.NVarChar, await bcrypt.hash(body.password, 10));
    passwordClause = ', PasswordHash = @passwordHash';
  }

  try {
    const result = await request.query(`
      UPDATE Staff SET
        FullName = @fullName, Username = @username, Email = @email, Phone = @phone, RoleID = @roleId,
        DesignationID = @designationId, BranchID = @branchId, ReportingManagerID = @reportingManagerId,
        DateOfJoining = @dateOfJoining, IsActive = @isActive, Address = @address, City = @city,
        Pincode = @pincode, District = @district, State = @state
        ${passwordClause}
      WHERE StaffID = @id
    `);
    if (result.rowsAffected[0] === 0) {
      return res.status(404).json({ success: false, message: 'Employee not found' });
    }
    res.json({ success: true, data: { staffId } });
  } catch (err) {
    console.error(err);
    res.status(409).json({ success: false, message: 'Could not update employee -- the username may already be in use' });
  }
});

export default router;
