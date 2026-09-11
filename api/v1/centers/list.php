<?php
/**
 * GET /api/v1/centers/list ("my centers" for the Client Sourcing flow)
 * Returns every center this staff member manages (ManagedByStaffID = caller),
 * i.e. the centers they created via /centers/create, along with each
 * center's *latest* CGT record (a center can have more than one CGT row
 * over time -- the most recent one determines current Done/Pending status,
 * same "latest per center" rule cgt.php uses).
 *
 * Also returns ApprovalStatus -- a center awaiting Branch Manager approval
 * cannot have CGT conducted yet (see centers/cgt_create.php); the app uses
 * this to show "Awaiting Approval" instead of letting the user tap through.
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

$claims = require_api_auth();

$stmt = $pdo->prepare("
    WITH LatestCGT AS (
        SELECT ccd.*, ROW_NUMBER() OVER (PARTITION BY ccd.CenterID ORDER BY ccd.CGTDate DESC, ccd.CGTID DESC) AS rn
        FROM Center_CGT_Details ccd
    )
    SELECT c.CenterID, c.CenterCode, c.CenterName, c.MeetingDay, c.MeetingTime, c.Address, c.CenterPlace, c.Latitude, c.Longitude,
           c.ApprovalStatus, lc.CGTID, lc.CGTDate, lc.DocumentFilePath, lc.Remarks, st.FullName AS ConductedBy
    FROM Center c
    LEFT JOIN LatestCGT lc ON lc.CenterID = c.CenterID AND lc.rn = 1
    LEFT JOIN Staff st ON lc.ConductedByStaffID = st.StaffID
    WHERE c.ManagedByStaffID = ? AND c.IsActive = 1
    ORDER BY c.CenterName
");
$stmt->execute([(int)$claims['StaffID']]);
$centers = $stmt->fetchAll();

json_response(true, [
    'centers' => array_map(function ($c) {
        return [
            'id'             => (int)$c['CenterID'],
            'code'           => $c['CenterCode'],
            'name'           => $c['CenterName'],
            'meeting_day'    => $c['MeetingDay'],
            'meeting_time'   => $c['MeetingTime'],
            'address'        => $c['Address'],
            'place'          => $c['CenterPlace'],
            'latitude'       => $c['Latitude'] !== null ? (float)$c['Latitude'] : null,
            'longitude'      => $c['Longitude'] !== null ? (float)$c['Longitude'] : null,
            'approval_status' => $c['ApprovalStatus'],
            'cgt_done'       => $c['CGTID'] !== null,
            'cgt_date'       => to_iso_date($c['CGTDate']),
            'cgt_image_url'  => $c['DocumentFilePath'],
            'cgt_remarks'    => $c['Remarks'],
            'cgt_conducted_by' => $c['ConductedBy'],
        ];
    }, $centers),
]);
