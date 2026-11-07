import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAdmin, requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth, requireAdmin);

/** Mirrors includes/permission_helper.php::PERMISSION_DEFS -- not DB-driven in the PHP app either. */
const PERMISSION_DEFS: { key: string; label: string }[] = [
  { key: 'change_loan_scheme', label: 'Change loan scheme before disbursement' },
  { key: 'shift_center_meeting', label: "Shift a center's meeting day (moves upcoming repayment dates)" },
  { key: 'transfer_center', label: 'Transfer a center to another employee (moves its members and active loans)' },
  { key: 'transfer_member_center', label: 'Transfer a client to another center in the same branch' },
  { key: 'manage_center_leave_coverage', label: "Temporarily cover an employee's centers while they are on leave, and revert it later" },
  { key: 'manage_reloan_eligibility_rules', label: 'Define the reloan amount-increase rules used by the mobile app' },
  { key: 'conduct_grt', label: 'Conduct GRT (Group Recognition Test) for a loan, from the mobile app' },
  { key: 'approve_loan_proposal', label: 'Approve or reject a submitted loan proposal' },
  { key: 'approve_loan_preclose', label: 'Approve or reject a loan preclose request' },
  { key: 'approve_partial_payment', label: 'Approve or reject a partial (short) EMI payment' },
  { key: 'approve_leave_request', label: "Approve or reject a staff member's leave request" },
  { key: 'approve_new_center', label: 'Approve or reject a newly created center' },
  { key: 'disburse_loan', label: 'Disburse an approved loan' },
  { key: 'request_loan_preclose', label: 'Request preclosure of a loan' },
  { key: 'conduct_tvr', label: 'Conduct TVR (Telephonic Verification) for a loan, from the mobile app' },
];

/** Mirrors includes/mobile_menu_helper.php::MOBILE_MENU_DEFS. */
const MOBILE_MENU_DEFS: { key: string; label: string }[] = [
  { key: 'create_center', label: 'Create New Center' },
  { key: 'client_sourcing', label: 'Client Sourcing (new lead capture)' },
  { key: 'flo_collection', label: 'FLO Collection' },
  { key: 'client_details', label: 'Client Details (member list)' },
  { key: 'dormant_clients', label: 'Dormant Client (closed loan, no current loan)' },
  { key: 'reloan', label: 'Reloan (worklist of reloan-eligible clients)' },
  { key: 'bm_approval', label: 'BM Approval (proposals, preclose, leave, partial payments)' },
  { key: 'disbursement', label: 'Disbursement' },
  { key: 'dcr', label: 'DCR (Daily Collection Report)' },
  { key: 'center_approval', label: 'Center Approval' },
  { key: 'am_loan_approval', label: 'Loan Approval (Area Manager, above the BM limit)' },
  { key: 'am_center_transfer', label: 'Center Transfer' },
  { key: 'am_tvr', label: 'TVR (Telephonic Verification)' },
  { key: 'operational_reports', label: 'Reports (collection, disbursement, arrears, defaults)' },
  { key: 'duty_tracking', label: 'Duty Tracking (Start/End Duty, location shared while on duty)' },
  { key: 'staff_tracking', label: 'Staff Tracking (Admin live map + route history)' },
  { key: 'announcements', label: 'Announcements (notices from Admin)' },
  { key: 'leaderboard', label: 'Leaderboard (top collectors, best attendance)' },
  { key: 'todays_meetings', label: "Today's Meetings (geo check-in at scheduled centers)" },
];

/** Mirrors includes/app_settings_helper.php::APP_SETTING_DEFS (bool-typed entries only, same as the PHP page renders). */
const APP_SETTING_DEFS: { key: string; label: string; defaultValue: string }[] = [
  { key: 'show_festival_banner', label: 'Show festival banner', defaultValue: '1' },
];

// ---------------------------------------------------------------------------
// 1. Role Permissions -- role x permission matrix (Role_Master, Role_Permission)
// ---------------------------------------------------------------------------

router.get('/permissions', async (_req, res) => {
  const pool = await getPool();
  const roles = (await pool.request().query(`SELECT RoleID AS roleId, RoleName AS roleName FROM Role_Master ORDER BY RoleName`)).recordset;
  const grants = (await pool.request().query(`SELECT RoleID AS roleId, PermissionKey AS permissionKey, IsAllowed AS isAllowed FROM Role_Permission`))
    .recordset;
  const matrix: Record<number, Record<string, boolean>> = {};
  for (const role of roles) matrix[role.roleId] = {};
  for (const g of grants) {
    if (matrix[g.roleId]) matrix[g.roleId][g.permissionKey] = !!g.isAllowed;
  }
  res.json({ success: true, data: { roles, permissions: PERMISSION_DEFS, matrix } });
});

