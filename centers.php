<?php
$pageTitle = 'Centers';
$crumb = 'JLG center groups under each branch';
$active = 'centers';
$extraHead = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';

$dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$canShiftMeeting = role_has_permission($pdo, current_staff()['RoleID'] ?? null, 'shift_center_meeting');
$canTransferCenter = role_has_permission($pdo, current_staff()['RoleID'] ?? null, 'transfer_center');
// shift_due_date_to_weekday() lives in includes/functions.php -- shared with
// member_view.php's "Transfer Center" action, which realigns dates the same way.

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['center_id'])) {
    $centerCode = trim($_POST['center_code'] ?? '');
    $centerName = trim($_POST['center_name'] ?? '');
    if ($centerCode === '' || $centerName === '' || !($_POST['branch_id'] ?? '')) {
        $error = 'Center Name, Center Code and Branch are required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE Center SET
                CenterCode=?, CenterName=?, BranchID=?, Address=?, MeetingDay=?, MeetingTime=?, ManagedByStaffID=?, IsActive=?, DissolvedDate=?,
                ContactPerson=?, ContactNo=?, CenterPlace=?, Latitude=?, Longitude=?
                WHERE CenterID=?");
            $stmt->execute([
                $centerCode, $centerName, $_POST['branch_id'], $_POST['address'] ?: null,
                $_POST['meeting_day'] ?: null, trim($_POST['meeting_time'] ?? '') ?: null,
                $_POST['managed_by'] ?: null, isset($_POST['is_active']) ? 1 : 0,
                $_POST['dissolved_date'] ?: null,
                trim($_POST['contact_person'] ?? '') ?: null, trim($_POST['contact_no'] ?? '') ?: null,
                trim($_POST['center_place'] ?? '') ?: null, trim($_POST['latitude'] ?? '') ?: null, trim($_POST['longitude'] ?? '') ?: null,
                $_POST['center_id'],
            ]);
            $qsArr = $_GET;
            $qsArr['updated'] = 1;
            header('Location: centers.php?' . http_build_query($qsArr));
            exit;
        } catch (PDOException $e) {
            $error = 'Could not update center — the center code may already be in use.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shift_meeting_center_id'])) {
    if (!$canShiftMeeting) {
        $error = 'You do not have permission to shift a center\'s meeting.';
    } else {
        $shiftCenterId = (int)$_POST['shift_meeting_center_id'];
        $newDay = (int)($_POST['shift_new_day'] ?? 0);
        $newTime = trim($_POST['shift_new_time'] ?? '') ?: null;
        $remark = trim($_POST['shift_remark'] ?? '') ?: null;

        if ($newDay < 1 || $newDay > 7) {
            $error = 'Please select a valid meeting day.';
        } else {
            $centerRow = $pdo->prepare("SELECT CenterID, MeetingDay, MeetingTime, ManagedByStaffID FROM Center WHERE CenterID = ?");
            $centerRow->execute([$shiftCenterId]);
            $centerRow = $centerRow->fetch();

            if (!$centerRow) {
                $error = 'Center not found.';
            } else {
                $oldDay = $centerRow['MeetingDay'];
                $oldDayIsNumeric = $oldDay !== null && ctype_digit((string)$oldDay) && (int)$oldDay >= 1 && (int)$oldDay <= 7;

                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE Center SET MeetingDay=?, MeetingTime=? WHERE CenterID=?")
                        ->execute([$newDay, $newTime, $shiftCenterId]);

                    $shiftedCount = 0;
                    $affectedLoanIds = [];
                    $loans = [];

                    // Only realign existing schedule dates if we know what weekday they were
                    // originally aligned to, and the day is actually changing.
                    if ($oldDayIsNumeric && (int)$oldDay !== $newDay) {
                        $loans = $pdo->prepare("
                            SELECT l.LoanID, l.LoanOfficerStaffID FROM Loan l
                            JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
                            WHERE l.CenterID = ? AND stg.IsFinal = 0
                        ");
                        $loans->execute([$shiftCenterId]);
                        $loans = $loans->fetchAll();

                        $scheduleStmt = $pdo->prepare("
                            SELECT ScheduleID, DueDate FROM Repayment_Schedule
                            WHERE LoanID = ? AND IsPaid = 0 AND DueDate >= CAST(GETDATE() AS DATE)
                        ");
                        $updateScheduleStmt = $pdo->prepare("UPDATE Repayment_Schedule SET DueDate=? WHERE ScheduleID=?");
                        $updateSheetStmt = $pdo->prepare("
                            UPDATE Daily_Collection_Sheet SET CollectionDueDate=?, UpdatedAt=GETDATE()
                            WHERE ScheduleID=? AND CollectionStatus IS NULL
                        ");

                        foreach ($loans as $loan) {
                            $scheduleStmt->execute([$loan['LoanID']]);
                            $rows = $scheduleStmt->fetchAll();
                            if (!$rows) {
                                continue;
                            }
                            foreach ($rows as $r) {
                                $newDue = shift_due_date_to_weekday($r['DueDate'], $newDay);
                                if ($newDue !== $r['DueDate']) {
                                    $updateScheduleStmt->execute([$newDue, $r['ScheduleID']]);
                                    $updateSheetStmt->execute([$newDue, $r['ScheduleID']]);
                                    $shiftedCount++;
                                }
                            }
                            $affectedLoanIds[] = $loan['LoanID'];
                        }
                    }

                    $pdo->prepare("
                        INSERT INTO Center_Meeting_Shift_History
                          (CenterID, OldMeetingDay, NewMeetingDay, OldMeetingTime, NewMeetingTime, SchedulesShiftedCount, LoansAffectedCount, Remark, ShiftedByStaffID)
                        VALUES (?,?,?,?,?,?,?,?,?)
                    ")->execute([
                        $shiftCenterId, $oldDay, $newDay, $centerRow['MeetingTime'], $newTime,
                        $shiftedCount, count($affectedLoanIds), $remark, current_staff()['StaffID'],
                    ]);

                    // Notify the center manager and the loan officers whose active loans in
                    // this center were just affected, so nobody shows up on the old day.
                    $notifyStaffIds = [];
                    if ($centerRow['ManagedByStaffID']) {
                        $notifyStaffIds[] = (int)$centerRow['ManagedByStaffID'];
                    }
                    foreach ($loans as $loan) {
                        if ($loan['LoanOfficerStaffID']) {
                            $notifyStaffIds[] = (int)$loan['LoanOfficerStaffID'];
                        }
                    }
                    $notifyStaffIds = array_unique(array_diff($notifyStaffIds, [(int)current_staff()['StaffID']]));
                    if ($notifyStaffIds) {
                        $centerName = $pdo->prepare("SELECT CenterName FROM Center WHERE CenterID=?");
                        $centerName->execute([$shiftCenterId]);
                        $centerName = $centerName->fetchColumn();
                        $msg = 'Meeting for ' . $centerName . ' has moved to ' . ($dayNames[$newDay] ?? $newDay)
                            . ($newTime ? ' at ' . $newTime : '') . '. ' . $shiftedCount . ' upcoming repayment date(s) updated accordingly.';
                        $notifyIns = $pdo->prepare("
                            INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
                            VALUES (?, 'Center Meeting Shifted', ?, 1, 'Center', ?, 0, GETDATE())
                        ");
                        foreach ($notifyStaffIds as $sid) {
                            $notifyIns->execute([$sid, $msg, $shiftCenterId]);
                        }
                    }

                    $pdo->commit();
                    $qsArr = $_GET;
                    $qsArr['shifted'] = $shiftedCount;
                    header('Location: centers.php?' . http_build_query($qsArr));
                    exit;
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $error = 'Could not shift the meeting. Please try again.';
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_center_id'])) {
    if (!$canTransferCenter) {
        $error = 'You do not have permission to transfer a center.';
    } else {
        $transferCenterId = (int)$_POST['transfer_center_id'];
        $newStaffId = (int)($_POST['transfer_new_staff_id'] ?? 0);
        $transferRemark = trim($_POST['transfer_remark'] ?? '') ?: null;

        $centerRow = $pdo->prepare("SELECT CenterID, CenterName, BranchID, ManagedByStaffID FROM Center WHERE CenterID = ?");
        $centerRow->execute([$transferCenterId]);
        $centerRow = $centerRow->fetch();

        $newStaff = $pdo->prepare("SELECT StaffID, FullName, BranchID FROM Staff WHERE StaffID = ? AND IsActive = 1");
        $newStaff->execute([$newStaffId]);
        $newStaff = $newStaff->fetch();

        $oldStaffName = 'Unassigned';
        if ($centerRow && $centerRow['ManagedByStaffID']) {
            $oldStaffNameStmt = $pdo->prepare("SELECT FullName FROM Staff WHERE StaffID = ?");
            $oldStaffNameStmt->execute([$centerRow['ManagedByStaffID']]);
            $oldStaffName = $oldStaffNameStmt->fetchColumn() ?: 'Unassigned';
        }

        if (!$centerRow) {
            $error = 'Center not found.';
        } elseif (!$newStaff || (int)$newStaff['BranchID'] !== (int)$centerRow['BranchID']) {
            $error = 'Please select an active employee from the same branch as this center.';
        } elseif ((int)$centerRow['ManagedByStaffID'] === $newStaffId) {
            $error = 'This center is already managed by that employee.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE Center SET ManagedByStaffID=? WHERE CenterID=?")->execute([$newStaffId, $transferCenterId]);

                $memberStmt = $pdo->prepare("UPDATE Member SET AssignedToStaffID=? WHERE CenterID=?");
                $memberStmt->execute([$newStaffId, $transferCenterId]);
                $membersMoved = $memberStmt->rowCount();

                $loanStmt = $pdo->prepare("
                    UPDATE l SET l.LoanOfficerStaffID=?
                    FROM Loan l JOIN Loan_Stage_Master stg ON l.CurrentStageID = stg.StageID
                    WHERE l.CenterID=? AND stg.IsFinal = 0
                ");
                $loanStmt->execute([$newStaffId, $transferCenterId]);
                $loansMoved = $loanStmt->rowCount();

                $pdo->prepare("
                    UPDATE Daily_Collection_Sheet SET OfficerStaffID=?, UpdatedAt=GETDATE()
                    WHERE CenterID=? AND CollectionStatus IS NULL
                ")->execute([$newStaffId, $transferCenterId]);

                $pdo->prepare("
                    INSERT INTO Center_Transfer_History (CenterID, OldStaffID, NewStaffID, MembersTransferredCount, LoansTransferredCount, Remark, TransferredByStaffID)
                    VALUES (?,?,?,?,?,?,?)
                ")->execute([
                    $transferCenterId, $centerRow['ManagedByStaffID'], $newStaffId, $membersMoved, $loansMoved,
                    $transferRemark, current_staff()['StaffID'],
                ]);

                $notifyIds = array_unique(array_filter([$centerRow['ManagedByStaffID'], $newStaffId], fn($id) => $id && (int)$id !== (int)current_staff()['StaffID']));
                if ($notifyIds) {
                    $msg = 'Center ' . $centerRow['CenterName'] . ' has been transferred from '
                        . $oldStaffName . ' to ' . $newStaff['FullName']
                        . '. ' . $membersMoved . ' member(s) and ' . $loansMoved . ' active loan(s) moved with it.';
                    $notifyIns = $pdo->prepare("
                        INSERT INTO Notification (StaffID, Title, Message, NotificationTypeID, RelatedEntityType, RelatedEntityID, IsRead, CreatedAt)
                        VALUES (?, 'Center Transferred', ?, 1, 'Center', ?, 0, GETDATE())
                    ");
                    foreach ($notifyIds as $sid) {
                        $notifyIns->execute([$sid, $msg, $transferCenterId]);
                    }
                }

                $pdo->commit();
                $qsArr = $_GET;
                $qsArr['transferred'] = $membersMoved . ',' . $loansMoved;
                header('Location: centers.php?' . http_build_query($qsArr));
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Could not transfer the center. Please try again.';
            }
        }
    }
}

/* ---- Filters ---- */
$branchFilter = $_GET['branch'] ?? '';
$dayFilter = $_GET['day'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$conditions = [];
$params = [];
if ($branchFilter) { $conditions[] = 'c.BranchID = ?'; $params[] = $branchFilter; }
if ($dayFilter !== '') { $conditions[] = 'c.MeetingDay = ?'; $params[] = $dayFilter; }
if ($statusFilter === 'active') { $conditions[] = 'c.IsActive = 1'; }
elseif ($statusFilter === 'inactive') { $conditions[] = 'c.IsActive = 0'; }
if ($q !== '') {
    $conditions[] = '(c.CenterName LIKE ? OR c.CenterCode LIKE ? OR c.ContactPerson LIKE ? OR c.ContactNo LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Center c $whereSql");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
  SELECT c.*, b.BranchName, u.FullName AS manager,
    (SELECT COUNT(*) FROM Member m WHERE m.CenterID = c.CenterID) AS member_count
  FROM Center c
  LEFT JOIN Branch b ON c.BranchID = b.BranchID
  LEFT JOIN Staff u ON c.ManagedByStaffID = u.StaffID
  $whereSql
  ORDER BY b.BranchName, c.CenterName
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
");
// SQL Server's OFFSET/FETCH row counts must be bound as actual integers —
// a plain execute([...]) sends every value as a string and SQL Server rejects that.
$paramIndex = 1;
foreach ($params as $val) { $stmt->bindValue($paramIndex++, $val); }
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$centers = $stmt->fetchAll();

$branches = $pdo->query("SELECT BranchID, BranchName FROM Branch WHERE BranchName NOT LIKE '%HEAD OFFICE%' ORDER BY BranchName")->fetchAll();
$staffList = $pdo->query("SELECT StaffID, FullName FROM Staff WHERE IsActive=1 ORDER BY FullName")->fetchAll();

$shiftHistoryByCenter = [];
if ($canShiftMeeting && $centers) {
    $centerIds = array_column($centers, 'CenterID');
    $placeholders = implode(',', array_fill(0, count($centerIds), '?'));
    $histStmt = $pdo->prepare("
        SELECT h.*, s.FullName AS ShiftedByName FROM Center_Meeting_Shift_History h
        LEFT JOIN Staff s ON h.ShiftedByStaffID = s.StaffID
        WHERE h.CenterID IN ($placeholders) ORDER BY h.ShiftedAt DESC
    ");
    $histStmt->execute($centerIds);
    foreach ($histStmt->fetchAll() as $h) {
        $shiftHistoryByCenter[$h['CenterID']][] = $h;
    }
}

$transferHistoryByCenter = [];
$staffByBranch = [];
if ($canTransferCenter && $centers) {
    $centerIds = array_column($centers, 'CenterID');
    $placeholders = implode(',', array_fill(0, count($centerIds), '?'));
    $histStmt = $pdo->prepare("
        SELECT t.*, os.FullName AS OldStaffName, ns.FullName AS NewStaffName, ts.FullName AS TransferredByName
        FROM Center_Transfer_History t
        LEFT JOIN Staff os ON t.OldStaffID = os.StaffID
        LEFT JOIN Staff ns ON t.NewStaffID = ns.StaffID
        LEFT JOIN Staff ts ON t.TransferredByStaffID = ts.StaffID
        WHERE t.CenterID IN ($placeholders) ORDER BY t.TransferredAt DESC
    ");
    $histStmt->execute($centerIds);
    foreach ($histStmt->fetchAll() as $h) {
        $transferHistoryByCenter[$h['CenterID']][] = $h;
    }

    foreach ($pdo->query("SELECT StaffID, FullName, BranchID FROM Staff WHERE IsActive=1 ORDER BY FullName")->fetchAll() as $s) {
        $staffByBranch[$s['BranchID']][] = $s;
    }
}

$totalCentersAll = (int)$pdo->query("SELECT COUNT(*) FROM Center")->fetchColumn();
$activeCentersAll = (int)$pdo->query("SELECT COUNT(*) FROM Center WHERE IsActive=1")->fetchColumn();
$branchesCovered = (int)$pdo->query("SELECT COUNT(DISTINCT BranchID) FROM Center")->fetchColumn();
$totalMembersInCenters = (int)$pdo->query("SELECT COUNT(*) FROM Member WHERE CenterID IS NOT NULL")->fetchColumn();

function centersLink($overrides) {
    global $branchFilter, $dayFilter, $statusFilter, $q;
    $base = ['branch' => $branchFilter, 'day' => $dayFilter, 'status' => $statusFilter, 'q' => $q];
    return '?' . http_build_query(array_merge($base, $overrides));
}
$hasActiveFilter = ($q || $branchFilter || $dayFilter !== '' || $statusFilter);
?>
<style>
.member-count-link { text-decoration: none; cursor: pointer; transition: background .15s, transform .1s; display: inline-block; }
.member-count-link:hover { background: #c3e0f7; transform: translateY(-1px); }
</style>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy">🏘️</div>
      <div class="stat-body">
        <div class="num"><?= number_format($totalCentersAll) ?></div>
        <div class="lbl">Total Centers</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal">✅</div>
      <div class="stat-body">
        <div class="num"><?= number_format($activeCentersAll) ?></div>
        <div class="lbl">Active Centers</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold">🏢</div>
      <div class="stat-body">
        <div class="num"><?= number_format($branchesCovered) ?></div>
        <div class="lbl">Branches Covered</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft">🧑‍🤝‍🧑</div>
      <div class="stat-body">
        <div class="num"><?= number_format($totalMembersInCenters) ?></div>
        <div class="lbl">Members in Centers</div>
      </div>
    </div>
  </div>
</div>

<?php if ($error): ?>
  <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div>
<?php elseif (isset($_GET['updated'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Center updated successfully.</div>
<?php elseif (isset($_GET['shifted'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Meeting shifted successfully — <?= (int)$_GET['shifted'] ?> upcoming repayment date(s) updated.</div>
<?php elseif (isset($_GET['transferred'])): [$tm, $tl] = array_map('intval', explode(',', $_GET['transferred'] . ',0')); ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Center transferred successfully — <?= $tm ?> member(s) and <?= $tl ?> active loan(s) moved.</div>
<?php endif; ?>

<div class="card-x mb-3">
  <form method="GET" class="row g-3 align-items-end">
    <div class="col-md-4 col-lg-4">
      <label class="form-label">Search</label>
      <div class="search-box" style="width:100%;">
        <span class="ic">🔍</span>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Center name, code, contact person or number...">
      </div>
    </div>
    <div class="col-6 col-md-2 col-lg-2">
      <label class="form-label">Branch</label>
      <select class="form-select" name="branch" onchange="this.form.submit()">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?>
          <option value="<?= $b['BranchID'] ?>" <?= $branchFilter==$b['BranchID']?'selected':'' ?>><?= htmlspecialchars($b['BranchName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2 col-lg-2">
      <label class="form-label">Meeting Day</label>
      <select class="form-select" name="day" onchange="this.form.submit()">
        <option value="">Any Day</option>
        <?php foreach ($dayNames as $val => $label): ?>
          <option value="<?= $val ?>" <?= $dayFilter==$val?'selected':'' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2 col-lg-2">
      <label class="form-label">Status</label>
      <select class="form-select" name="status" onchange="this.form.submit()">
        <option value="">Any Status</option>
        <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
      </select>
    </div>
    <div class="col-6 col-md-2 col-lg-2 d-flex gap-2">
      <button class="btn-navy" type="submit" style="flex:1;">Search</button>
      <?php if ($hasActiveFilter): ?><a href="centers.php" class="btn-outline-x" style="color:var(--danger);">Clear</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card-x">
  <div class="table-scroll">
  <table class="table-x">
    <tr><th>Code</th><th>Center</th><th>Branch</th><th>Contact</th><th>Schedule</th><th>Managed By</th><th>Members</th><th>Status</th><th></th></tr>
    <?php foreach ($centers as $c): ?>
    <tr>
      <td><?= htmlspecialchars($c['CenterCode']) ?></td>
      <td>
        <div style="font-weight:600;"><?= htmlspecialchars($c['CenterName']) ?></div>
        <?php if ($c['CenterPlace']): ?><div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($c['CenterPlace']) ?></div><?php endif; ?>
      </td>
      <td><?= htmlspecialchars($c['BranchName'] ?? '-') ?></td>
      <td>
        <?php if ($c['ContactPerson'] || $c['ContactNo']): ?>
          <div><?= htmlspecialchars($c['ContactPerson'] ?: '-') ?></div>
          <div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($c['ContactNo'] ?: '-') ?></div>
        <?php else: ?>-<?php endif; ?>
      </td>
      <td><?= htmlspecialchars($dayNames[$c['MeetingDay']] ?? ($c['MeetingDay'] ?: '-')) ?><div style="font-size:11.5px;color:var(--text-muted);"><?= htmlspecialchars($c['MeetingTime'] ?: '') ?></div></td>
      <td><?= htmlspecialchars($c['manager'] ?? 'Unassigned') ?></td>
      <td><a href="members.php?center=<?= $c['CenterID'] ?>" class="badge badge-info member-count-link" title="View members in this center">👥 <?= (int)$c['member_count'] ?></a></td>
      <td><span class="badge badge-<?= $c['IsActive']?'success':'danger' ?>"><?= $c['IsActive']?'Active':'Inactive' ?></span></td>
      <td class="text-nowrap">
        <?php if ($c['Latitude'] && $c['Longitude']): ?>
          <button type="button" class="btn-outline-x map-btn" style="font-size:12px;padding:5px 10px;" data-lat="<?= htmlspecialchars($c['Latitude']) ?>" data-lng="<?= htmlspecialchars($c['Longitude']) ?>" data-name="<?= htmlspecialchars($c['CenterName']) ?>" title="View on map">📍</button>
        <?php endif; ?>
        <?php if ($canShiftMeeting): ?>
          <button type="button" class="btn-outline-x" style="font-size:12px;padding:5px 10px;" data-bs-toggle="modal" data-bs-target="#shiftMeeting<?= $c['CenterID'] ?>" title="Shift meeting day/time">🔄</button>
        <?php endif; ?>
        <?php if ($canTransferCenter): ?>
          <button type="button" class="btn-outline-x" style="font-size:12px;padding:5px 10px;" data-bs-toggle="modal" data-bs-target="#transferCenter<?= $c['CenterID'] ?>" title="Transfer to another employee">🔁</button>
        <?php endif; ?>
        <button type="button" class="btn-outline-x" style="font-size:12px;padding:5px 12px;" data-bs-toggle="modal" data-bs-target="#editCenter<?= $c['CenterID'] ?>">Edit</button>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$centers): ?><tr><td colspan="9"><div class="empty-state">No centers match your filters.</div></td></tr><?php endif; ?>
  </table>
  </div>

  <div class="pagination-bar">
    <div class="info">Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalFiltered) ?> of <?= number_format($totalFiltered) ?> centers</div>
    <div class="pager">
      <a class="<?= $page<=1?'disabled':'' ?>" href="<?= centersLink(['page'=>$page-1]) ?>">‹ Prev</a>
      <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>
      <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= centersLink(['page'=>$page+1]) ?>">Next ›</a>
    </div>
  </div>
</div>

<!-- ---- Map modal (shared, Leaflet + OpenStreetMap — free, no API key) ---- -->
<div class="modal fade" id="mapModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mapModalTitle">Center Location</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding:0;">
        <div id="centerMap" style="height:320px;border-radius:0 0 var(--radius) var(--radius);"></div>
      </div>
      <div class="modal-footer">
        <a id="gmapsLink" href="#" target="_blank" rel="noopener" class="btn-outline-x">Open in Google Maps</a>
        <button type="button" class="btn-navy" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php foreach ($centers as $c): ?>
<div class="modal fade" id="editCenter<?= $c['CenterID'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="center_id" value="<?= $c['CenterID'] ?>">
        <div class="modal-header">
          <h5 class="modal-title">Edit Center — <?= htmlspecialchars($c['CenterName']) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="form-section-label"><span class="dot">🏷️</span> Basic Information</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Center Name <span class="required-mark">*</span></label>
              <input class="form-control" name="center_name" required value="<?= htmlspecialchars($c['CenterName']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Center Code <span class="required-mark">*</span></label>
              <input class="form-control" name="center_code" required value="<?= htmlspecialchars($c['CenterCode']) ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label">Address</label>
              <input class="form-control" name="address" value="<?= htmlspecialchars($c['Address'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Place / Locality</label>
              <input class="form-control" name="center_place" value="<?= htmlspecialchars($c['CenterPlace'] ?? '') ?>">
            </div>
          </div>

          <div class="form-section-label"><span class="dot">🏢</span> Branch &amp; Schedule</div>
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Branch <span class="required-mark">*</span></label>
              <select class="form-select" name="branch_id" required>
                <?php foreach ($branches as $b): ?><option value="<?= $b['BranchID'] ?>" <?= $c['BranchID']==$b['BranchID']?'selected':'' ?>><?= htmlspecialchars($b['BranchName']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Meeting Day</label>
              <select class="form-select" name="meeting_day">
                <option value="">Not set</option>
                <?php foreach ($dayNames as $val => $label): ?><option value="<?= $val ?>" <?= $c['MeetingDay']==$val?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Meeting Time</label>
              <input class="form-control" name="meeting_time" value="<?= htmlspecialchars($c['MeetingTime'] ?? '') ?>" placeholder="e.g. 09:00 AM">
            </div>
          </div>

          <div class="form-section-label"><span class="dot">📍</span> Location &amp; Contact</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Contact Person</label>
              <input class="form-control" name="contact_person" value="<?= htmlspecialchars($c['ContactPerson'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Contact No.</label>
              <input class="form-control" name="contact_no" value="<?= htmlspecialchars($c['ContactNo'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Latitude</label>
              <input class="form-control" name="latitude" value="<?= htmlspecialchars($c['Latitude'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Longitude</label>
              <input class="form-control" name="longitude" value="<?= htmlspecialchars($c['Longitude'] ?? '') ?>">
            </div>
          </div>

          <div class="form-section-label"><span class="dot">👤</span> Management &amp; Status</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Managed By</label>
              <select class="form-select" name="managed_by">
                <option value="">Unassigned</option>
                <?php foreach ($staffList as $s): ?><option value="<?= $s['StaffID'] ?>" <?= $c['ManagedByStaffID']==$s['StaffID']?'selected':'' ?>><?= htmlspecialchars($s['FullName']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Dissolved Date</label>
              <input type="date" class="form-control" name="dissolved_date" value="<?= htmlspecialchars($c['DissolvedDate'] ?? '') ?>">
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveCenter<?= $c['CenterID'] ?>" <?= $c['IsActive']?'checked':'' ?>>
                <label class="form-check-label" for="isActiveCenter<?= $c['CenterID'] ?>" style="font-size:13.5px;font-weight:600;">Center is active</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-outline-x" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-navy">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($canShiftMeeting): $hist = $shiftHistoryByCenter[$c['CenterID']] ?? []; ?>
<div class="modal fade" id="shiftMeeting<?= $c['CenterID'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="shift_meeting_center_id" value="<?= $c['CenterID'] ?>">
        <div class="modal-header">
          <h5 class="modal-title">Shift Meeting — <?= htmlspecialchars($c['CenterName']) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">
            Current: <strong><?= htmlspecialchars($dayNames[$c['MeetingDay']] ?? ($c['MeetingDay'] ?: 'Not set')) ?></strong><?= $c['MeetingTime'] ? ' at ' . htmlspecialchars($c['MeetingTime']) : '' ?>.
            Changing the day moves every <strong>unpaid, not-yet-due</strong> installment of this center's <strong>active</strong> loans to the same week's new day. Closed/rejected/preclosed loans and already-overdue installments are left untouched.
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">New Meeting Day <span class="required-mark">*</span></label>
              <select class="form-select" name="shift_new_day" required>
                <option value="">Select day</option>
                <?php foreach ($dayNames as $val => $label): ?><option value="<?= $val ?>" <?= (string)$c['MeetingDay']===(string)$val?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">New Meeting Time</label>
              <input class="form-control" name="shift_new_time" value="<?= htmlspecialchars($c['MeetingTime'] ?? '') ?>" placeholder="e.g. 09:00 AM">
            </div>
            <div class="col-12">
              <label class="form-label">Reason (optional)</label>
              <input class="form-control" name="shift_remark" placeholder="Why is this center's meeting moving?">
            </div>
          </div>

          <?php if ($hist): ?>
          <div class="form-section-label" style="margin-top:18px;"><span class="dot">🕘</span> Shift History</div>
          <div class="table-scroll" style="max-height:180px;">
            <table class="table-x">
              <tr><th>When</th><th>From → To</th><th>Schedules</th><th>By</th></tr>
              <?php foreach ($hist as $h): ?>
              <tr>
                <td style="font-size:11.5px;"><?= fdate($h['ShiftedAt']) ?></td>
                <td style="font-size:11.5px;"><?= htmlspecialchars($dayNames[$h['OldMeetingDay']] ?? ($h['OldMeetingDay'] ?: '-')) ?> → <?= htmlspecialchars($dayNames[$h['NewMeetingDay']] ?? $h['NewMeetingDay']) ?></td>
                <td style="font-size:11.5px;"><?= (int)$h['SchedulesShiftedCount'] ?> / <?= (int)$h['LoansAffectedCount'] ?> loans</td>
                <td style="font-size:11.5px;"><?= htmlspecialchars($h['ShiftedByName'] ?? '-') ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-outline-x" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-navy" onclick="return confirm('Shift this center\'s meeting? Upcoming repayment dates for active loans will be updated to match.');">Shift Meeting</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($canTransferCenter): $thist = $transferHistoryByCenter[$c['CenterID']] ?? []; $branchStaff = array_filter($staffByBranch[$c['BranchID']] ?? [], fn($s) => (int)$s['StaffID'] !== (int)$c['ManagedByStaffID']); ?>
<div class="modal fade" id="transferCenter<?= $c['CenterID'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="transfer_center_id" value="<?= $c['CenterID'] ?>">
        <div class="modal-header">
          <h5 class="modal-title">Transfer Center — <?= htmlspecialchars($c['CenterName']) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px;">
            Currently managed by <strong><?= htmlspecialchars($c['manager'] ?? 'Unassigned') ?></strong>.
            Transferring moves this center's manager, all its <strong>members</strong>, all its <strong>active (non-closed) loans</strong>' officer, and any pending collection rows to the new employee. Only employees in the same branch (<?= htmlspecialchars($c['BranchName'] ?? '-') ?>) can be selected.
          </div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">New Employee <span class="required-mark">*</span></label>
              <select class="form-select" name="transfer_new_staff_id" required>
                <option value="">Select employee</option>
                <?php foreach ($branchStaff as $s): ?><option value="<?= $s['StaffID'] ?>"><?= htmlspecialchars($s['FullName']) ?></option><?php endforeach; ?>
              </select>
              <?php if (!$branchStaff): ?><div style="font-size:11.5px;color:var(--danger);margin-top:4px;">No other active employees found in this branch.</div><?php endif; ?>
            </div>
            <div class="col-12">
              <label class="form-label">Reason (optional)</label>
              <input class="form-control" name="transfer_remark" placeholder="Why is this center being transferred?">
            </div>
          </div>

          <?php if ($thist): ?>
          <div class="form-section-label" style="margin-top:18px;"><span class="dot">🕘</span> Transfer History</div>
          <div class="table-scroll" style="max-height:180px;">
            <table class="table-x">
              <tr><th>When</th><th>From → To</th><th>Moved</th><th>By</th></tr>
              <?php foreach ($thist as $h): ?>
              <tr>
                <td style="font-size:11.5px;"><?= fdate($h['TransferredAt']) ?></td>
                <td style="font-size:11.5px;"><?= htmlspecialchars($h['OldStaffName'] ?? 'Unassigned') ?> → <?= htmlspecialchars($h['NewStaffName'] ?? '-') ?></td>
                <td style="font-size:11.5px;"><?= (int)$h['MembersTransferredCount'] ?> members / <?= (int)$h['LoansTransferredCount'] ?> loans</td>
                <td style="font-size:11.5px;"><?= htmlspecialchars($h['TransferredByName'] ?? '-') ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-outline-x" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-navy" <?= !$branchStaff?'disabled':'' ?> onclick="return confirm('Transfer this center? Its members and active loans will move to the new employee.');">Transfer Center</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/images/marker-icon-2x.png',
  iconUrl: 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/images/marker-shadow.png',
});

let centerMap = null;
let centerMarker = null;
let pendingLat = 0, pendingLng = 0;
const mapModalEl = document.getElementById('mapModal');

mapModalEl.addEventListener('shown.bs.modal', function () {
  if (!centerMap) {
    centerMap = L.map('centerMap').setView([pendingLat, pendingLng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
    }).addTo(centerMap);
    centerMarker = L.marker([pendingLat, pendingLng]).addTo(centerMap);
  } else {
    centerMap.setView([pendingLat, pendingLng], 15);
    centerMarker.setLatLng([pendingLat, pendingLng]);
  }
  centerMap.invalidateSize();
});

document.querySelectorAll('.map-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    pendingLat = parseFloat(btn.dataset.lat);
    pendingLng = parseFloat(btn.dataset.lng);
    document.getElementById('mapModalTitle').textContent = '📍 ' + btn.dataset.name;
    document.getElementById('gmapsLink').href = `https://www.google.com/maps?q=${pendingLat},${pendingLng}`;
    bootstrap.Modal.getOrCreateInstance(mapModalEl).show();
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
