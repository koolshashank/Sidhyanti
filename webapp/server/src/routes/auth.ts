import { Router } from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

/**
 * POST /api/auth/login
 * Validates against the same Staff.PasswordHash the PHP web CRM's
 * index.php checks (standard bcrypt -- password_verify() on the PHP side,
 * bcryptjs.compare() here, same hash format) -- a staff member's existing
 * web password works here unchanged, no separate account to create.
 */
router.post('/login', async (req, res) => {
  const { username, password } = req.body ?? {};
  if (typeof username !== 'string' || typeof password !== 'string' || !username || !password) {
    return res.status(422).json({ success: false, message: 'Username and password are required' });
  }

  const pool = await getPool();
  const result = await pool
    .request()
    .input('username', sql.NVarChar, username)
    .query(`
      SELECT s.StaffID, s.FullName, s.Username, s.PasswordHash, s.RoleID, s.BranchID, s.IsActive,
             r.RoleName, d.DesignationName
      FROM Staff s
      LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
      LEFT JOIN Designation_Master d ON s.DesignationID = d.DesignationID
      WHERE s.Username = @username AND s.IsActive = 1
    `);

  const staff = result.recordset[0];
  const ok = staff && (await bcrypt.compare(password, staff.PasswordHash));

  await pool
    .request()
    .input('staffId', sql.Int, staff?.StaffID ?? null)
    .input('usernameTried', sql.NVarChar, username)
    .input('ip', sql.NVarChar, req.ip ?? '')
    .input('userAgent', sql.NVarChar, req.headers['user-agent'] ?? '')
    .input('status', sql.NVarChar, ok ? 'Success' : 'Failed')
    .query(`
      INSERT INTO Login_Log (StaffID, UsernameTried, IPAddress, UserAgent, Status)
      VALUES (@staffId, @usernameTried, @ip, @userAgent, @status)
    `);

  if (!ok) {
    return res.status(401).json({ success: false, message: 'Invalid username or password' });
  }

  await pool.request().input('staffId', sql.Int, staff.StaffID).query(`
    UPDATE Staff SET LastLoginAt = GETDATE() WHERE StaffID = @staffId
  `);

  const claims = {
    staffId: staff.StaffID,
    roleId: staff.RoleID,
    roleName: staff.RoleName,
    branchId: staff.BranchID,
    fullName: staff.FullName,
  };

  const ttlSeconds = Number(process.env.JWT_ACCESS_TOKEN_TTL_HOURS ?? 8) * 3600;
  const token = jwt.sign(claims, process.env.JWT_SECRET!, { expiresIn: ttlSeconds });

  res.json({
    success: true,
    data: {
      token,
      staff: {
        staffId: staff.StaffID,
        fullName: staff.FullName,
        username: staff.Username,
        roleId: staff.RoleID,
        roleName: staff.RoleName,
        branchId: staff.BranchID,
        designationName: staff.DesignationName,
      },
    },
  });
});

/**
 * GET /api/auth/me
 * Lets the React app confirm a stored token is still valid on page load/
 * refresh, and re-fetch the staff's current profile (role/branch could
 * have changed since the token was issued).
 */
router.get('/me', requireAuth, async (req, res) => {
  const pool = await getPool();
  const result = await pool.request().input('staffId', sql.Int, req.staff!.staffId).query(`
    SELECT s.StaffID, s.FullName, s.Username, s.RoleID, s.BranchID, r.RoleName, d.DesignationName
    FROM Staff s
    LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
    LEFT JOIN Designation_Master d ON s.DesignationID = d.DesignationID
    WHERE s.StaffID = @staffId AND s.IsActive = 1
  `);
  const staff = result.recordset[0];
  if (!staff) {
    return res.status(401).json({ success: false, message: 'Unauthorized' });
  }
  res.json({
    success: true,
    data: {
      staffId: staff.StaffID,
      fullName: staff.FullName,
      username: staff.Username,
      roleId: staff.RoleID,
      roleName: staff.RoleName,
      branchId: staff.BranchID,
      designationName: staff.DesignationName,
    },
  });
});

export default router;
