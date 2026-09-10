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

/**
 * The global "services enabled" kill switch, driven from billing.php
 * (SuperAdmin only) -- deliberately kept out of APP_SETTING_DEFS so it never
 * shows up on the generic app_settings.php checkbox list, which any Admin
 * (not just SuperAdmin) can edit. A missing row reads as enabled so a fresh
 * install/migration never accidentally locks everyone out.
 */
function services_enabled(PDO $pdo): bool {
    $val = get_app_setting($pdo, 'services_enabled');
    return $val === null || $val === '1' || strtolower($val) === 'true';
}

function set_services_enabled(PDO $pdo, bool $enabled, int $staffId): void {
    $value = $enabled ? '1' : '0';
    $stmt = $pdo->prepare("UPDATE App_Setting SET SettingValue = ?, UpdatedAt = GETDATE(), UpdatedByStaffID = ? WHERE SettingKey = 'services_enabled'");
    $stmt->execute([$value, $staffId]);
    if ($stmt->rowCount() === 0) {
        $pdo->prepare("INSERT INTO App_Setting (SettingKey, SettingValue, UpdatedAt, UpdatedByStaffID) VALUES ('services_enabled', ?, GETDATE(), ?)")
            ->execute([$value, $staffId]);
    }
}
