import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAdmin, requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/** GET /api/product-types -- list of loan product types + how many schemes use each. */
router.get('/', async (_req, res) => {
  const pool = await getPool();
  const result = await pool.request().query(`
    SELECT pt.ProductTypeID AS productTypeId, pt.ProductTypeName AS productTypeName,
           (SELECT COUNT(*) FROM Loan_Scheme s WHERE s.ProductTypeID = pt.ProductTypeID) AS schemeCount
    FROM Product_Type_Master pt
    ORDER BY pt.ProductTypeName
  `);
  res.json({ success: true, data: { items: result.recordset } });
});

router.post('/', requireAdmin, async (req, res) => {
  const name = (req.body.productTypeName as string | undefined)?.trim();
  if (!name) return res.status(400).json({ success: false, message: 'Product type name is required' });
  const pool = await getPool();
  try {
    const result = await pool.request().input('name', sql.NVarChar, name).query(`
      INSERT INTO Product_Type_Master (ProductTypeName) OUTPUT INSERTED.ProductTypeID AS productTypeId VALUES (@name)
    `);
    res.json({ success: true, data: { productTypeId: result.recordset[0].productTypeId } });
  } catch {
    res.status(409).json({ success: false, message: 'Could not create -- a product type with this name may already exist' });
  }
});

router.put('/:id', requireAdmin, async (req, res) => {
  const name = (req.body.productTypeName as string | undefined)?.trim();
  if (!name) return res.status(400).json({ success: false, message: 'Product type name is required' });
  const pool = await getPool();
  try {
    await pool
      .request()
      .input('id', sql.Int, Number(req.params.id))
      .input('name', sql.NVarChar, name)
      .query(`UPDATE Product_Type_Master SET ProductTypeName = @name WHERE ProductTypeID = @id`);
    res.json({ success: true, data: { productTypeId: Number(req.params.id) } });
  } catch {
    res.status(409).json({ success: false, message: 'Could not update -- a product type with this name may already exist' });
  }
});

router.delete('/:id', requireAdmin, async (req, res) => {
  const pool = await getPool();
  const id = Number(req.params.id);
  const inUse = (
    await pool.request().input('id', sql.Int, id).query(`SELECT COUNT(*) AS total FROM Loan_Scheme WHERE ProductTypeID = @id`)
  ).recordset[0].total;
  if (inUse > 0) {
    return res.status(400).json({ success: false, message: `Cannot delete -- ${inUse} loan scheme(s) still use this product type` });
  }
  await pool.request().input('id', sql.Int, id).query(`DELETE FROM Product_Type_Master WHERE ProductTypeID = @id`);
  res.json({ success: true, data: { deleted: true } });
});

export default router;
