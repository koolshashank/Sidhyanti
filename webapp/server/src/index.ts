import 'dotenv/config';
import 'express-async-errors';
import express from 'express';
import cors from 'cors';
import path from 'path';
import { fileURLToPath } from 'url';
import periodLockRouter from './routes/periodLock.js';
import authRouter from './routes/auth.js';
import dashboardRouter from './routes/dashboard.js';
import attendanceRouter from './routes/attendance.js';
import collectionsRouter from './routes/collections.js';
import grtRouter from './routes/grt.js';
import cgtRouter from './routes/cgt.js';
import settingsRouter from './routes/settings.js';
import tvrRouter from './routes/tvr.js';
import deathEntriesRouter from './routes/deathEntries.js';
import auditLogsRouter from './routes/auditLogs.js';
import incentiveRouter from './routes/incentive.js';
import loanSchemesRouter from './routes/loanSchemes.js';
import billingRouter from './routes/billing.js';
import loanProposalsRouter from './routes/loanProposals.js';
import loanApprovalsRouter from './routes/loanApprovals.js';
import loanWriteoffsRouter from './routes/loanWriteoffs.js';
import menuVisibilityRouter from './routes/menuVisibility.js';
import productTypesRouter from './routes/productTypes.js';
import paymentFrequenciesRouter from './routes/paymentFrequencies.js';
import chartOfAccountsRouter from './routes/chartOfAccounts.js';
import journalEntriesRouter from './routes/journalEntries.js';
import accountingReportsRouter from './routes/accountingReports.js';
import branchesRouter from './routes/branches.js';
import centersRouter from './routes/centers.js';
import loansRouter from './routes/loans.js';
import membersRouter from './routes/members.js';
import staffRouter from './routes/staff.js';

const app = express();

app.use(cors({ origin: process.env.CORS_ORIGIN ?? '*' }));
app.use(express.json());

const __dirname = path.dirname(fileURLToPath(import.meta.url));
app.use('/uploads', express.static(path.join(__dirname, '..', 'uploads')));

app.get('/api/health', (_req, res) => res.json({ success: true, data: { status: 'ok' } }));
app.use('/api/auth', authRouter);
app.use('/api/dashboard', dashboardRouter);
app.use('/api/staff', staffRouter);
app.use('/api/members', membersRouter);
app.use('/api/loans', loansRouter);
app.use('/api/centers', centersRouter);
app.use('/api/branches', branchesRouter);
app.use('/api/attendance', attendanceRouter);
app.use('/api/collections', collectionsRouter);
app.use('/api/grt', grtRouter);
app.use('/api/cgt', cgtRouter);
app.use('/api/settings', settingsRouter);
app.use('/api/tvr', tvrRouter);
app.use('/api/death-entries', deathEntriesRouter);
app.use('/api/audit-logs', auditLogsRouter);
app.use('/api/incentive', incentiveRouter);
app.use('/api/loan-schemes', loanSchemesRouter);
app.use('/api/billing', billingRouter);
app.use('/api/loan-proposals', loanProposalsRouter);
app.use('/api/loan-approvals', loanApprovalsRouter);
app.use('/api/loan-writeoffs', loanWriteoffsRouter);
app.use('/api/menu-visibility', menuVisibilityRouter);
app.use('/api/product-types', productTypesRouter);
app.use('/api/payment-frequencies', paymentFrequenciesRouter);
app.use('/api/chart-of-accounts', chartOfAccountsRouter);
app.use('/api/journal-entries', journalEntriesRouter);
app.use('/api/accounting-reports', accountingReportsRouter);
app.use('/api/period-lock', periodLockRouter);

// Centralized error handler -- every route above can just `throw`/reject
// and land here instead of each one needing its own try/catch boilerplate.
app.use((err: unknown, _req: express.Request, res: express.Response, _next: express.NextFunction) => {
  console.error(err);
  res.status(500).json({ success: false, message: 'Internal server error' });
});

const port = Number(process.env.PORT ?? 4000);
app.listen(port, () => console.log(`Sidhyanti CRM webapp API listening on :${port}`));
