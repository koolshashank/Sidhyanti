import { Router } from 'express';
import { getPool, sql } from '../db.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(requireAuth);

/** GET /api/loan-schemes/lookups -- dropdown data for the Loan Schemes filters/form. */
router.get('/lookups', async (_req, res) => {
  const pool = await getPool();
  const productTypes = await pool.request().query(`SELECT ProductTypeID AS id, ProductTypeName AS name FROM Product_Type_Master ORDER BY ProductTypeName`);
  res.json({ success: true, data: { productTypes: productTypes.recordset } });
});

/**
 * GET /api/loan-schemes
 * Query: page, perPage, product, status (active|inactive), q
 */
router.get('/', async (req, res) => {
  const page = Math.max(1, Number(req.query.page) || 1);
  const perPage = Math.min(100, Math.max(1, Number(req.query.perPage) || 20));
  const product = req.query.product ? Number(req.query.product) : null;
  const status = (req.query.status as string | undefined)?.trim();
  const q = (req.query.q as string | undefined)?.trim();

  const conditions: string[] = [];
  if (product) conditions.push('s.ProductTypeID = @product');
  if (status === 'active') conditions.push('s.IsActive = 1');
  else if (status === 'inactive') conditions.push('s.IsActive = 0');
  if (q) conditions.push('(s.SchemeName LIKE @q OR s.SchemeCode LIKE @q)');
  const whereSql = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';

  const pool = await getPool();

  function bindFilters(request: ReturnType<typeof pool.request>) {
    if (product) request.input('product', sql.Int, product);
    if (q) request.input('q', sql.NVarChar, `%${q}%`);
    return request;
  }

  const total = (await bindFilters(pool.request()).query(`SELECT COUNT(*) AS total FROM Loan_Scheme s ${whereSql}`)).recordset[0].total;

  const listReq = bindFilters(pool.request());
  listReq.input('offset', sql.Int, (page - 1) * perPage);
  listReq.input('perPage', sql.Int, perPage);
  const listResult = await listReq.query(`
    SELECT s.SchemeID AS schemeId, s.SchemeCode AS schemeCode, s.SchemeName AS schemeName, pt.ProductTypeName AS productTypeName,
           s.MinLoanAmount AS minLoanAmount, s.MaxLoanAmount AS maxLoanAmount, s.DefaultLoanAmount AS defaultLoanAmount,
           s.TenureMonths AS tenureMonths, s.InterestRatePercent AS interestRatePercent, s.InterestMethod AS interestMethod,
           s.PaymentFrequency AS paymentFrequency, s.IsActive AS isActive
    FROM Loan_Scheme s
    LEFT JOIN Product_Type_Master pt ON s.ProductTypeID = pt.ProductTypeID
    ${whereSql}
    ORDER BY s.IsActive DESC, s.SchemeName
    OFFSET @offset ROWS FETCH NEXT @perPage ROWS ONLY
  `);

  const statsResult = await pool.request().query(`
    SELECT COUNT(*) AS totalSchemes,
           SUM(CASE WHEN IsActive = 1 THEN 1 ELSE 0 END) AS activeSchemes,
           COUNT(DISTINCT ProductTypeID) AS productTypesUsed,
           AVG(InterestRatePercent) AS avgInterest
    FROM Loan_Scheme
  `);
  const row = statsResult.recordset[0];
  const stats = {
    totalSchemes: row.totalSchemes ?? 0,
    activeSchemes: row.activeSchemes ?? 0,
    productTypesUsed: row.productTypesUsed ?? 0,
    avgInterest: row.avgInterest ? Math.round(Number(row.avgInterest) * 10) / 10 : 0,
  };

  res.json({ success: true, data: { items: listResult.recordset, total, page, perPage, stats } });
});

router.get('/:id', async (req, res) => {
  const pool = await getPool();
  const result = await pool.request().input('id', sql.Int, Number(req.params.id)).query(`SELECT * FROM Loan_Scheme WHERE SchemeID = @id`);
  const row = result.recordset[0];
  if (!row) return res.status(404).json({ success: false, message: 'Scheme not found' });
  res.json({
    success: true,
    data: {
      schemeId: row.SchemeID,
      schemeCode: row.SchemeCode,
      schemeName: row.SchemeName,
      productTypeId: row.ProductTypeID,
      minLoanAmount: row.MinLoanAmount,
      maxLoanAmount: row.MaxLoanAmount,
      defaultLoanAmount: row.DefaultLoanAmount,
      tenureMonths: row.TenureMonths,
      interestRatePercent: row.InterestRatePercent,
      interestMethod: row.InterestMethod,
      paymentFrequency: row.PaymentFrequency,
      processingFeeAmount: row.ProcessingFeeAmount,
      processingFeeGstPercent: row.ProcessingFeeGSTPercent,
      documentChargeAmount: row.DocumentChargeAmount,
      documentChargeGstPercent: row.DocumentChargeGSTPercent,
      otherChargesAmount: row.OtherChargesAmount,
      otherChargesGstPercent: row.OtherChargesGSTPercent,
      loginFeeAmount: row.LoginFeeAmount,
      loginFeeGstPercent: row.LoginFeeGSTPercent,
      insuranceFeeAmount: row.InsuranceFeeAmount,
      insurancePeriodMonths: row.InsurancePeriodMonths,
      sumAssuredClient: row.SumAssuredClient,
      sumAssuredGuarantor: row.SumAssuredGuarantor,
      insuredBy: row.InsuredBy,
      numInstallmentsSlot1: row.NumInstallmentsSlot1,
      installmentAmountSlot1: row.InstallmentAmountSlot1,
      numInstallmentsSlot2: row.NumInstallmentsSlot2,
      installmentAmountSlot2: row.InstallmentAmountSlot2,
      numInstallmentsSlot3: row.NumInstallmentsSlot3,
      installmentAmountSlot3: row.InstallmentAmountSlot3,
      effectiveFrom: row.EffectiveFrom,
      effectiveTo: row.EffectiveTo,
      isActive: row.IsActive,
    },
  });
});

