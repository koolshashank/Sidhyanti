<?php
/**
 * Public, no-login member verification page -- the target of a member's
 * "Show My QR" code (mobile app). Anyone who scans it with any phone
 * camera (not necessarily with the app or an account) lands here, instead
 * of getting a generic scanner's "No usable data found" for a plain custom
 * text payload. The app's own in-app scanner (scan_member_qr_screen.dart)
 * parses the same URL's `id` query param and jumps straight to the
 * member's profile instead of opening this page.
 *
 * Deliberately does NOT include includes/header.php (that requires a
 * session) -- just the raw DB connection, and only ever shows non-
 * sensitive fields (name, photo, enrollment no, branch, center). No
 * mobile number, address, or financial details go on this page.
 */
require_once __DIR__ . '/config/db.php';

$memberId = (int)($_GET['id'] ?? 0);
$enrollmentNo = trim((string)($_GET['code'] ?? ''));

$member = null;
if ($memberId > 0 && $enrollmentNo !== '') {
    $stmt = $pdo->prepare("
        SELECT m.FirstName, m.LastName, m.EnrollmentNo, m.IsActive, b.BranchName, c.CenterName,
               (SELECT TOP 1 k.DocFilePath FROM Member_KYC_Document k
                JOIN KYC_Document_Type_Master dt ON k.DocTypeID = dt.DocTypeID
                WHERE k.MemberID = m.MemberID AND dt.DocTypeName = 'Client Photograph'
                ORDER BY k.UploadedAt DESC) AS PhotoUrl
        FROM Member m
        LEFT JOIN Branch b ON m.BranchID = b.BranchID
        LEFT JOIN Center c ON m.CenterID = c.CenterID
        WHERE m.MemberID = ? AND m.EnrollmentNo = ?
    ");
    $stmt->execute([$memberId, $enrollmentNo]);
    $member = $stmt->fetch();
}

$verified = (bool)$member;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Member Verification | Sidhyanti CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  :root { --navy: #3B6FED; --bg: #f4f6f9; --border: #e6e9ef; }
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
  .fail-icon { font-size: 48px; color: #d64545; margin-bottom: 12px; }
</style>
</head>
<body>
<div class="card">
<?php if ($verified): ?>
  <div class="badge-row badge-verified"><i class="bi bi-patch-check-fill"></i> <?= $member['IsActive'] ? 'Verified Active Member' : 'Verified (Inactive) Member' ?></div>
  <?php if (!empty($member['PhotoUrl'])): ?>
    <img class="photo" src="<?= htmlspecialchars($member['PhotoUrl']) ?>" alt="">
  <?php else: ?>
    <div class="avatar-fallback"><?= htmlspecialchars(strtoupper(substr($member['FirstName'], 0, 1))) ?></div>
  <?php endif; ?>
  <div class="name"><?= htmlspecialchars(trim($member['FirstName'] . ' ' . $member['LastName'])) ?></div>
  <div class="meta">Enrollment No: <?= htmlspecialchars($member['EnrollmentNo']) ?></div>
  <?php if ($member['CenterName']): ?><div class="meta"><i class="bi bi-people-fill"></i> <?= htmlspecialchars($member['CenterName']) ?></div><?php endif; ?>
  <?php if ($member['BranchName']): ?><div class="meta"><i class="bi bi-building"></i> <?= htmlspecialchars($member['BranchName']) ?></div><?php endif; ?>
  <hr class="divider">
  <div class="footer-note">This confirms the person is a genuine, registered Sidhyanti CRM member. If anything feels off, please contact your branch directly before sharing any payment or personal information.</div>
<?php else: ?>
  <div class="fail-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
  <div class="badge-row badge-failed"><i class="bi bi-x-circle-fill"></i> Not Verified</div>
  <div class="name" style="font-size:16px;">We could not verify this member</div>
  <hr class="divider">
  <div class="footer-note">This QR code does not match an active Sidhyanti CRM member record. Please contact your branch office directly to confirm before proceeding.</div>
<?php endif; ?>
</div>
</body>
</html>
