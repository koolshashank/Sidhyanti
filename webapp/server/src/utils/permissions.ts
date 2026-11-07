import { getPool, sql } from '../db.js';
import type { AuthClaims } from '../middleware/auth.js';

/**
 * Same Role_Permission check the PHP web CRM's role_has_permission() does
 * (includes/permission_helper.php) -- SuperAdmin bypasses everything, every
 * other role needs an explicit IsAllowed=1 row for the key.
 */
export async function roleHasPermission(claims: AuthClaims, key: string): Promise<boolean> {
  if (claims.roleName === 'SuperAdmin') return true;
  if (!claims.roleId) return false;
  const pool = await getPool();
  const result = await pool
    .request()
    .input('roleId', sql.Int, claims.roleId)
    .input('key', sql.NVarChar, key)
    .query('SELECT IsAllowed FROM Role_Permission WHERE RoleID = @roleId AND PermissionKey = @key');
  return Boolean(result.recordset[0]?.IsAllowed);
}