interface SchemePayload {
  schemeCode: string;
  schemeName: string;
  productTypeId?: number | null;
  minLoanAmount?: number | null;
  maxLoanAmount?: number | null;
  defaultLoanAmount?: number | null;
  tenureMonths: number;
  interestRatePercent: number;
  interestMethod?: string | null;
  paymentFrequency: string;
  processingFeeAmount?: number | null;
  processingFeeGstPercent?: number | null;
  documentChargeAmount?: number | null;
  documentChargeGstPercent?: number | null;
  otherChargesAmount?: number | null;
  otherChargesGstPercent?: number | null;
  loginFeeAmount?: number | null;
  loginFeeGstPercent?: number | null;
  insuranceFeeAmount?: number | null;
  insurancePeriodMonths?: number | null;
  sumAssuredClient?: number | null;
  sumAssuredGuarantor?: number | null;
  insuredBy?: string | null;
  numInstallmentsSlot1?: number | null;
  installmentAmountSlot1?: number | null;
  numInstallmentsSlot2?: number | null;
  installmentAmountSlot2?: number | null;
  numInstallmentsSlot3?: number | null;
  installmentAmountSlot3?: number | null;
  effectiveFrom?: string | null;
  effectiveTo?: string | null;
  isActive: boolean;
}

function bindSchemePayload(request: sql.Request, body: SchemePayload) {
  request.input('code', sql.NVarChar, body.schemeCode);
  request.input('name', sql.NVarChar, body.schemeName);
  request.input('productType', sql.Int, body.productTypeId ?? null);
  request.input('minAmt', sql.Decimal(18, 2), body.minLoanAmount ?? null);
  request.input('maxAmt', sql.Decimal(18, 2), body.maxLoanAmount ?? null);
  request.input('defaultAmt', sql.Decimal(18, 2), body.defaultLoanAmount ?? null);
  request.input('tenure', sql.Int, body.tenureMonths);
  request.input('interestRate', sql.Decimal(6, 2), body.interestRatePercent);
  request.input('interestMethod', sql.NVarChar, body.interestMethod ?? null);
  request.input('frequency', sql.NVarChar, body.paymentFrequency);
  request.input('procFee', sql.Decimal(18, 2), body.processingFeeAmount ?? null);
  request.input('procFeeGst', sql.Decimal(6, 2), body.processingFeeGstPercent ?? null);
  request.input('docCharge', sql.Decimal(18, 2), body.documentChargeAmount ?? null);
  request.input('docChargeGst', sql.Decimal(6, 2), body.documentChargeGstPercent ?? null);
  request.input('otherCharges', sql.Decimal(18, 2), body.otherChargesAmount ?? null);
  request.input('otherChargesGst', sql.Decimal(6, 2), body.otherChargesGstPercent ?? null);
  request.input('loginFee', sql.Decimal(18, 2), body.loginFeeAmount ?? null);
  request.input('loginFeeGst', sql.Decimal(6, 2), body.loginFeeGstPercent ?? null);
  request.input('insFee', sql.Decimal(18, 2), body.insuranceFeeAmount ?? null);
  request.input('insPeriod', sql.Int, body.insurancePeriodMonths ?? null);
  request.input('sac', sql.Decimal(18, 2), body.sumAssuredClient ?? null);
  request.input('sag', sql.Decimal(18, 2), body.sumAssuredGuarantor ?? null);
  request.input('insuredBy', sql.NVarChar, body.insuredBy ?? null);
  request.input('slot1n', sql.Int, body.numInstallmentsSlot1 ?? null);
  request.input('slot1amt', sql.Decimal(18, 2), body.installmentAmountSlot1 ?? null);
  request.input('slot2n', sql.Int, body.numInstallmentsSlot2 ?? null);
  request.input('slot2amt', sql.Decimal(18, 2), body.installmentAmountSlot2 ?? null);
  request.input('slot3n', sql.Int, body.numInstallmentsSlot3 ?? null);
  request.input('slot3amt', sql.Decimal(18, 2), body.installmentAmountSlot3 ?? null);
  request.input('effFrom', sql.Date, body.effectiveFrom ?? null);
  request.input('effTo', sql.Date, body.effectiveTo ?? null);
  request.input('isActive', sql.Bit, body.isActive);
}

