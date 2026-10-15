<?php
$pageTitle = 'Staff Tracking';
$crumb = 'Live location of staff currently on duty, and their route history';
$active = 'staff_tracking';
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can view Staff Tracking.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// -- Live map: every staff member currently on an Active duty session. --
$liveStmt = $pdo->query("
    SELECT s.StaffID, s.FullName, s.Phone, b.BranchName,
           sll.Latitude, sll.Longitude, sll.Accuracy, sll.CapturedAt
    FROM Staff_Live_Location sll
    JOIN Duty_Session ds ON sll.SessionID = ds.SessionID AND ds.Status = 'Active'
    JOIN Staff s ON sll.StaffID = s.StaffID
    LEFT JOIN Branch b ON s.BranchID = b.BranchID
    ORDER BY sll.CapturedAt DESC
");
$liveStaff = $liveStmt->fetchAll();

// -- Route history filters. --
$staffId = (int)($_GET['staff_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$staffList = $pdo->query("SELECT StaffID, FullName FROM Staff WHERE IsActive = 1 ORDER BY FullName")->fetchAll();

$routePoints = [];
$routeStaffName = '';
if ($staffId > 0) {
    $nameStmt = $pdo->prepare("SELECT FullName FROM Staff WHERE StaffID = ?");
    $nameStmt->execute([$staffId]);
    $routeStaffName = $nameStmt->fetchColumn() ?: '';

    $routeStmt = $pdo->prepare("
        SELECT Latitude, Longitude, Accuracy, CapturedAt
        FROM Duty_Session_Point
        WHERE StaffID = ?
          AND CAST(CapturedAt AS DATE) = ?
          AND (Accuracy IS NULL OR Accuracy <= 100)
        ORDER BY CapturedAt ASC
    ");
    $routeStmt->execute([$staffId, $date]);
    $routePoints = $routeStmt->fetchAll();
}

function haversine_km(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$routeDistanceKm = 0.0;
$prev = null;
foreach ($routePoints as $p) {
    if ($prev !== null) {
        $routeDistanceKm += haversine_km($prev[0], $prev[1], (float)$p['Latitude'], (float)$p['Longitude']);
    }
    $prev = [(float)$p['Latitude'], (float)$p['Longitude']];
}
$routeDurationMin = 0;
if (count($routePoints) >= 2) {
    $routeDurationMin = (int)round((strtotime($routePoints[count($routePoints) - 1]['CapturedAt']) - strtotime($routePoints[0]['CapturedAt'])) / 60);
}
?>

<div class="card-x mb-3">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
    <h5 style="margin:0;"><i class="bi bi-broadcast"></i> Live Map</h5>
    <span style="font-size:12.5px;color:var(--text-muted);"><?= count($liveStaff) ?> staff on duty right now</span>
  </div>
  <?php if (!$liveStaff): ?>
    <div class="empty-state">No staff currently on duty.</div>
  <?php else: ?>
    <div id="liveMap" style="height:420px;border-radius:12px;"></div>
  <?php endif; ?>
</div>

<div class="card-x">
  <h5 style="margin-top:0;"><i class="bi bi-signpost-split"></i> Route History</h5>
  <form method="GET" class="row g-3 align-items-end" style="margin-bottom:16px;">
    <div class="col-md-4">
      <label class="form-label">Staff</label>
      <select class="form-select" name="staff_id">
        <option value="">Select staff...</option>
        <?php foreach ($staffList as $s): ?>
          <option value="<?= $s['StaffID'] ?>" <?= $staffId === (int)$s['StaffID'] ? 'selected' : '' ?>><?= htmlspecialchars($s['FullName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Date</label>
      <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($date) ?>" max="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-md-2">
      <button class="btn-navy" type="submit" style="width:100%;">View Route</button>
    </div>
  </form>

  <?php if ($staffId > 0): ?>
    <?php if (!$routePoints): ?>
      <div class="empty-state">No route data for <?= htmlspecialchars($routeStaffName) ?> on <?= htmlspecialchars($date) ?>.</div>
    <?php else: ?>
      <div style="display:flex;gap:24px;margin-bottom:12px;flex-wrap:wrap;">
        <div><span style="font-size:11px;color:var(--text-muted);">DISTANCE</span><br><strong><?= round($routeDistanceKm, 2) ?> km</strong></div>
        <div><span style="font-size:11px;color:var(--text-muted);">DURATION</span><br><strong><?= $routeDurationMin ?> min</strong></div>
        <div><span style="font-size:11px;color:var(--text-muted);">POINTS</span><br><strong><?= count($routePoints) ?></strong></div>
      </div>
      <div id="routeMap" style="height:420px;border-radius:12px;"></div>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty-state">Select a staff member and date to view their route.</div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl: 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/images/marker-shadow.png',
});

<?php if ($liveStaff): ?>
const liveStaff = <?= json_encode(array_map(fn($s) => [
    'name' => $s['FullName'],
    'branch' => $s['BranchName'],
    'lat' => (float)$s['Latitude'],
    'lng' => (float)$s['Longitude'],
    'updated' => notif_time_ago($s['CapturedAt']),
], $liveStaff)) ?>;

const liveMap = L.map('liveMap').setView([liveStaff[0].lat, liveStaff[0].lng], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
}).addTo(liveMap);
liveStaff.forEach(s => {
  L.marker([s.lat, s.lng]).addTo(liveMap)
    .bindPopup(`<strong>${s.name}</strong><br>${s.branch ?? ''}<br><span style="color:#888;">Updated ${s.updated}</span>`);
});
<?php endif; ?>

<?php if ($staffId > 0 && $routePoints): ?>
const routePoints = <?= json_encode(array_map(fn($p) => [(float)$p['Latitude'], (float)$p['Longitude']], $routePoints)) ?>;
const routeMap = L.map('routeMap').setView(routePoints[0], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
}).addTo(routeMap);
L.polyline(routePoints, {color: '#E65100', weight: 4}).addTo(routeMap);
L.marker(routePoints[0]).addTo(routeMap).bindPopup('Start');
L.marker(routePoints[routePoints.length - 1]).addTo(routeMap).bindPopup('End');
routeMap.fitBounds(routePoints);
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
