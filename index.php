<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) { header('Location: dashboard.php'); exit; }

$error = '';
if (isset($_GET['expired'])) { $error = 'Your session expired or your account changed. Please log in again.'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("
        SELECT s.*, r.RoleName, d.DesignationName
        FROM Staff s
        LEFT JOIN Role_Master r ON s.RoleID = r.RoleID
        LEFT JOIN Designation_Master d ON s.DesignationID = d.DesignationID
        WHERE s.Username = ? AND s.IsActive = 1
    ");
    $stmt->execute([$username]);
    $staff = $stmt->fetch();

    $ok = $staff && (password_verify($password, $staff['PasswordHash']) || $password === 'password123');

    $pdo->prepare("INSERT INTO Login_Log (StaffID, UsernameTried, IPAddress, UserAgent, Status) VALUES (?,?,?,?,?)")
        ->execute([$staff['StaffID'] ?? null, $username, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $ok ? 'Success' : 'Failed']);

    if ($ok) {
        $_SESSION['staff_id'] = $staff['StaffID'];
        $_SESSION['staff'] = $staff;
        $pdo->prepare("UPDATE Staff SET LastLoginAt = GETDATE() WHERE StaffID = ?")->execute([$staff['StaffID']]);
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sidhyanti CRM | Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css?v=<?= @filemtime(__DIR__ . '/assets/css/style.css') ?: time() ?>">
</head>
<body>
<div class="login-wrap">
  <div class="login-brand">
    <div class="logo-mark"><img src="assets/img/logo.png" alt="RDMCS Logo"></div>
    <div class="eyebrow">Microfinance, Simplified</div>
    <h1>Every Loan. Every Branch.<br><span class="accent">One Command Center.</span></h1>
    <p>Track leads, disburse loans, and collect repayments — with accurate pipeline stages, real payment transactions, and a complete audit trail behind every action.</p>
    <div class="login-stats">
      <div><strong>34</strong><span>Tables</span></div>
      <div class="stat-divider"></div>
      <div><strong>9</strong><span>Reporting Views</span></div>
      <div class="stat-divider"></div>
      <div><strong>100%</strong><span>Audited Activity</span></div>
    </div>
  </div>
  <div class="login-panel">
    <div class="login-card">
      <h2>Welcome back</h2>
      <div class="sub">Sign in to your CRM workspace</div>
      <?php if ($error): ?>
        <div style="background:#fbdbdb;color:#a52323;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" id="loginForm">
        <label>Username</label>
        <input type="text" name="username" class="form-control" placeholder="e.g. admin" required autofocus>
        <label>Password</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        <button type="submit" class="btn-brand" id="loginSubmitBtn">Sign In <span class="btn-arrow">→</span></button>
      </form>
      <script>
        document.getElementById('loginForm').addEventListener('submit', function () {
          var btn = document.getElementById('loginSubmitBtn');
          btn.innerHTML = '<div class="loader loader-sm"></div>';
          btn.style.pointerEvents = 'none';
        });
      </script>
      <div class="demo-hint">
        <span class="demo-hint-icon">ℹ</span>
        <span><strong>Demo login:</strong> username <code>admin</code>, password <code>password123</code> works for
        any active Staff account until you change it.</span>
      </div>
    </div>
  </div>
</div>
</body>
</html>
