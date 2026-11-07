import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/**
 * GET /api/audit-logs/lookups -- distinct table names already logged, for the filter dropdown.
 */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const result = await pool.request().query(`SELECT DISTINCT TableName AS name FROM Audit_Log ORDER BY TableName`);
  res.json({ success: true, data: { tables: result.recordset.map((r) => r.name) } });
});

/**
 * GET /api/audit-logs
 * Query: page, perPage, table, action, dateFrom, dateTo
 *
 * Audit_Log rows are written entirely by SQL Server AFTER triggers on
 * Member/Loan/Collection/Interaction/Task/Staff/Branch/Center/
 * Role_Permission/Repayment_Schedule/Reloan_Eligibility_Rule -- not by any
 * application code, PHP or Node. Mutations made through this webapp (e.g.
 * updating a Center, saving Role Permissions) already fire the same
 * triggers since both apps share the one SQL Server database, so this page
 * is purely a read/report view, same as audit_log.php.
 */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(200, Math.max(1, Number(req.query.perPage) || 50));
  const table = (req.query.table as string | undefined)?.trim();
  const action = (req.query.action as string | undefined)?.trim();
  const dateFrom = (req.query.dateFrom as string | undefined) || null;
  const dateTo = (req.query.dateTo as string | undefined) || null;

  const conditions: string[] = [];
  if (table) conditions.push('a.TableName = @table');
  if (action) conditions.push('a.ActionType = @action');
  if (dateFrom) conditions.push('a.ChangedAt >= @dateFrom');
  if (dateTo) conditions.push('a.ChangedAt < DATEADD(DAY, 1, @dateTo)');
  const whereSql = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';

  const pool = await getPool();

  function bindFilters(request: ReturnType<typeof pool.request>) {
    if (table) request.input('table', sql.NVarChar, table);
    if (action) request.input('action', sql.NVarChar, action);
    if (dateFrom) request.input('dateFrom', sql.Date, dateFrom);
    if (dateTo) request.input('dateTo', sql.Date, dateTo);
    return request;
  }

  const baseFrom = `FROM Audit_Log a LEFT JOIN Staff s ON a.ChangedByStaffID = s.StaffID`;

  if (req.query.format === 'csv') {
    const csvResult = await bindFilters(pool.request()).query(`
      SELECT a.ChangedAt AS changedAt, a.TableName AS tableName, a.RecordID AS recordId, a.ActionType AS actionType,
             s.FullName AS staffName, a.OldValues AS oldValues, a.NewValues AS newValues
      ${baseFrom}
      ${whereSql}
      ORDER BY a.ChangedAt DESC
    `);
    const header = ['When', 'Table', 'Record ID', 'Action', 'Changed By', 'Before', 'After'];
    const escape = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = csvResult.recordset.map((r) =>
      [
        r.changedAt ? new Date(r.changedAt).toISOString() : '',
        r.tableName,
        r.recordId,
        r.actionType,
        r.staffName ?? 'System',
        r.oldValues ?? '',
        r.newValues ?? '',
      ]
        .map(escape)
        .join(','),
    );
    const csv = [header.map(escape).join(','), ...rows].join('\r\n');
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', 'attachment; filename="audit-log.csv"');
    return res.send(csv);
  }

  const total = (await bindFilters(pool.request()).query(`SELECT COUNT(*) AS total ${baseFrom} ${whereSql}`)).recordset[0].total;

  const listReq = bindFilters(pool.request());
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);
  const listResult = await listReq.query(`
    SELECT a.AuditID AS auditId, a.ChangedAt AS changedAt, a.TableName AS tableName, a.RecordID AS recordId, a.ActionType AS actionType,
           s.FullName AS staffName, a.OldValues AS oldValues, a.NewValues AS newValues, a.IPAddress AS ipAddress
    ${baseFrom}
    ${whereSql}
    ORDER BY a.ChangedAt DESC
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  res.json({
    success: true,
    data: { items: listResult.recordset, total, page, perPage },
  });
});

export default router;
