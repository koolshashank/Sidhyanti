<?php
$pageTitle = 'Leave Coverage';
$crumb = "Temporarily hand an employee's centers to someone else while they're on leave";
$active = 'leave_coverage';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/notification_helper.php';

$canManage = role_has_permission($pdo, current_staff()['RoleID'] ?? null, 'manage_center_leave_coverage');
if (!$canManage) {
    echo '<div class="empty-state">You do not have permission to manage leave coverage.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$error = '';

// Start a new coverage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_original_staff_id'])) {
    $originalStaffId = (int)$_POST['start_original_staff_id'];
    $coveringStaffId = (int)($_POST['start_covering_staff_id'] ?? 0);
    $centerIds = array_map('intval', $_POST['center_ids'] ?? []);
    $remark = trim($_POST['start_remark'] ?? '') ?: null;

    $covering = $pdo->prepare("SELECT StaffID, FullName, BranchID FROM Staff WHERE StaffID = ? AND IsActive = 1");
    $covering->execute([$coveringStaffId]);
    $covering = $covering->fetch();

    $original = $pdo->prepare("SELECT StaffID, FullName FROM Staff WHERE StaffID = ?");
    $original->execute([$originalStaffId]);
    $original = $original->fetch();

    if (!$original) {
        $error = 'Employee not found.';
    } elseif (!$centerIds) {
        $error = 'Please select at least one center to cover.';
    } elseif (!$covering) {
        $error = 'Please select an active covering employee.';
    } elseif ($coveringStaffId === $originalStaffId) {
        $error = 'The covering employee must be different from the employee on leave.';
    } else {
        $placeholders = implode(',', array_fill(0, count($centerIds), '?'));
        $centers = $pdo->prepare("
            SELECT CenterID, CenterName, BranchID, ManagedByStaffID FROM Center
            WHERE CenterID IN ($placeholders) AND ManagedByStaffID = ? AND IsActive = 1
        ");
        $centers->execute(array_merge($centerIds, [$originalStaffId]));
        $centers = $centers->fetchAll();

        $branches = array_unique(array_column($centers, 'BranchID'));

        if (count($centers) !== count($centerIds)) {
            $error = 'One or more selected centers are no longer managed by this employee — please refresh and try again.';
        } elseif (count($branches) > 1) {
            $error = 'Selected centers span more than one branch — this is not currently supported.';
        } elseif ((int)$covering['BranchID'] !== (int)$branches[0]) {
            $error = 'Please select a covering employee from the same branch as the selected centers.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO Center_Leave_Coverage (OriginalStaffID, CoveringStaffID, Status, Remark, StartedByStaffID)
                    VALUES (?,?, 'Active', ?, ?)
                ")->execute([$originalStaffId, $coveringStaffId, $remark, current_staff()['StaffID']]);
                $coverageId = $pdo->lastInsertId();

                $updMember = $pdo->prepare("UPDATE Member SET AssignedToStaffID=? WHERE CenterID=?");
                $updLoan = $pdo->prepare("
                    UPDATE l SET l.LoanOfficerStaffID=?
                    FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
                    WHERE l.CenterID=? AND stg.IsFinal = 0
                ");
                $updSheet = $pdo->prepare("UPDATE Daily_Collection_Sheet SET OfficerStaffID=?, UpdatedAt=GETDATE() WHERE CenterID=? AND CollectionStatus IS NULL");
                $insDetail = $pdo->prepare("
                    INSERT INTO Center_Leave_Coverage_Detail (CoverageID, CenterID, MembersMovedCount, LoansMovedCount) VALUES (?,?,?,?)
                ");

                foreach ($centers as $c) {
                    $pdo->prepare("UPDATE Center SET ManagedByStaffID=? WHERE CenterID=?")->execute([$coveringStaffId, $c['CenterID']]);

                    $updMember->execute([$coveringStaffId, $c['CenterID']]);
                    $membersMoved = $updMember->rowCount();

                    $updLoan->execute([$coveringStaffId, $c['CenterID']]);
                    $loansMoved = $updLoan->rowCount();

                    $updSheet->execute([$coveringStaffId, $c['CenterID']]);

                    $insDetail->execute([$coverageId, $c['CenterID'], $membersMoved, $loansMoved]);
                }

                $centerNames = implode(', ', array_column($centers, 'CenterName'));
                $msg = $original['FullName'] . "'s centers (" . $centerNames . ') are now covered by ' . $covering['FullName'] . ' while on leave.';
                $notifyIds = array_unique(array_filter([$originalStaffId, $coveringStaffId], fn($sid) => (int)$sid !== (int)current_staff()['StaffID']));
                if ($notifyIds) {
                    foreach ($notifyIds as $sid) {
                        notify_staff($pdo, $sid, 'Leave Coverage Started', $msg, 2, 'LeaveCoverage', $coverageId);
                    }
                }

                $pdo->commit();
                header('Location: leave_coverage.php?started=1');
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Could not start coverage. Please try again.';
            }
        }
    }
}

// Revert an existing coverage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revert_coverage_id'])) {
    $coverageId = (int)$_POST['revert_coverage_id'];
    $coverage = $pdo->prepare("SELECT * FROM Center_Leave_Coverage WHERE CoverageID = ? AND Status = 'Active'");
    $coverage->execute([$coverageId]);
    $coverage = $coverage->fetch();

    if (!$coverage) {
        $error = 'This coverage is not active (already reverted?).';
    } else {
        $details = $pdo->prepare("SELECT * FROM Center_Leave_Coverage_Detail WHERE CoverageID = ?");
        $details->execute([$coverageId]);
        $details = $details->fetchAll();

        $pdo->beginTransaction();
        try {
            $updMember = $pdo->prepare("UPDATE Member SET AssignedToStaffID=? WHERE CenterID=?");
            $updLoan = $pdo->prepare("
                UPDATE l SET l.LoanOfficerStaffID=?
                FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
                WHERE l.CenterID=? AND stg.IsFinal = 0
            ");
            $updSheet = $pdo->prepare("UPDATE Daily_Collection_Sheet SET OfficerStaffID=?, UpdatedAt=GETDATE() WHERE CenterID=? AND CollectionStatus IS NULL");

            foreach ($details as $d) {
                $pdo->prepare("UPDATE Center SET ManagedByStaffID=? WHERE CenterID=?")->execute([$coverage['OriginalStaffID'], $d['CenterID']]);
                $updMember->execute([$coverage['OriginalStaffID'], $d['CenterID']]);
                $updLoan->execute([$coverage['OriginalStaffID'], $d['CenterID']]);
                $updSheet->execute([$coverage['OriginalStaffID'], $d['CenterID']]);
            }

            $pdo->prepare("UPDATE Center_Leave_Coverage SET Status='Reverted', RevertedByStaffID=?, RevertedAt=GETDATE() WHERE CoverageID=?")
                ->execute([current_staff()['StaffID'], $coverageId]);

            $names = $pdo->prepare("SELECT FullName FROM Staff WHERE StaffID IN (?, ?)");
            $names->execute([$coverage['OriginalStaffID'], $coverage['CoveringStaffID']]);
            $names = $names->fetchAll(PDO::FETCH_COLUMN);
            $msg = 'Leave coverage ended — centers are back with ' . ($names[0] ?? 'the original employee') . '.';
            $notifyIds = array_unique(array_filter([$coverage['OriginalStaffID'], $coverage['CoveringStaffID']], fn($sid) => (int)$sid !== (int)current_staff()['StaffID']));
            if ($notifyIds) {
                foreach ($notifyIds as $sid) {
                    notify_staff($pdo, $sid, 'Leave Coverage Ended', $msg, 3, 'LeaveCoverage', $coverageId);
                }
            }

            $pdo->commit();
            header('Location: leave_coverage.php?reverted=1');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Could not revert this coverage. Please try again.';
        }
    }
}

$selectedStaffId = (int)($_GET['staff_id'] ?? 0);
$onLeaveCandidates = $pdo->query("
    SELECT DISTINCT s.StaffID, s.FullName, s.BranchID
    FROM Staff s JOIN Center c ON c.ManagedByStaffID = s.StaffID
    WHERE s.IsActive = 1 AND c.IsActive = 1 ORDER BY s.FullName
")->fetchAll();

$selectedStaffCenters = [];
$sameBranchStaff = [];
if ($selectedStaffId) {
    $selectedStaffCenters = $pdo->prepare("
        SELECT CenterID, CenterName, BranchID,
            (SELECT COUNT(*) FROM Member m WHERE m.CenterID = Center.CenterID) AS member_count
        FROM Center WHERE ManagedByStaffID = ? AND IsActive = 1 ORDER BY CenterName
    ");
    $selectedStaffCenters->execute([$selectedStaffId]);
    $selectedStaffCenters = $selectedStaffCenters->fetchAll();

    if ($selectedStaffCenters) {
        $branchId = $selectedStaffCenters[0]['BranchID'];
        $sameBranchStaff = $pdo->prepare("SELECT StaffID, FullName FROM Staff WHERE BranchID = ? AND IsActive = 1 AND StaffID <> ? ORDER BY FullName");
        $sameBranchStaff->execute([$branchId, $selectedStaffId]);
        $sameBranchStaff = $sameBranchStaff->fetchAll();
    }
}

$activeCoverage = $pdo->query("
    SELECT cov.*, os.FullName AS OriginalName, cs.FullName AS CoveringName, sb.FullName AS StartedByName
    FROM Center_Leave_Coverage cov
    LEFT JOIN Staff os ON cov.OriginalStaffID = os.StaffID
    LEFT JOIN Staff cs ON cov.CoveringStaffID = cs.StaffID
    LEFT JOIN Staff sb ON cov.StartedByStaffID = sb.StaffID
    WHERE cov.Status = 'Active' ORDER BY cov.StartedAt DESC
")->fetchAll();

$historyCoverage = $pdo->query("
    SELECT cov.*, os.FullName AS OriginalName, cs.FullName AS CoveringName, rb.FullName AS RevertedByName
    FROM Center_Leave_Coverage cov
    LEFT JOIN Staff os ON cov.OriginalStaffID = os.StaffID
    LEFT JOIN Staff cs ON cov.CoveringStaffID = cs.StaffID
    LEFT JOIN Staff rb ON cov.RevertedByStaffID = rb.StaffID
    WHERE cov.Status = 'Reverted' ORDER BY cov.RevertedAt DESC
")->fetchAll();

$coverageIds = array_merge(array_column($activeCoverage, 'CoverageID'), array_column($historyCoverage, 'CoverageID'));
$centersByCoverage = [];
if ($coverageIds) {
    $placeholders = implode(',', array_fill(0, count($coverageIds), '?'));
    $d = $pdo->prepare("
        SELECT d.CoverageID, d.MembersMovedCount, d.LoansMovedCount, c.CenterName
        FROM Center_Leave_Coverage_Detail d JOIN Center c ON d.CenterID = c.CenterID
        WHERE d.CoverageID IN ($placeholders)
    ");
    $d->execute($coverageIds);
    foreach ($d->fetchAll() as $row) {
        $centersByCoverage[$row['CoverageID']][] = $row;
    }
}
?>

<?php if ($error): ?>
  <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div>
<?php elseif (isset($_GET['started'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Coverage started successfully.</div>
<?php elseif (isset($_GET['reverted'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Coverage reverted — centers are back with the original employee.</div>
<?php endif; ?>

<div class="card-x mb-3">
  <div class="section-title">Start New Coverage</div>
  <form method="GET" class="row g-3 align-items-end mb-3">
    <div class="col-md-6">
      <label class="form-label">Employee on Leave</label>
      <select class="form-select" name="staff_id" onchange="this.form.submit()">
        <option value="">Select employee</option>
        <?php foreach ($onLeaveCandidates as $s): ?>
          <option value="<?= $s['StaffID'] ?>" <?= $selectedStaffId===(int)$s['StaffID']?'selected':'' ?>><?= htmlspecialchars($s['FullName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <?php if ($selectedStaffId && $selectedStaffCenters): ?>
  <form method="POST">
    <input type="hidden" name="start_original_staff_id" value="<?= $selectedStaffId ?>">
    <div class="form-section-label"><span class="dot"><i class="bi bi-houses-fill"></i></span> Centers to Cover</div>
    <div class="table-scroll mb-3">
      <table class="table-x">
        <tr><th style="width:36px;"><input type="checkbox" id="checkAllCenters" checked></th><th>Center</th><th>Members</th></tr>
        <?php foreach ($selectedStaffCenters as $c): ?>
        <tr>
          <td><input type="checkbox" class="center-checkbox" name="center_ids[]" value="<?= $c['CenterID'] ?>" checked></td>
          <td><?= htmlspecialchars($c['CenterName']) ?></td>
          <td><?= (int)$c['member_count'] ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Covering Employee <span class="required-mark">*</span></label>
        <select class="form-select" name="start_covering_staff_id" required>
          <option value="">Select employee</option>
          <?php foreach ($sameBranchStaff as $s): ?><option value="<?= $s['StaffID'] ?>"><?= htmlspecialchars($s['FullName']) ?></option><?php endforeach; ?>
        </select>
        <?php if (!$sameBranchStaff): ?><div style="font-size:11.5px;color:var(--danger);margin-top:4px;">No other active employees found in this branch.</div><?php endif; ?>
      </div>
      <div class="col-md-6">
        <label class="form-label">Reason (optional)</label>
        <input class="form-control" name="start_remark" placeholder="e.g. On sick leave till 15th">
      </div>
    </div>
    <div class="mt-3">
      <button type="submit" class="btn-navy" <?= !$sameBranchStaff?'disabled':'' ?> onclick="return confirm('Start leave coverage? Selected centers, their members and active loans will move to the covering employee until reverted.');">Start Coverage</button>
    </div>
  </form>
  <script>
    document.getElementById('checkAllCenters').addEventListener('change', function () {
      document.querySelectorAll('.center-checkbox').forEach(cb => cb.checked = this.checked);
    });
  </script>
  <?php elseif ($selectedStaffId): ?>
    <div class="empty-state">This employee doesn't currently manage any active center.</div>
  <?php endif; ?>
</div>

<div class="card-x mb-3">
  <div class="section-title">Active Coverage (<?= count($activeCoverage) ?>)</div>
  <?php if ($activeCoverage): ?>
  <table class="table-x">
    <tr><th>Original Employee</th><th>Covering Employee</th><th>Centers</th><th>Started</th><th>Reason</th><th></th></tr>
    <?php foreach ($activeCoverage as $cov): $rows = $centersByCoverage[$cov['CoverageID']] ?? []; ?>
    <tr>
      <td><?= htmlspecialchars($cov['OriginalName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($cov['CoveringName'] ?? '-') ?></td>
      <td style="font-size:12px;">
        <?= htmlspecialchars(implode(', ', array_column($rows, 'CenterName'))) ?>
        <div style="color:var(--text-muted);"><?= array_sum(array_column($rows,'MembersMovedCount')) ?> members / <?= array_sum(array_column($rows,'LoansMovedCount')) ?> loans</div>
      </td>
      <td style="font-size:12px;"><?= fdate($cov['StartedAt']) ?><br><span style="color:var(--text-muted);">by <?= htmlspecialchars($cov['StartedByName'] ?? '-') ?></span></td>
      <td style="font-size:12px;"><?= htmlspecialchars($cov['Remark'] ?: '-') ?></td>
      <td>
        <form method="POST" onsubmit="return confirm('Revert this coverage? Centers, members and active loans will move back to the original employee.');">
          <input type="hidden" name="revert_coverage_id" value="<?= $cov['CoverageID'] ?>">
          <button class="btn-outline-x" style="font-size:12px;padding:5px 12px;color:var(--danger);border-color:var(--danger);">Revert</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
    <div class="empty-state">No centers are currently under leave coverage.</div>
  <?php endif; ?>
</div>

<div class="card-x">
  <div class="section-title">History (<?= count($historyCoverage) ?>)</div>
  <?php if ($historyCoverage): ?>
  <table class="table-x">
    <tr><th>Original Employee</th><th>Covering Employee</th><th>Centers</th><th>Started</th><th>Reverted</th></tr>
    <?php foreach ($historyCoverage as $cov): $rows = $centersByCoverage[$cov['CoverageID']] ?? []; ?>
    <tr>
      <td><?= htmlspecialchars($cov['OriginalName'] ?? '-') ?></td>
      <td><?= htmlspecialchars($cov['CoveringName'] ?? '-') ?></td>
      <td style="font-size:12px;"><?= htmlspecialchars(implode(', ', array_column($rows, 'CenterName'))) ?></td>
      <td style="font-size:12px;"><?= fdate($cov['StartedAt']) ?></td>
      <td style="font-size:12px;"><?= fdate($cov['RevertedAt']) ?><br><span style="color:var(--text-muted);">by <?= htmlspecialchars($cov['RevertedByName'] ?? '-') ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?>
    <div class="empty-state">No past coverage yet.</div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
