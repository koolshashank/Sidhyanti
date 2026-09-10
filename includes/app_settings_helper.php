<?php
/**
 * General-purpose app-wide settings, configurable by Admin via
 * app_settings.php and read by the mobile app (api/v1/app/version.php,
 * checked once per launch). Stored in App_Setting (SettingKey,
 * SettingValue) -- add a new entry here whenever a new toggle is needed,
 * no schema change required.
 */

/** key => [label, type, default] -- type is 'bool' for now, extend as needed. */
const APP_SETTING_DEFS = [
    'show_festival_banner' => ['label' => 'Show the festival banner on the mobile dashboard', 'type' => 'bool', 'default' => '1'],
];

function get_app_setting(PDO $pdo, string $key): ?string {
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("SELECT SettingValue FROM App_Setting WHERE SettingKey = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $cache[$key] = ($val === false ? null : $val);
}

function get_app_setting_bool(PDO $pdo, string $key): bool {
    $val = get_app_setting($pdo, $key) ?? (APP_SETTING_DEFS[$key]['default'] ?? '0');
    return $val === '1' || strtolower($val) === 'true';
}