router.put('/permissions', async (req, res) => {
  const allowed: string[] = Array.isArray(req.body.allowed) ? req.body.allowed : [];
  const allowedSet = new Set(allowed);
  const pool = await getPool();
  const roles = (await pool.request().query(`SELECT RoleID AS roleId FROM Role_Master`)).recordset;

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    for (const role of roles) {
      for (const perm of PERMISSION_DEFS) {
        const isAllowed = allowedSet.has(`${role.roleId}:${perm.key}`);
        const request = new sql.Request(transaction);
        request.input('roleId', sql.Int, role.roleId);
        request.input('key', sql.VarChar, perm.key);
        request.input('isAllowed', sql.Bit, isAllowed);
        request.input('staffId', sql.Int, req.staff!.staffId);
        await request.query(`
          MERGE Role_Permission AS target
          USING (SELECT @roleId AS RoleID, @key AS PermissionKey) AS src
          ON target.RoleID = src.RoleID AND target.PermissionKey = src.PermissionKey
          WHEN MATCHED THEN UPDATE SET IsAllowed = @isAllowed, UpdatedAt = GETDATE(), UpdatedByStaffID = @staffId
          WHEN NOT MATCHED THEN INSERT (RoleID, PermissionKey, IsAllowed, UpdatedAt, UpdatedByStaffID)
            VALUES (@roleId, @key, @isAllowed, GETDATE(), @staffId);
        `);
      }
    }
    await transaction.commit();
  } catch (err) {
    await transaction.rollback();
    throw err;
  }
  res.json({ success: true, data: { saved: true } });
});

// ---------------------------------------------------------------------------
// 2. Designations -- simple CRUD (Designation_Master)
// ---------------------------------------------------------------------------

router.get('/designations/lookups', async (_req, res) => {
  const pool = await getPool();
  const roles = await pool.request().query(`SELECT RoleID AS id, RoleName AS name FROM Role_Master ORDER BY RoleName`);
  res.json({ success: true, data: { roles: roles.recordset } });
});

router.get('/designations', async (_req, res) => {
  const pool = await getPool();
  const result = await pool.request().query(`
    SELECT d.DesignationID AS designationId, d.DesignationName AS designationName, d.RoleID AS roleId, r.RoleName AS roleName,
           (SELECT COUNT(*) FROM Staff s WHERE s.DesignationID = d.DesignationID AND s.IsActive = 1) AS activeStaffCount
    FROM Designation_Master d
    LEFT JOIN Role_Master r ON d.RoleID = r.RoleID
    ORDER BY d.DesignationName
  `);
  res.json({ success: true, data: { items: result.recordset } });
});

router.post('/designations', async (req, res) => {
  const { designationName, roleId } = req.body;
  if (!designationName || !roleId) return res.status(400).json({ success: false, message: 'Designation name and role are required' });
  const pool = await getPool();
  const request = pool.request();
  request.input('name', sql.NVarChar, designationName);
  request.input('roleId', sql.Int, roleId);
  const result = await request.query(`
    INSERT INTO Designation_Master (DesignationName, RoleID) OUTPUT INSERTED.DesignationID AS designationId
    VALUES (@name, @roleId)
  `);
  res.json({ success: true, data: { designationId: result.recordset[0].designationId } });
});

router.put('/designations/:id', async (req, res) => {
  const { designationName, roleId } = req.body;
  if (!designationName || !roleId) return res.status(400).json({ success: false, message: 'Designation name and role are required' });
  const pool = await getPool();
  const request = pool.request();
  request.input('id', sql.Int, Number(req.params.id));
  request.input('name', sql.NVarChar, designationName);
  request.input('roleId', sql.Int, roleId);
  await request.query(`UPDATE Designation_Master SET DesignationName = @name, RoleID = @roleId WHERE DesignationID = @id`);
  res.json({ success: true, data: { designationId: Number(req.params.id) } });
});

router.delete('/designations/:id', async (req, res) => {
  const pool = await getPool();
  const id = Number(req.params.id);
  const inUse = (await pool.request().input('id', sql.Int, id).query(`SELECT COUNT(*) AS total FROM Staff WHERE DesignationID = @id`)).recordset[0]
    .total;
  if (inUse > 0) {
    return res.status(400).json({ success: false, message: `Cannot delete -- ${inUse} staff member(s) still use this designation` });
  }
  await pool.request().input('id', sql.Int, id).query(`DELETE FROM Designation_Master WHERE DesignationID = @id`);
  res.json({ success: true, data: { deleted: true } });
});

