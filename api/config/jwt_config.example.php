<?php
/**
 * JWT settings for the Sidhyanti CRM mobile REST API.
 * Copy this file to api/config/jwt_config.php and set a real random
 * secret -- api/config/jwt_config.php is gitignored so it never gets
 * committed.
 *
 * JWT_SECRET must stay identical across all servers issuing/verifying tokens,
 * and must never be committed to a public repo. Rotate it only if you're
 * prepared to invalidate every currently-issued access token.
 */
define('JWT_SECRET', 'replace-with-a-long-random-string');

define('JWT_ACCESS_TOKEN_TTL', 3600);          // 1 hour, in seconds
define('JWT_REFRESH_TOKEN_TTL_DAYS', 30);      // offline-first field app -> generous refresh window
