<?php
/**
 * Which mobile-app screens a Designation can see, configurable by Admin via
 * mobile_menu_access.php. Stored in Designation_Mobile_Menu (DesignationID,
 * MenuKey, IsVisible) -- a designation with no row for a given key is
 * treated as not visible (deny-by-default), same convention as
 * Role_Permission / permission_helper.php.
 *
 * Keyed by Designation rather than Role: Role_Master only has 6 broad
 * roles, but real job-function variety (CM, AM, PCM, RM, SCM, ...) lives in
 * Designation_Master, often several designations sharing one Role.
 */

/** Human-readable labels for every mobile menu key, shown on mobile_menu_access.php. */
const MOBILE_MENU_DEFS = [
    'create_center'   => 'Create New Center',
    'client_sourcing' => 'Client Sourcing (new lead capture)',
    'flo_collection'  => 'FLO Collection',
    'client_details'  => 'Client Details (member list)',
    'reloan'          => 'Reloan (worklist of reloan-eligible clients)',
    'bm_approval'     => 'BM Approval (proposals, preclose, leave, partial payments)',
    'disbursement'    => 'Disbursement',
    'dcr'             => 'DCR (Daily Collection Report)',
    'center_approval' => 'Center Approval',
];

/** Every mobile menu key this designation is allowed to see, most-configured-first not guaranteed -- caller decides display order. */
function mobile_menu_items_for_designation(PDO $pdo, ?int $designationId): array {
    if (!$designationId) {
        return [];
    }
    static $cache = [];
    if (array_key_exists($designationId, $cache)) {
        return $cache[$designationId];
    }
    $stmt = $pdo->prepare("SELECT MenuKey FROM Designation_Mobile_Menu WHERE DesignationID = ? AND IsVisible = 1");
    $stmt->execute([$designationId]);
    return $cache[$designationId] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
