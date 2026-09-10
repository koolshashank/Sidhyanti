<?php
/**
 * Shown instead of every other page while the Billing kill switch
 * (billing.php, SuperAdmin only) is turned off. Deliberately does NOT call
 * require_login()'s full flow (that's what redirects here in the first
 * place) -- just enough session access to greet the user and offer logout.
 */
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) {
    header('Location: index.php');
    exit;
}
$u = current_staff();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Service Suspended | Sidhyanti CRM</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;">
  <div class="card-x" style="max-width:440px;text-align:center;padding:40px 32px;">
    <div style="font-size:44px;margin-bottom:12px;">🚫</div>
    <div class="section-title" style="margin-bottom:8px;">Service Suspended</div>
    <p style="color:var(--text-muted);font-size:14px;margin-bottom:24px;">
      Hi <?= htmlspecialchars($u['FullName'] ?? '') ?>, this CRM has been temporarily suspended.
      Please contact your administrator to resolve this.
    </p>
    <a href="logout.php" class="btn-navy" style="display:inline-block;">Logout</a>
  </div>
</div>
</body>
</html>