// ---------------------------------------------------------------------------
// 3. Mobile Menu Access -- designation x menu-key matrix (Designation_Mobile_Menu)
// ---------------------------------------------------------------------------

router.get('/mobile-menu', async (_req, res) => {
  const pool = await getPool();
  const designations = (
    await pool.request().query(`
      SELECT d.DesignationID AS designationId, d.DesignationName AS designationName, r.RoleName AS roleName
      FROM Designation_Master d
      LEFT JOIN Role_Master r ON d.RoleID = r.RoleID
      ORDER BY r.RoleName, d.DesignationName
    `)
  ).recordset;
  const grants = (await pool.request().query(`SELECT DesignationID AS designationId, MenuKey AS menuKey, IsVisible AS isVisible FROM Designation_Mobile_Menu`))
    .recordset;
  const matrix: Record<number, Record<string, boolean>> = {};
  for (const d of designations) matrix[d.designationId] = {};
  for (const g of grants) {
    if (matrix[g.designationId]) matrix[g.designationId][g.menuKey] = !!g.isVisible;
  }
  res.json({ success: true, data: { designations, menuKeys: MOBILE_MENU_DEFS, matrix } });
});

router.put('/mobile-menu', async (req, res) => {
  const visible: string[] = Array.isArray(req.body.visible) ? req.body.visible : [];
  const visibleSet = new Set(visible);
  const pool = await getPool();
  const designations = (await pool.request().query(`SELECT DesignationID AS designationId FROM Designation_Master`)).recordset;

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    for (const d of designations) {
      for (const menu of MOBILE_MENU_DEFS) {
        const isVisible = visibleSet.has(`${d.designationId}:${menu.key}`);
        const request = new sql.Request(transaction);
        request.input('designationId', sql.Int, d.designationId);
        request.input('key', sql.VarChar, menu.key);
        request.input('isVisible', sql.Bit, isVisible);
        request.input('staffId', sql.Int, req.staff!.staffId);
        await request.query(`
          MERGE Designation_Mobile_Menu AS target
          USING (SELECT @designationId AS DesignationID, @key AS MenuKey) AS src
          ON target.DesignationID = src.DesignationID AND target.MenuKey = src.MenuKey
          WHEN MATCHED THEN UPDATE SET IsVisible = @isVisible, UpdatedAt = GETDATE(), UpdatedByStaffID = @staffId
          WHEN NOT MATCHED THEN INSERT (DesignationID, MenuKey, IsVisible, UpdatedAt, UpdatedByStaffID)
            VALUES (@designationId, @key, @isVisible, GETDATE(), @staffId);
        `);
      }
    }
    await transaction.commit();
  } catch (err) {
    await transaction.rollback();
    throw err;
  }
  res.json({ success: true, data: { saved: true } });
});

// ---------------------------------------------------------------------------
// 4. AM Branch Mapping -- Area Manager staff x branch join table (Staff_Branch_Map)
// ---------------------------------------------------------------------------

router.get('/am-branch-mapping', async (_req, res) => {
  const pool = await getPool();
  const staff = (
    await pool.request().query(`
      SELECT s.StaffID AS staffId, s.FullName AS fullName
      FROM Staff s
      JOIN Role_Master r ON s.RoleID = r.RoleID
      WHERE r.RoleName = 'Area Manager' AND s.IsActive = 1
      ORDER BY s.FullName
    `)
  ).recordset;
  const branches = (
    await pool.request().query(`SELECT BranchID AS branchId, BranchName AS branchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName`)
  ).recordset;
  const mappings = (await pool.request().query(`SELECT StaffID AS staffId, BranchID AS branchId FROM Staff_Branch_Map`)).recordset;
  const matrix: Record<number, Record<number, boolean>> = {};
  for (const s of staff) matrix[s.staffId] = {};
  for (const m of mappings) {
    if (matrix[m.staffId]) matrix[m.staffId][m.branchId] = true;
  }
  res.json({ success: true, data: { staff, branches, matrix } });
});

