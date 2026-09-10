<?php
/**
 * GET /api/v1/app/version.php -- no auth required (checked from the splash
 * screen, before login).
 *
 * Reads api/app_version_config.json (committed to git, updated by hand
 * after each release build alongside uploading the new APK to releases/)
 * and returns it with a full, host-relative apk_url the app can download
 * directly.
 */

require_once __DIR__ . '/../../includes/api_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$configPath = __DIR__ . '/../../app_version_config.json';
$config = json_decode((string)@file_get_contents($configPath), true);
if (!is_array($config)) {
    json_response(false, null, 'Version info not available', 500);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$apkUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/sidhyanti-crm-v2/' . ltrim($config['apk_path'] ?? '', '/');

json_response(true, [
    'version_code'   => (int)($config['version_code'] ?? 0),
    'version_name'   => $config['version_name'] ?? '',
    'apk_url'        => $apkUrl,
    'release_notes'  => $config['release_notes'] ?? '',
    'force_update'   => (bool)($config['force_update'] ?? false),
]);
