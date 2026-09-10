<?php
/**
 * Sidhyanti CRM v2 - Database Connection
 * Copy this file to config/db.php and fill in your real SQL Server
 * host/credentials -- config/db.php is gitignored so real secrets never
 * get committed.
 *
 * REQUIREMENTS on the PHP server:
 *   - Microsoft Drivers for PHP for SQL Server (pdo_sqlsrv + sqlsrv extensions), OR
 *   - unixODBC + Microsoft ODBC Driver 17/18 for SQL Server (Linux/Mac hosting)
 */
define('DB_HOST', 'localhost');                // e.g. 'SERVERNAME\SQLEXPRESS' or 'localhost,1433'
define('DB_NAME', 'SidhyantiCRM');
define('DB_USER', 'sa');
define('DB_PASS', 'change-me');

try {
    $dsn = "sqlsrv:Server=" . DB_HOST . ";Database=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    try {
        $dsn = "odbc:Driver={ODBC Driver 18 for SQL Server};Server=" . DB_HOST . ";Database=" . DB_NAME . ";TrustServerCertificate=yes";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e2) {
        die("Database connection failed. Make sure the sqlsrv/pdo_sqlsrv extension (or ODBC Driver 17/18 for SQL Server) "
          . "is installed and DB_HOST/DB_NAME/DB_USER/DB_PASS in config/db.php are correct.<br>Error: " . $e2->getMessage());
    }
}
