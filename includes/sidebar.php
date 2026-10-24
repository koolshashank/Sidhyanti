<?php
$active = $active ?? '';
require_once __DIR__ . '/permission_helper.php';
$sidebarCanManageLeaveCoverage = role_has_permission($pdo, current_staff()['RoleID'] ?? null, 'manage_center_leave_coverage');
?>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="logo-mark"><img src="assets/img/logo.png" alt="RDMCS Logo"></div>
    <span>Sidhyanti CRM</span>
    <button type="button" class="sidebar-close" id="sidebarCloseBtn" aria-label="Close menu">&times;</button>
  </div>
  <nav>
    <a class="nav-link <?= $active==='dashboard'?'active':'' ?>" href="dashboard.php"><span class="ic"><i class="bi bi-bar-chart-fill"></i></span> Dashboard</a>


    <div class="nav-section">Operations</div>
    <a class="nav-link <?= $active==='staff'?'active':'' ?>" href="staff.php"><span class="ic"><i class="bi bi-person-fill"></i></span> Staff</a>
    <a class="nav-link <?= $active==='branches'?'active':'' ?>" href="branches.php"><span class="ic"><i class="bi bi-building"></i></span> Branches</a>
    <a class="nav-link <?= $active==='collection'?'active':'' ?>" href="collection.php"><span class="ic"><i class="bi bi-cash-coin"></i></span> Collection</a>
    <a class="nav-link <?= $active==='meeting_attendance'?'active':'' ?>" href="meeting_attendance.php"><span class="ic"><i class="bi bi-receipt"></i></span> Meeting Attendance</a>
    <a class="nav-link <?= $active==='centers'?'active':'' ?>" href="centers.php"><span class="ic"><i class="bi bi-houses-fill"></i></span> Centers</a>   
    <a class="nav-link <?= $active==='schemes'?'active':'' ?>" href="schemes.php"><span class="ic"><i class="bi bi-box-seam-fill"></i></span> Loan Schemes</a>
    <?php if (role_has_permission($pdo, current_staff()['RoleID'] ?? null, 'manage_reloan_eligibility_rules')): ?>
    <a class="nav-link <?= $active==='reloan_eligibility_rules'?'active':'' ?>" href="reloan_eligibility_rules.php"><span class="ic"><i class="bi bi-graph-up-arrow"></i></span> Reloan Eligibility</a>
    <?php endif; ?>

    <div class="nav-section">Sales &amp; Members</div>
    <a class="nav-link <?= $active==='members'?'active':'' ?>" href="members.php"><span class="ic"><i class="bi bi-people-fill"></i></span> Members / Leads</a>
    <a class="nav-link <?= $active==='loans'?'active':'' ?>" href="loans.php"><span class="ic"><i class="bi bi-briefcase-fill"></i></span> Loan Pipeline</a>
    <?php if (role_has_permission($pdo, current_staff()['RoleID'] ?? null, 'approve_loan_proposal') || role_has_permission($pdo, current_staff()['RoleID'] ?? null, 'approve_loan_preclose')): ?>
    <a class="nav-link <?= $active==='loan_approvals'?'active':'' ?>" href="loan_approvals.php"><span class="ic"><i class="bi bi-check-circle-fill"></i></span> Loan Approvals</a>
    <?php endif; ?>
    <a class="nav-link <?= $active==='grt'?'active':'' ?>" href="grt.php"><span class="ic"><i class="bi bi-clipboard-check"></i></span> GRT</a>
    <a class="nav-link <?= $active==='cgt'?'active':'' ?>" href="cgt.php"><span class="ic"><i class="bi bi-houses-fill"></i></span> CGT</a>
    <a class="nav-link <?= $active==='tvr'?'active':'' ?>" href="tvr.php"><span class="ic"><i class="bi bi-telephone-fill"></i></span> TVR Records</a>
    <a class="nav-link <?= $active==='interactions'?'active':'' ?>" href="interactions.php"><span class="ic"><i class="bi bi-telephone-fill"></i></span> Interactions</a>
    <a class="nav-link <?= $active==='tasks'?'active':'' ?>" href="tasks.php"><span class="ic"><i class="bi bi-check-circle-fill"></i></span> Tasks &amp; Follow-ups</a>
    <a class="nav-link <?= $active==='death_entries'?'active':'' ?>" href="death_entries.php"><span class="ic"><i class="bi bi-flower1"></i></span> Death Entries</a>
    <?php if ($sidebarCanManageLeaveCoverage): ?>
    <a class="nav-link <?= $active==='leave_coverage'?'active':'' ?>" href="leave_coverage.php"><span class="ic"><i class="bi bi-briefcase"></i></span> Leave Coverage</a>
    <?php endif; ?>



    <div class="nav-section">Insights</div>
    <a class="nav-link <?= $active==='portfolio'?'active':'' ?>" href="portfolio.php"><span class="ic"><i class="bi bi-heart-pulse-fill"></i></span> Portfolio Health</a>
    <a class="nav-link <?= $active==='reports'?'active':'' ?>" href="reports.php"><span class="ic"><i class="bi bi-graph-up-arrow"></i></span> Reports</a>
    <a class="nav-link <?= $active==='audit'?'active':'' ?>" href="audit_log.php"><span class="ic"><i class="bi bi-journal-text"></i></span> Audit Log</a>
    <a class="nav-link <?= $active==='notifications'?'active':'' ?>" href="notifications.php"><span class="ic"><i class="bi bi-bell-fill"></i></span> Notifications</a>
    <?php // Staff Tracking -- temporarily disabled (if (false) instead of
          // deleting, since a /* */ comment can't safely span a '?>' below). ?>
    <?php if (false && is_admin_or_super(current_staff())): ?>
    <a class="nav-link <?= $active==='staff_tracking'?'active':'' ?>" href="staff_tracking.php"><span class="ic"><i class="bi bi-broadcast"></i></span> Staff Tracking</a>
    <?php endif; ?>

    <?php if (is_admin_or_super(current_staff())): ?>
    <div class="nav-section">Settings</div>
    <a class="nav-link <?= $active==='role_permissions'?'active':'' ?>" href="role_permissions.php"><span class="ic"><i class="bi bi-shield-lock-fill"></i></span> Role Permissions</a>
    <a class="nav-link <?= $active==='designations'?'active':'' ?>" href="designations.php"><span class="ic"><i class="bi bi-tag-fill"></i></span> Designations</a>
    <a class="nav-link <?= $active==='mobile_menu_access'?'active':'' ?>" href="mobile_menu_access.php"><span class="ic"><i class="bi bi-phone-fill"></i></span> Mobile Menu Access</a>
    <a class="nav-link <?= $active==='am_branch_mapping'?'active':'' ?>" href="am_branch_mapping.php"><span class="ic"><i class="bi bi-map-fill"></i></span> AM Branch Mapping</a>
    <a class="nav-link <?= $active==='app_settings'?'active':'' ?>" href="app_settings.php"><span class="ic"><i class="bi bi-gear-fill"></i></span> App Settings</a>
    <a class="nav-link <?= $active==='device_lock_settings'?'active':'' ?>" href="device_lock_settings.php"><span class="ic"><i class="bi bi-lock-fill"></i></span> Device Lock Settings</a>
    <a class="nav-link <?= $active==='staff_devices'?'active':'' ?>" href="staff_devices.php"><span class="ic"><i class="bi bi-phone-slash"></i></span> Registered Devices</a>
    <?php endif; ?>

    <?php if ((current_staff()['RoleName'] ?? '') === 'SuperAdmin'): ?>
    <div class="nav-section">SuperAdmin</div>
    <a class="nav-link <?= $active==='billing'?'active':'' ?>" href="billing.php"><span class="ic"><i class="bi bi-receipt"></i></span> Billing</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-foot">Sidhyanti CRM v2.0<br>Clean Database Edition</div>
</aside>
