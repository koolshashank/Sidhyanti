import sql from 'mssql';

/**
 * Shared connection pool to the same SQL Server database the PHP web CRM
 * and mobile API already use -- same Staff/Role_Master/etc tables, so a
 * staff member's login/role/branch here matches the other two clients
 * exactly, with nothing to keep in sync by hand.
 */
const config: sql.config = {
  server: process.env.DB_HOST!,
  database: process.env.DB_NAME!,
  user: process.env.DB_USER!,
  password: process.env.DB_PASSWORD!,
  options: {
    encrypt: process.env.DB_ENCRYPT === 'true',
    trustServerCertificate: true,
  },
};

let poolPromise: Promise<sql.ConnectionPool> | undefined;

export function getPool(): Promise<sql.ConnectionPool> {
  poolPromise ??= new sql.ConnectionPool(config).connect();
  return poolPromise;
}

export { sql };
