<?php
// Buffered so a page can still call header('Location: ...') to redirect
// after a POST handler runs -- header.php itself echoes HTML (sidebar,
// notification bell, etc.) before a page's own POST-handling code runs,
// which would otherwise send headers too early and break every
// "save and redirect back" page (role_permissions.php, death_entries.php,
// mobile_menu_access.php, ...).
ob_start();
require_once __DIR__ . '/functions.php';
require_login();
$u = current_staff();
$pageTitle = $pageTitle ?? 'Dashboard';
$crumb = $crumb ?? '';
$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM Notification WHERE StaffID = ? AND IsRead = 0");
$stmtUnread->execute([$u['StaffID']]);
$unreadNotifCount = (int)$stmtUnread->fetchColumn();

$recentNotifStmt = $pdo->prepare("
  SELECT TOP 8 n.*, nt.TypeName FROM Notification n
  LEFT JOIN Notification_Type_Master nt ON n.NotificationTypeID = nt.TypeID
  WHERE n.StaffID = ? ORDER BY n.IsRead ASC, n.CreatedAt DESC
");
$recentNotifStmt->execute([$u['StaffID']]);
$recentNotifs = $recentNotifStmt->fetchAll();
$notifTypeIcon = ['Info' => 'ℹ️', 'Warning' => '⚠️', 'Success' => '✅', 'Danger' => '🚨'];

function notif_time_ago($dt) {
    $diff = time() - strtotime($dt);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($pageTitle) ?> | Sidhyanti CRM</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<?= $extraHead ?? '' ?>
</head>
<body>
<div class="app-shell">
<?php include __DIR__ . '/sidebar.php'; ?>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <button type="button" class="menu-toggle" id="menuToggleBtn" aria-label="Open menu" aria-expanded="false" aria-controls="sidebar">
          <span></span><span></span><span></span>
        </button>
        <div>
          <h4><?= htmlspecialchars($pageTitle) ?></h4>
          <?php if ($crumb): ?><div class="crumb"><?= htmlspecialchars($crumb) ?></div><?php endif; ?>
        </div>
      </div>
      <div class="user-chip">
        <div class="dropdown">
          <button type="button" class="notif-bell" data-bs-toggle="dropdown" aria-expanded="false" id="notifBellBtn">
            🔔
            <?php if ($unreadNotifCount > 0): ?><span class="notif-dot" id="notifDot"></span><?php endif; ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end notif-panel" aria-labelledby="notifBellBtn">
            <div class="notif-panel-head">
              <span>Notifications</span>
              <?php if ($unreadNotifCount > 0): ?>
                <button type="button" class="notif-mark-all" id="notifMarkAllBtn">Mark all read</button>
              <?php endif; ?>
            </div>
            <div class="notif-panel-list" id="notifPanelList">
              <?php foreach ($recentNotifs as $n): ?>
                <div class="notif-item <?= $n['IsRead'] ? '' : 'unread' ?>" data-notif-id="<?= $n['NotificationID'] ?>">
                  <div class="notif-item-icon"><?= $notifTypeIcon[$n['TypeName']] ?? 'ℹ️' ?></div>
                  <div class="notif-item-body">
                    <div class="notif-item-title"><?= htmlspecialchars($n['Title']) ?></div>
                    <div class="notif-item-msg"><?= htmlspecialchars($n['Message']) ?></div>
                    <div class="notif-item-time"><?= notif_time_ago($n['CreatedAt']) ?></div>
                  </div>
                  <?php if (!$n['IsRead']): ?><span class="notif-item-dot"></span><?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php if (!$recentNotifs): ?><div class="notif-panel-empty">You're all caught up 🎉</div><?php endif; ?>
            </div>
            <a href="notifications.php" class="notif-panel-foot">View all notifications</a>
          </div>
        </div>
        <div class="dropdown">
          <button type="button" class="profile-chip" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="avatar"><?= initials($u['FullName'] ?? '?') ?></div>
            <div class="profile-chip-text">
              <div class="profile-chip-name"><?= htmlspecialchars($u['FullName'] ?? 'Unknown') ?></div>
              <div class="profile-chip-role"><?= htmlspecialchars($u['DesignationName'] ?? $u['RoleName'] ?? '') ?></div>
            </div>
            <span class="profile-chip-caret">▾</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="notifications.php">🔔 Notifications<?= $unreadNotifCount > 0 ? ' (' . $unreadNotifCount . ')' : '' ?></a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="logout.php">↪ Logout</a></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="content">
