import type { NextFunction, Request, Response } from 'express';
import jwt from 'jsonwebtoken';
import { getPool } from '../db.js';

export interface AuthClaims {
  staffId: number;
  roleId: number | null;
  roleName: string | null;
  branchId: number | null;
  fullName: string;
}

declare global {
  // eslint-disable-next-line @typescript-eslint/no-namespace
  namespace Express {
    interface Request {
      staff?: AuthClaims;
    }
  }
}

/**
 * Mirrors includes/app_settings_helper.php::services_enabled() -- a missing
 * row reads as enabled (fail-open), so a fresh install never locks everyone
 * out. SuperAdmin is exempt, same as PHP's require_login()/require_api_auth().
 */
async function servicesEnabled(): Promise<boolean> {
  const pool = await getPool();
  const result = await pool.request().query(`SELECT SettingValue FROM App_Setting WHERE SettingKey = 'services_enabled'`);
  const value = result.recordset[0]?.SettingValue;
  return value === undefined || value === '1' || String(value).toLowerCase() === 'true';
}

/** Every protected route beyond login/health uses this. */
export async function requireAuth(req: Request, res: Response, next: NextFunction) {
  const header = req.headers.authorization ?? '';
  const token = header.startsWith('Bearer ') ? header.slice(7) : null;
  if (!token) {
    return res.status(401).json({ success: false, message: 'Unauthorized' });
  }
  let claims: AuthClaims;
  try {
    claims = jwt.verify(token, process.env.JWT_SECRET!) as AuthClaims;
  } catch {
    return res.status(401).json({ success: false, message: 'Unauthorized' });
  }
  req.staff = claims;
  if (claims.roleName !== 'SuperAdmin' && !(await servicesEnabled())) {
    return res.status(403).json({ success: false, message: 'Service suspended. Please contact your administrator.' });
  }
  next();
}

/** Billing is SuperAdmin-exclusive -- deliberately stricter than requireAdmin (Admin is excluded). */
export function requireSuperAdmin(req: Request, res: Response, next: NextFunction) {
  if (req.staff?.roleName !== 'SuperAdmin') {
    return res.status(403).json({ success: false, message: 'Only SuperAdmin can access Billing' });
  }
  next();
}

/** Posting/reversing a journal entry is restricted to whoever actually does the books. */
export function requireAccountant(req: Request, res: Response, next: NextFunction) {
  const role = req.staff?.roleName;
  if (role !== 'Admin' && role !== 'SuperAdmin' && role !== 'Accountant') {
    return res.status(403).json({ success: false, message: 'Only an Accountant or Admin can post journal entries' });
  }
  next();
}

/** Settings pages are Admin/SuperAdmin only in the PHP CRM (is_admin_or_super) -- same gate here. */
export function requireAdmin(req: Request, res: Response, next: NextFunction) {
  const role = req.staff?.roleName;
  if (role !== 'Admin' && role !== 'SuperAdmin') {
    return res.status(403).json({ success: false, message: 'Admin access required' });
  }
  next();
}
