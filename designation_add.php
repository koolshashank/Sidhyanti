<?php
$pageTitle = 'New Designation';
$crumb = 'Define a new job function';
$active = 'designations';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can manage designations.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$roles = $pdo->query("SELECT RoleID, RoleName FROM Role_Master ORDER BY RoleName")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['designation_name'] ?? '');
    $roleId = $_POST['role_id'] ?? '';
    if ($name === '' || !$roleId) {
        $error = 'Designation Name and Role are required.';
    } else {
        try {
            $pdo->prepare("INSERT INTO Designation_Master (DesignationName, RoleID) VALUES (?, ?)")
                ->execute([$name, $roleId]);
            header('Location: designations.php?created=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Could not create designation — the name may already be in use.';
        }
    }
}
?>
<div class="card-x" style="max-width:640px;">
  <div class="form-card-head">
    <div class="form-card-icon"><i class="bi bi-tag-fill"></i></div>
    <div>
      <h5>Designation Details</h5>
      <p>A designation is the job function shown against a staff member (e.g. "CM", "Field Officer") --
         it's what mobile_menu_access.php uses to decide which mobile screens they can see.</p>
    </div>
  </div>

  <?php if ($error): ?><div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:18px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST">
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">Designation Name <span class="required-mark">*</span></label>
        <input class="form-control" name="designation_name" required value="<?= htmlspecialchars($_POST['designation_name'] ?? '') ?>" placeholder="e.g. Cluster Manager">
      </div>
      <div class="col-12">
        <label class="form-label">Role <span class="required-mark">*</span></label>
        <select class="form-select" name="role_id" required>
          <option value="">Select Role</option>
          <?php foreach ($roles as $r): ?><option value="<?= $r['RoleID'] ?>"><?= htmlspecialchars($r['RoleName']) ?></option><?php endforeach; ?>
        </select>
        <div style="font-size:11.5px;color:var(--text-muted);margin-top:5px;">Controls feature permissions (Role Permissions page) -- pick whichever existing Role best matches what this designation is allowed to do.</div>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit" class="btn-navy"><i class="bi bi-plus-lg"></i> Create Designation</button>
      <a href="designations.php" class="btn-outline-x">Cancel</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
