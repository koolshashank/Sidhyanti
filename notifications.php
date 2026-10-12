<?php
$pageTitle = 'Notifications';
$crumb = 'System alerts and reminders';
$active = 'notifications';
require_once __DIR__ . '/includes/header.php';

$sid = current_staff()['StaffID'];

if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE Notification SET IsRead=1 WHERE NotificationID=? AND StaffID=?")->execute([$_GET['mark_read'], $sid]);
    header('Location: notifications.php'); exit;
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM Notification WHERE NotificationID=? AND StaffID=?")->execute([$_GET['delete'], $sid]);
    header('Location: notifications.php'); exit;
}
if (isset($_GET['clear_all'])) {
    $pdo->prepare("DELETE FROM Notification WHERE StaffID=?")->execute([$sid]);
    header('Location: notifications.php'); exit;
}

$stmt = $pdo->prepare("
  SELECT n.*, nt.TypeName FROM Notification n
  LEFT JOIN Notification_Type_Master nt ON n.NotificationTypeID = nt.TypeID
  WHERE n.StaffID=? ORDER BY n.IsRead ASC, n.CreatedAt DESC
");
$stmt->execute([$sid]);
$notifications = $stmt->fetchAll();

$typeIcon = ['Info'=>'ℹ️','Warning'=>'⚠️','Success'=>'✅','Danger'=>'🚨'];
?>
<div class="card-x">
  <?php if ($notifications): ?>
  <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
    <a href="?clear_all=1" class="btn-outline-x" style="font-size:11.5px;color:var(--danger);" onclick="return confirm('Clear all notifications? This cannot be undone.');">Clear All</a>
  </div>
  <?php endif; ?>
  <?php foreach ($notifications as $n): ?>
  <div style="display:flex;gap:14px;padding:14px 0;border-bottom:1px solid var(--border);<?= $n['IsRead']?'opacity:0.6;':'' ?>">
    <div style="font-size:22px;"><?= $typeIcon[$n['TypeName']] ?? 'ℹ️' ?></div>
    <div style="flex:1;">
      <div style="font-weight:600;"><?= htmlspecialchars($n['Title']) ?></div>
      <div style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($n['Message']) ?></div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:4px;"><?= fdate($n['CreatedAt']) ?></div>
    </div>
    <div style="display:flex;gap:6px;height:fit-content;">
      <?php if (!$n['IsRead']): ?>
        <a href="?mark_read=<?= $n['NotificationID'] ?>" class="btn-outline-x" style="font-size:11px;padding:5px 10px;">Mark read</a>
      <?php endif; ?>
      <a href="?delete=<?= $n['NotificationID'] ?>" class="btn-outline-x" style="font-size:11px;padding:5px 10px;color:var(--danger);" title="Dismiss">✕</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$notifications): ?><div class="empty-state">You're all caught up 🎉</div><?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
