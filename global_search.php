<?php
$pageTitle = 'Search Results';
$crumb = 'Members, loans and centers matching your search';
$active = '';
require_once __DIR__ . '/includes/header.php';

$q = trim($_GET['q'] ?? '');
$members = $loans = $centers = [];

if ($q !== '') {
    $like = "%$q%";

    $memberStmt = $pdo->prepare("
      SELECT TOP 15 m.MemberID, m.EnrollmentNo, m.FirstName, m.LastName, m.MobileNo, b.BranchName, c.CenterName
      FROM Member m
      LEFT JOIN Branch b ON m.BranchID = b.BranchID
      LEFT JOIN Center c ON m.CenterID = c.CenterID
      WHERE m.FirstName LIKE ? OR m.LastName LIKE ? OR m.MobileNo LIKE ? OR m.EnrollmentNo LIKE ?
      ORDER BY m.MemberID DESC
    ");
    $memberStmt->execute([$like, $like, $like, $like]);
    $members = $memberStmt->fetchAll();

    $loanStmt = $pdo->prepare("
      SELECT TOP 15 l.LoanID, l.LoanNo, m.FirstName, m.LastName, b.BranchName, stg.StageName,
             COALESCE(l.LoanAmountSanctioned, l.LoanAmountApplied) AS Amount
      FROM Loan l
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Branch b ON l.BranchID = b.BranchID
      LEFT JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
      WHERE l.LoanNo LIKE ? OR m.FirstName LIKE ? OR m.LastName LIKE ?
      ORDER BY l.LoanID DESC
    ");
    $loanStmt->execute([$like, $like, $like]);
    $loans = $loanStmt->fetchAll();

    $centerStmt = $pdo->prepare("
      SELECT TOP 15 c.CenterID, c.CenterName, b.BranchName, mgr.FullName AS ManagerName
      FROM Center c
      LEFT JOIN Branch b ON c.BranchID = b.BranchID
      LEFT JOIN Staff mgr ON c.ManagedByStaffID = mgr.StaffID
      WHERE c.CenterName LIKE ?
      ORDER BY c.CenterID DESC
    ");
    $centerStmt->execute([$like]);
    $centers = $centerStmt->fetchAll();
}

$totalResults = count($members) + count($loans) + count($centers);
?>
<?php if ($q === ''): ?>
  <div class="card-x empty-state">Type something in the search box above to look across members, loans and centers.</div>
<?php else: ?>

  <div class="card-x mb-3">
    <div style="font-size:13.5px;color:var(--text-muted);">
      <?= $totalResults ?> result<?= $totalResults === 1 ? '' : 's' ?> for "<strong><?= htmlspecialchars($q) ?></strong>"
    </div>
  </div>

  <div class="card-x mb-3">
    <div class="section-title">Members <span class="badge badge-secondary"><?= count($members) ?></span></div>
    <?php if ($members): ?>
      <div class="table-scroll">
        <table class="table-x">
          <tr><th>Enrollment No</th><th>Name</th><th>Mobile</th><th>Branch</th><th>Center</th><th></th></tr>
          <?php foreach ($members as $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['EnrollmentNo']) ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar sm" style="background:var(--navy);"><?= initials($m['FirstName'] . ' ' . $m['LastName']) ?></div>
                <a href="member_view.php?id=<?= $m['MemberID'] ?>"><?= htmlspecialchars($m['FirstName'] . ' ' . $m['LastName']) ?></a>
              </div>
            </td>
            <td><?= htmlspecialchars($m['MobileNo']) ?></td>
            <td><?= htmlspecialchars($m['BranchName'] ?? '-') ?></td>
            <td><?= htmlspecialchars($m['CenterName'] ?? '-') ?></td>
            <td><a href="member_view.php?id=<?= $m['MemberID'] ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state">No matching members.</div>
    <?php endif; ?>
  </div>

  <div class="card-x mb-3">
    <div class="section-title">Loans <span class="badge badge-secondary"><?= count($loans) ?></span></div>
    <?php if ($loans): ?>
      <div class="table-scroll">
        <table class="table-x">
          <tr><th>Loan No</th><th>Member</th><th>Branch</th><th>Stage</th><th>Amount</th><th></th></tr>
          <?php foreach ($loans as $l): ?>
          <tr>
            <td><?= htmlspecialchars($l['LoanNo']) ?></td>
            <td><?= htmlspecialchars($l['FirstName'] . ' ' . $l['LastName']) ?></td>
            <td><?= htmlspecialchars($l['BranchName'] ?? '-') ?></td>
            <td><?= stage_badge($l['StageName']) ?></td>
            <td style="font-weight:700;color:var(--navy);"><?= money($l['Amount']) ?></td>
            <td><a href="loan_view.php?id=<?= $l['LoanID'] ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state">No matching loans.</div>
    <?php endif; ?>
  </div>

  <div class="card-x">
    <div class="section-title">Centers <span class="badge badge-secondary"><?= count($centers) ?></span></div>
    <?php if ($centers): ?>
      <div class="table-scroll">
        <table class="table-x">
          <tr><th>Center</th><th>Branch</th><th>Manager</th><th></th></tr>
          <?php foreach ($centers as $c): ?>
          <tr>
            <td><strong><?= htmlspecialchars($c['CenterName']) ?></strong></td>
            <td><?= htmlspecialchars($c['BranchName'] ?? '-') ?></td>
            <td><?= htmlspecialchars($c['ManagerName'] ?? 'Unassigned') ?></td>
            <td><a href="members.php?center=<?= $c['CenterID'] ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View Members</a></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state">No matching centers.</div>
    <?php endif; ?>
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