router.put('/am-branch-mapping', async (req, res) => {
  const mapped: string[] = Array.isArray(req.body.mapped) ? req.body.mapped : [];
  const mappedSet = new Set(mapped);
  const pool = await getPool();
  const staff = (
    await pool.request().query(`
      SELECT s.StaffID AS staffId FROM Staff s JOIN Role_Master r ON s.RoleID = r.RoleID WHERE r.RoleName = 'Area Manager' AND s.IsActive = 1
    `)
  ).recordset;
  const branches = (await pool.request().query(`SELECT BranchID AS branchId FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%'`)).recordset;

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    for (const s of staff) {
      for (const b of branches) {
        const key = `${s.staffId}:${b.branchId}`;
        const shouldBeMapped = mappedSet.has(key);
        const existsReq = new sql.Request(transaction);
        existsReq.input('staffId', sql.Int, s.staffId);
        existsReq.input('branchId', sql.Int, b.branchId);
        const exists = (await existsReq.query(`SELECT COUNT(*) AS total FROM Staff_Branch_Map WHERE StaffID = @staffId AND BranchID = @branchId`))
          .recordset[0].total;
        if (shouldBeMapped && !exists) {
          const insReq = new sql.Request(transaction);
          insReq.input('staffId', sql.Int, s.staffId);
          insReq.input('branchId', sql.Int, b.branchId);
          insReq.input('createdBy', sql.Int, req.staff!.staffId);
          await insReq.query(
            `INSERT INTO Staff_Branch_Map (StaffID, BranchID, CreatedAt, CreatedByStaffID) VALUES (@staffId, @branchId, GETDATE(), @createdBy)`,
          );
        } else if (!shouldBeMapped && exists) {
          const delReq = new sql.Request(transaction);
          delReq.input('staffId', sql.Int, s.staffId);
          delReq.input('branchId', sql.Int, b.branchId);
          await delReq.query(`DELETE FROM Staff_Branch_Map WHERE StaffID = @staffId AND BranchID = @branchId`);
        }
      }
    }
    await transaction.commit();
  } catch (err) {
    await transaction.rollback();
    throw err;
  }
  res.json({ success: true, data: { saved: true } });
});

// ---------------------------------------------------------------------------
// 5. App Settings -- key/value toggle store (App_Setting)
// ---------------------------------------------------------------------------

router.get('/app-settings', async (_req, res) => {
  const pool = await getPool();
  const rows = (await pool.request().query(`SELECT SettingKey AS settingKey, SettingValue AS settingValue FROM App_Setting`)).recordset;
  const valueByKey = new Map(rows.map((r) => [r.settingKey, r.settingValue]));
  const settings = APP_SETTING_DEFS.map((def) => ({
    key: def.key,
    label: def.label,
    value: (valueByKey.get(def.key) ?? def.defaultValue) === '1',
  }));
  res.json({ success: true, data: { settings } });
});

router.put('/app-settings', async (req, res) => {
  const values: Record<string, boolean> = req.body.values ?? {};
  const pool = await getPool();
  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    for (const def of APP_SETTING_DEFS) {
      const value = values[def.key] ? '1' : '0';
      const request = new sql.Request(transaction);
      request.input('key', sql.NVarChar, def.key);
      request.input('value', sql.NVarChar, value);
      request.input('staffId', sql.Int, req.staff!.staffId);
      await request.query(`
        MERGE App_Setting AS target
        USING (SELECT @key AS SettingKey) AS src ON target.SettingKey = src.SettingKey
        WHEN MATCHED THEN UPDATE SET SettingValue = @value, UpdatedAt = GETDATE(), UpdatedByStaffID = @staffId
        WHEN NOT MATCHED THEN INSERT (SettingKey, SettingValue, UpdatedAt, UpdatedByStaffID) VALUES (@key, @value, GETDATE(), @staffId);
      `);
    }
    await transaction.commit();
  } catch (err) {
    await transaction.rollback();
    throw err;
  }
  res.json({ success: true, data: { saved: true } });
});

// ---------------------------------------------------------------------------
// 6. Device Lock Settings -- per-role toggle (Role_Master.DeviceLockEnabled)
// ---------------------------------------------------------------------------

router.get('/device-lock', async (_req, res) => {
  const pool = await getPool();
  const roles = await pool.request().query(`SELECT RoleID AS roleId, RoleName AS roleName, DeviceLockEnabled AS deviceLockEnabled FROM Role_Master ORDER BY RoleName`);
  res.json({ success: true, data: { roles: roles.recordset } });
});

