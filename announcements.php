<?php
$pageTitle = 'Announcements';
$crumb = 'Broadcast a notice to your field staff';
$active = 'announcements';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/notification_helper.php';

$u = current_staff();
if (!is_admin_or_super($u)) {
    echo '<div class="empty-state">Only Admin can send announcements.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$typeOptions = ['Info' => 1, 'Success' => 3, 'Warning' => 2, 'Danger' => 4];
$sentCount = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $typeName = $_POST['type'] ?? 'Info';
    $notificationTypeId = $typeOptions[$typeName] ?? 1;
    $branchFilter = (int)($_POST['branch'] ?? 0);
    $roleFilter = (int)($_POST['role'] ?? 0);

    if ($title === '' || $message === '') {
        $error = 'Title and message are required.';
    } else {
        $where = ['s.IsActive = 1'];
        $params = [];
        if ($branchFilter > 0) { $where[] = 's.BranchID = ?'; $params[] = $branchFilter; }
        if ($roleFilter > 0) { $where[] = 's.RoleID = ?'; $params[] = $roleFilter; }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $recipientStmt = $pdo->prepare("SELECT StaffID FROM Staff s $whereSql");
        $recipientStmt->execute($params);
        $recipientIds = array_map('intval', $recipientStmt->fetchAll(PDO::FETCH_COLUMN));

        $pdo->beginTransaction();
        try {
            foreach ($recipientIds as $staffId) {
                notify_staff($pdo, $staffId, $title, $message, $notificationTypeId, 'Announcement', null);
            }
            $pdo->commit();
            $sentCount = count($recipientIds);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('announcements.php failed: ' . $e->getMessage());
            $error = 'Failed to send announcement.';
        }
    }
}

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$roles = $pdo->query("SELECT RoleID, RoleName FROM Role_Master ORDER BY RoleName")->fetchAll();
?>

<?php if ($sentCount !== null): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Announcement sent to <?= $sentCount ?> staff member<?= $sentCount === 1 ? '' : 's' ?>.</div>
<?php endif; ?>
<?php if (isset($error)): ?>
  <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card-x">
  <form method="POST">
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">Title</label>
        <input type="text" class="form-control" name="title" maxlength="200" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Message</label>
        <textarea class="form-control" name="message" rows="4" maxlength="500" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
      </div>
      <div class="col-md-4">
        <label class="form-label">Severity</label>
        <select class="form-select" name="type">
          <?php foreach (array_keys($typeOptions) as $t): ?>
            <option value="<?= $t ?>" <?= ($_POST['type'] ?? 'Info') === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Branch</label>
        <select class="form-select" name="branch">
          <option value="">All Branches</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= $b['BranchID'] ?>" <?= (int)($_POST['branch'] ?? 0) === (int)$b['BranchID'] ? 'selected' : '' ?>><?= htmlspecialchars($b['BranchName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Role</label>
        <select class="form-select" name="role">
          <option value="">All Roles</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r['RoleID'] ?>" <?= (int)($_POST['role'] ?? 0) === (int)$r['RoleID'] ? 'selected' : '' ?>><?= htmlspecialchars($r['RoleName']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <button class="btn-navy" type="submit"><i class="bi bi-megaphone-fill"></i> Send Announcement</button>
      </div>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
