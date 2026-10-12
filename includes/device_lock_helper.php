<?php
/**
 * Per-role device-binding toggle (Role_Master.DeviceLockEnabled), mirrors
 * the role_has_permission() static-cache-per-request pattern.
 */
function role_device_lock_enabled(PDO $pdo, ?int $roleId): bool {
    if ($roleId === null) {
        return false;
    }
    static $cache = [];
    if (!array_key_exists($roleId, $cache)) {
        $stmt = $pdo->prepare("SELECT DeviceLockEnabled FROM Role_Master WHERE RoleID = ?");
        $stmt->execute([$roleId]);
        $cache[$roleId] = (bool)$stmt->fetchColumn();
    }
    return $cache[$roleId];
}