router.put('/device-lock', async (req, res) => {
  const enabled: number[] = Array.isArray(req.body.enabled) ? req.body.enabled : [];
  const enabledSet = new Set(enabled.map(Number));
  const pool = await getPool();
  const roles = (await pool.request().query(`SELECT RoleID AS roleId FROM Role_Master`)).recordset;

  const transaction = new sql.Transaction(pool);
  await transaction.begin();
  try {
    for (const role of roles) {
      const request = new sql.Request(transaction);
      request.input('roleId', sql.Int, role.roleId);
      request.input('enabled', sql.Bit, enabledSet.has(role.roleId));
      await request.query(`UPDATE Role_Master SET DeviceLockEnabled = @enabled WHERE RoleID = @roleId`);
    }
    await transaction.commit();
  } catch (err) {
    await transaction.rollback();
    throw err;
  }
  res.json({ success: true, data: { saved: true } });
});

// ---------------------------------------------------------------------------
// 7. Registered Devices -- read-only list + reset action (Staff.DeviceID)
// ---------------------------------------------------------------------------

router.get('/devices', async (_req, res) => {
  const pool = await getPool();
  const result = await pool.request().query(`
    SELECT s.StaffID AS staffId, s.FullName AS fullName, s.Username AS username, r.RoleName AS roleName,
           s.DeviceID AS deviceId, s.DeviceRegisteredAt AS deviceRegisteredAt
    FROM Staff s
    LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
    WHERE s.DeviceID IS NOT NULL
    ORDER BY s.DeviceRegisteredAt DESC
  `);
  res.json({ success: true, data: { items: result.recordset } });
});

router.post('/devices/:staffId/reset', async (req, res) => {
  const pool = await getPool();
  await pool
    .request()
    .input('staffId', sql.Int, Number(req.params.staffId))
    .query(`UPDATE Staff SET DeviceID = NULL, DeviceRegisteredAt = NULL WHERE StaffID = @staffId`);
  res.json({ success: true, data: { reset: true } });
});

// ---------------------------------------------------------------------------
// 8. KFS Settings -- singleton NBFC company profile (Company_Profile)
// ---------------------------------------------------------------------------

router.get('/kfs', async (_req, res) => {
  const pool = await getPool();
  const result = await pool.request().query(`
    SELECT CompanyName AS companyName, NBFCRegistrationNo AS nbfcRegistrationNo, RegisteredOfficeAddress AS registeredOfficeAddress,
           CustomerCarePhone AS customerCarePhone, CustomerCareEmail AS customerCareEmail, GrievanceOfficerName AS grievanceOfficerName,
           GrievanceOfficerPhone AS grievanceOfficerPhone, CoolingOffPeriodDays AS coolingOffPeriodDays
    FROM Company_Profile WHERE ProfileID = 1
  `);
  res.json({ success: true, data: result.recordset[0] ?? null });
});

router.put('/kfs', async (req, res) => {
  const {
    companyName,
    nbfcRegistrationNo,
    registeredOfficeAddress,
    customerCarePhone,
    customerCareEmail,
    grievanceOfficerName,
    grievanceOfficerPhone,
    coolingOffPeriodDays,
  } = req.body;
  const pool = await getPool();
  const request = pool.request();
  request.input('companyName', sql.NVarChar, companyName);
  request.input('nbfcRegistrationNo', sql.NVarChar, nbfcRegistrationNo);
  request.input('registeredOfficeAddress', sql.NVarChar, registeredOfficeAddress);
  request.input('customerCarePhone', sql.NVarChar, customerCarePhone);
  request.input('customerCareEmail', sql.NVarChar, customerCareEmail);
  request.input('grievanceOfficerName', sql.NVarChar, grievanceOfficerName);
  request.input('grievanceOfficerPhone', sql.NVarChar, grievanceOfficerPhone);
  request.input('coolingOffPeriodDays', sql.Int, coolingOffPeriodDays);
  request.input('staffId', sql.Int, req.staff!.staffId);
  await request.query(`
    UPDATE Company_Profile SET
      CompanyName = @companyName, NBFCRegistrationNo = @nbfcRegistrationNo, RegisteredOfficeAddress = @registeredOfficeAddress,
      CustomerCarePhone = @customerCarePhone, CustomerCareEmail = @customerCareEmail, GrievanceOfficerName = @grievanceOfficerName,
      GrievanceOfficerPhone = @grievanceOfficerPhone, CoolingOffPeriodDays = @coolingOffPeriodDays,
      UpdatedAt = GETDATE(), UpdatedByStaffID = @staffId
    WHERE ProfileID = 1
  `);
  res.json({ success: true, data: { saved: true } });
});

export default router;