router.post('/', async (req, res) => {
  const body: SchemePayload = req.body;
  if (!body.schemeCode?.trim() || !body.schemeName?.trim()) {
    return res.status(400).json({ success: false, message: 'Scheme Code and Scheme Name are required' });
  }
  const pool = await getPool();
  const request = pool.request();
  bindSchemePayload(request, body);
  request.input('createdBy', sql.Int, req.staff!.staffId);
  try {
    const result = await request.query(`
      INSERT INTO Loan_Scheme (
        SchemeCode, SchemeName, ProductTypeID, MinLoanAmount, MaxLoanAmount, DefaultLoanAmount,
        TenureMonths, InterestRatePercent, InterestMethod, PaymentFrequency,
        ProcessingFeeAmount, ProcessingFeeGSTPercent, DocumentChargeAmount, DocumentChargeGSTPercent,
        OtherChargesAmount, OtherChargesGSTPercent, LoginFeeAmount, LoginFeeGSTPercent,
        InsuranceFeeAmount, InsurancePeriodMonths, SumAssuredClient, SumAssuredGuarantor, InsuredBy,
        NumInstallmentsSlot1, InstallmentAmountSlot1, NumInstallmentsSlot2, InstallmentAmountSlot2,
        NumInstallmentsSlot3, InstallmentAmountSlot3, EffectiveFrom, EffectiveTo, IsActive, CreatedBy, CreatedAt
      )
      OUTPUT INSERTED.SchemeID AS schemeId
      VALUES (
        @code, @name, @productType, @minAmt, @maxAmt, @defaultAmt,
        @tenure, @interestRate, @interestMethod, @frequency,
        @procFee, @procFeeGst, @docCharge, @docChargeGst,
        @otherCharges, @otherChargesGst, @loginFee, @loginFeeGst,
        @insFee, @insPeriod, @sac, @sag, @insuredBy,
        @slot1n, @slot1amt, @slot2n, @slot2amt,
        @slot3n, @slot3amt, @effFrom, @effTo, @isActive, @createdBy, GETDATE()
      )
    `);
    res.json({ success: true, data: { schemeId: result.recordset[0].schemeId } });
  } catch (err: unknown) {
    res.status(409).json({ success: false, message: 'Could not create scheme -- the scheme code may already be in use' });
  }
});

router.put('/:id', async (req, res) => {
  const body: SchemePayload = req.body;
  if (!body.schemeCode?.trim() || !body.schemeName?.trim()) {
    return res.status(400).json({ success: false, message: 'Scheme Code and Scheme Name are required' });
  }
  const pool = await getPool();
  const request = pool.request();
  bindSchemePayload(request, body);
  request.input('id', sql.Int, Number(req.params.id));
  request.input('updatedBy', sql.Int, req.staff!.staffId);
  try {
    await request.query(`
      UPDATE Loan_Scheme SET
        SchemeCode=@code, SchemeName=@name, ProductTypeID=@productType, MinLoanAmount=@minAmt, MaxLoanAmount=@maxAmt, DefaultLoanAmount=@defaultAmt,
        TenureMonths=@tenure, InterestRatePercent=@interestRate, InterestMethod=@interestMethod, PaymentFrequency=@frequency,
        ProcessingFeeAmount=@procFee, ProcessingFeeGSTPercent=@procFeeGst, DocumentChargeAmount=@docCharge, DocumentChargeGSTPercent=@docChargeGst,
        OtherChargesAmount=@otherCharges, OtherChargesGSTPercent=@otherChargesGst, LoginFeeAmount=@loginFee, LoginFeeGSTPercent=@loginFeeGst,
        InsuranceFeeAmount=@insFee, InsurancePeriodMonths=@insPeriod, SumAssuredClient=@sac, SumAssuredGuarantor=@sag, InsuredBy=@insuredBy,
        NumInstallmentsSlot1=@slot1n, InstallmentAmountSlot1=@slot1amt, NumInstallmentsSlot2=@slot2n, InstallmentAmountSlot2=@slot2amt,
        NumInstallmentsSlot3=@slot3n, InstallmentAmountSlot3=@slot3amt, EffectiveFrom=@effFrom, EffectiveTo=@effTo, IsActive=@isActive,
        UpdatedBy=@updatedBy, UpdatedAt=GETDATE()
      WHERE SchemeID=@id
    `);
    res.json({ success: true, data: { schemeId: Number(req.params.id) } });
  } catch (err: unknown) {
    res.status(409).json({ success: false, message: 'Could not update scheme -- the scheme code may already be in use' });
  }
});

export default router;
