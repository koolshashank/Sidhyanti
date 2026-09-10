<?php
$pageTitle = 'Tasks & Follow-ups';
$crumb = 'Everything the team needs to action';
$active = 'tasks';
require_once __DIR__ . '/includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='complete') {
    $completedStatusId = lookup_id($pdo, 'Task_Status_Master', 'StatusID', 'StatusName', 'Completed');
    $pdo->prepare("UPDATE Task SET StatusID=?, CompletedAt=GETDATE() WHERE TaskID=?")->execute([$completedStatusId, $_POST['task_id']]);
    header('Location: tasks.php?' . http_build_query($_GET)); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $openStatusId = lookup_id($pdo, 'Task_Status_Master', 'StatusID', 'StatusName', 'Open');
    $stmt = $pdo->prepare("INSERT INTO Task (AssignedToStaffID, CreatedByStaffID, Title, Description, DueDate, PriorityID, StatusID, RelatedEntityType) VALUES (?,?,?,?,?,?,?, 'General')");
    $stmt->execute([$_POST['assigned_to'], current_staff()['StaffID'], $_POST['title'], $_POST['description'], $_POST['due_date'], $_POST['priority_id'], $openStatusId]);
    header('Location: tasks.php'); exit;
}

$staff = $pdo->query("SELECT StaffID, FullName FROM Staff WHERE IsActive=1 ORDER BY FullName")->fetchAll();
$priorities = $pdo->query("SELECT PriorityID, PriorityName FROM Task_Priority_Master ORDER BY SortOrder")->fetchAll();

$statusFilter = $_GET['status'] ?? '';
$where = $statusFilter ? "WHERE ts.StatusName = ?" : "";
$stmt = $pdo->prepare("
  SELECT t.*, u.FullName AS assignee, c.FullName AS creator, p.PriorityName, ts.StatusName
  FROM Task t
  LEFT JOIN Staff u ON t.AssignedToStaffID = u.StaffID
  LEFT JOIN Staff c ON t.CreatedByStaffID = c.StaffID
  LEFT JOIN Task_Priority_Master p ON t.PriorityID = p.PriorityID
  LEFT JOIN Task_Status_Master ts ON t.StatusID = ts.StatusID
  $where
  ORDER BY CASE ts.StatusName WHEN 'Overdue' THEN 1 WHEN 'Open' THEN 2 WHEN 'In Progress' THEN 3 ELSE 4 END, t.DueDate ASC
");
$stmt->execute($statusFilter ? [$statusFilter] : []);
$tasks = $stmt->fetchAll();

$statusCounts = [];
foreach ($pdo->query("SELECT ts.StatusName, COUNT(*) c FROM Task t JOIN Task_Status_Master ts ON t.StatusID=ts.StatusID GROUP BY ts.StatusName")->fetchAll() as $row) {
    $statusCounts[$row['StatusName']] = $row['c'];
}
?>

<div class="row g-3 mb-3">
  <?php foreach (['Open','In Progress','Completed','Overdue'] as $s): ?>
  <div class="col-6 col-md-3">
    <a href="?status=<?= $s ?>" style="color:inherit;">
      <div class="card-x stat-card"><div class="num"><?= $statusCounts[$s] ?? 0 ?></div><div class="lbl"><?= $s ?></div></div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card-x">
      <div class="section-title">
        Task List <?= $statusFilter ? " — $statusFilter" : '' ?>
        <?php if ($statusFilter): ?><a href="tasks.php" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">Clear filter</a><?php endif; ?>
      </div>
      <table class="table-x">
        <tr><th></th><th>Title</th><th>Assignee</th><th>Due</th><th>Priority</th><th>Status</th></tr>
        <?php foreach ($tasks as $t): ?>
        <tr>
          <td>
            <?php if ($t['StatusName'] !== 'Completed'): ?>
            <form method="POST"><input type="hidden" name="action" value="complete"><input type="hidden" name="task_id" value="<?= $t['TaskID'] ?>">
              <button class="btn-outline-x" style="padding:3px 8px;font-size:11px;" title="Mark complete">✓</button>
            </form>
            <?php else: ?>✅<?php endif; ?>
          </td>
          <td><?= htmlspecialchars($t['Title']) ?></td>
          <td><?= htmlspecialchars($t['assignee'] ?? 'Unassigned') ?></td>
          <td><?= fdate($t['DueDate']) ?></td>
          <td><?= priority_badge($t['PriorityName']) ?></td>
          <td><?= stage_badge($t['StatusName']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$tasks): ?><tr><td colspan="6"><div class="empty-state">No tasks found.</div></td></tr><?php endif; ?>
      </table>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card-x">
      <div class="section-title">Create Task</div>
      <form method="POST">
        <label class="form-label">Title</label>
        <input class="form-control mb-2" name="title" required>
        <label class="form-label">Description</label>
        <textarea class="form-control mb-2" name="description" rows="3"></textarea>
        <label class="form-label">Assign To</label>
        <select class="form-select mb-2" name="assigned_to" required>
          <?php foreach ($staff as $s): ?><option value="<?= $s['StaffID'] ?>"><?= htmlspecialchars($s['FullName']) ?></option><?php endforeach; ?>
        </select>
        <label class="form-label">Due Date</label>
        <input type="date" class="form-control mb-2" name="due_date" required>
        <label class="form-label">Priority</label>
        <select class="form-select mb-3" name="priority_id">
          <?php foreach ($priorities as $p): ?><option value="<?= $p['PriorityID'] ?>"><?= htmlspecialchars($p['PriorityName']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn-navy w-100" type="submit">Create Task</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
