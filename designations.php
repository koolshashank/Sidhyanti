<?php
$pageTitle = 'Designations';
$crumb = 'Job functions staff are assigned, each under a Role';
$active = 'designations';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

if (!is_admin_or_super(current_staff())) {
    echo '<div class="empty-state">Only Admin can manage designations.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$designations = $pdo->query("
  SELECT d.DesignationID, d.DesignationName, r.RoleName,
         (SELECT COUNT(*) FROM Staff s WHERE s.DesignationID = d.DesignationID AND s.IsActive = 1) AS StaffCount
  FROM Designation_Master d
  LEFT JOIN Role_Master r ON d.RoleID = r.RoleID
  ORDER BY r.RoleName, d.DesignationName
")->fetchAll();
?>

<?php if (isset($_GET['created'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Designation created successfully.</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div style="color:var(--text-muted);font-size:13px;">
    Each designation belongs to one Role (which controls feature permissions) and can have its own
    <a href="mobile_menu_access.php">mobile menu visibility</a>.
  </div>
  <a href="designation_add.php" class="btn-navy">+ New Designation</a>
</div>

<div class="card-x">
  <table class="table-x">
    <tr><th>Designation</th><th>Role</th><th>Active Staff</th></tr>
    <?php foreach ($designations as $d): ?>
    <tr>
      <td style="font-weight:600;"><?= htmlspecialchars($d['DesignationName'] ?: '(unnamed)') ?></td>
      <td><?= htmlspecialchars($d['RoleName'] ?? '-') ?></td>
      <td><?= (int)$d['StaffCount'] ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$designations): ?><tr><td colspan="3"><div class="empty-state">No designations found.</div></td></tr><?php endif; ?>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
