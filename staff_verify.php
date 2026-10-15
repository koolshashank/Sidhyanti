<?php
/**
 * Public, no-login staff ID verification page -- the target of the QR code
 * on a staff member's Digital ID Card (mobile app). Anyone -- a client, not
 * necessarily with the app or an account -- scans the QR with their phone's
 * camera and lands here to confirm the person in front of them is a real,
 * currently active Sidhyanti CRM staff member.
 *
 * Deliberately does NOT include includes/header.php (that requires a
 * session) -- just the raw DB connection, and only ever shows non-sensitive
 * fields (name, photo, designation, branch, active status). No phone
 * number, address, or anything else goes on this page.
 */
require_once __DIR__ . '/config/db.php';

$staffId = (int)($_GET['id'] ?? 0);
$code = trim((string)($_GET['code'] ?? ''));

$staff = null;
if ($staffId > 0 && $code !== '') {
    $stmt = $pdo->prepare("
        SELECT s.FullName, s.EmployeeCode, s.Photo, s.IsActive, d.DesignationName, b.BranchName
        FROM Staff s
        LEFT JOIN Designation_Master d ON s.DesignationID = d.DesignationID
        LEFT JOIN Branch b ON s.BranchID = b.BranchID
        WHERE s.StaffID = ? AND s.EmployeeCode = ?
    ");
    $stmt->execute([$staffId, $code]);
    $staff = $stmt->fetch();
}

$verified = $staff && (bool)$staff['IsActive'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff Verification | Sidhyanti CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  :root { --navy: #3B6FED; --success: #1f9d55; --danger: #d64545; --bg: #f4f6f9; --border: #e6e9ef; }
  * { box-sizing: border-box; }
  body { font-family: 'Inter', -apple-system, sans-serif; background: var(--bg); margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
  .card { background: #fff; border-radius: 20px; box-shadow: 0 8px 24px rgba(11,37,69,0.09); max-width: 380px; width: 100%; padding: 32px 28px; text-align: center; }
  .badge-row { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 12.5px; font-weight: 700; margin-bottom: 20px; }
  .badge-verified { background: #d9f2e2; color: #167a44; }
  .badge-failed { background: #fbdbdb; color: #a52323; }
  .photo { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; margin: 0 auto 16px; display: block; border: 3px solid var(--border); }
  .avatar-fallback {
    width: 96px; height: 96px; border-radius: 50%; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--navy), #6B93F5); color: #fff; font-weight: 800; font-size: 32px;
  }
  .name { font-size: 20px; font-weight: 800; color: #1c2530; margin-bottom: 4px; }
  .meta { font-size: 13.5px; color: #6b7688; margin-bottom: 2px; }
  .divider { border: none; border-top: 1px solid var(--border); margin: 20px 0; }
  .footer-note { font-size: 11.5px; color: #9aa4b2; line-height: 1.5; }
  .fail-icon { font-size: 48px; color: var(--danger); margin-bottom: 12px; }
</style>
</head>
<body>
<div class="card">
<?php if ($verified): ?>
  <div class="badge-row badge-verified"><i class="bi bi-patch-check-fill"></i> Verified Active Staff</div>
  <?php if (!empty($staff['Photo'])):
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $photoUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/sidhyanti-crm-v2/' . ltrim($staff['Photo'], '/');
  ?>
    <img class="photo" src="<?= htmlspecialchars($photoUrl) ?>" alt="">
  <?php else: ?>
    <div class="avatar-fallback"><?= htmlspecialchars(strtoupper(substr($staff['FullName'], 0, 1))) ?></div>
  <?php endif; ?>
  <div class="name"><?= htmlspecialchars($staff['FullName']) ?></div>
  <div class="meta"><?= htmlspecialchars($staff['DesignationName'] ?? 'Staff') ?></div>
  <?php if ($staff['BranchName']): ?><div class="meta"><i class="bi bi-building"></i> <?= htmlspecialchars($staff['BranchName']) ?></div><?php endif; ?>
  <hr class="divider">
  <div class="footer-note">This confirms the person is a genuine, currently active Sidhyanti CRM field staff member. If anything feels off, please contact your branch directly before sharing any payment or personal information.</div>
<?php else: ?>
  <div class="fail-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
  <div class="badge-row badge-failed"><i class="bi bi-x-circle-fill"></i> Not Verified</div>
  <div class="name" style="font-size:16px;">We could not verify this staff member</div>
  <hr class="divider">
  <div class="footer-note">This QR code does not match an active Sidhyanti CRM staff record. Please do not share any payment or personal information, and contact your branch office directly to confirm before proceeding.</div>
<?php endif; ?>
</div>
</body>
</html>
