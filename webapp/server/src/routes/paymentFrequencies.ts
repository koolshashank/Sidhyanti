import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAdmin, requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/** GET /api/payment-frequencies -- list, with how many schemes use each (for delete guard). */
router.get('/', async (_req, res) => {
  const pool = await getPool();
  const result = await pool.request().query(`
    SELECT pf.FrequencyID AS frequencyId, pf.FrequencyName AS frequencyName, pf.IsActive AS isActive,
           (SELECT COUNT(*) FROM Loan_Scheme s WHERE s.PaymentFrequency = pf.FrequencyName) AS schemeCount
    FROM Payment_Frequency_Master pf
    ORDER BY pf.FrequencyID
  `);
  res.json({ success: true, data: { items: result.recordset } });
});

router.post('/', requireAdmin, async (req, res) => {
  const name = (req.body.frequencyName as string | undefined)?.trim();
  if (!name) return res.status(400).json({ success: false, message: 'Frequency name is required' });
  const pool = await getPool();
  try {
    const result = await pool.request().input('name', sql.NVarChar, name).query(`
      INSERT INTO Payment_Frequency_Master (FrequencyName) OUTPUT INSERTED.FrequencyID AS frequencyId VALUES (@name)
    `);
    res.json({ success: true, data: { frequencyId: result.recordset[0].frequencyId } });
  } catch {
    res.status(409).json({ success: false, message: 'Could not create -- this frequency name may already exist' });
  }
});

router.put('/:id', requireAdmin, async (req, res) => {
  const name = (req.body.frequencyName as string | undefined)?.trim();
  const isActive = req.body.isActive ?? true;
  if (!name) return res.status(400).json({ success: false, message: 'Frequency name is required' });
  const pool = await getPool();
  try {
    await pool
      .request()
      .input('id', sql.Int, Number(req.params.id))
      .input('name', sql.NVarChar, name)
      .input('isActive', sql.Bit, isActive)
      .query(`UPDATE Payment_Frequency_Master SET FrequencyName = @name, IsActive = @isActive WHERE FrequencyID = @id`);
    res.json({ success: true, data: { frequencyId: Number(req.params.id) } });
  } catch {
    res.status(409).json({ success: false, message: 'Could not update -- this frequency name may already exist' });
  }
});

router.delete('/:id', requireAdmin, async (req, res) => {
  const pool = await getPool();
  const id = Number(req.params.id);
  const row = (await pool.request().input('id', sql.Int, id).query(`SELECT FrequencyName FROM Payment_Frequency_Master WHERE FrequencyID = @id`)).recordset[0];
  if (!row) return res.status(404).json({ success: false, message: 'Not found' });
  const inUse = (
    await pool.request().input('name', sql.NVarChar, row.FrequencyName).query(`SELECT COUNT(*) AS total FROM Loan_Scheme WHERE PaymentFrequency = @name`)
  ).recordset[0].total;
  if (inUse > 0) {
    return res.status(400).json({ success: false, message: `Cannot delete -- ${inUse} loan scheme(s) still use this frequency. Deactivate it instead.` });
  }
  await pool.request().input('id', sql.Int, id).query(`DELETE FROM Payment_Frequency_Master WHERE FrequencyID = @id`);
  res.json({ success: true, data: { deleted: true } });
});

export default router;
