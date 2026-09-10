<?php
$pageTitle = 'Audit Log';
$crumb = 'Every create, update & delete — automatically logged';
$active = 'audit';
require_once __DIR__ . '/includes/header.php';

$tableFilter = $_GET['table'] ?? '';
$tables = $pdo->query("SELECT DISTINCT TableName FROM Audit_Log ORDER BY TableName")->fetchAll(PDO::FETCH_COLUMN);

$where = $tableFilter ? "WHERE a.TableName = ?" : "";
$stmt = $pdo->prepare("
  SELECT TOP 200 a.*, s.FullName AS staff_name
  FROM Audit_Log a
  LEFT JOIN Staff s ON a.ChangedByStaffID = s.StaffID
  $where
  ORDER BY a.ChangedAt DESC
");
$stmt->execute($tableFilter ? [$tableFilter] : []);
$logs = $stmt->fetchAll();

$actionColor = ['INSERT'=>'success','UPDATE'=>'warning','DELETE'=>'danger'];
?>
<div class="filters">
  <a class="btn-outline-x" style="<?= !$tableFilter?'background:var(--navy);color:#fff;':'' ?>" href="?table=">All Tables</a>
  <?php foreach ($tables as $t): ?>
    <a class="btn-outline-x" style="<?= $tableFilter==$t?'background:var(--navy);color:#fff;':'' ?>" href="?table=<?= urlencode($t) ?>"><?= htmlspecialchars($t) ?></a>
  <?php endforeach; ?>
</div>

<div class="card-x">
  <table class="table-x">
    <tr><th>When</th><th>Table</th><th>Record ID</th><th>Action</th><th>Changed By</th><th>Details</th></tr>
    <?php foreach ($logs as $l): ?>
    <tr>
      <td><?= date('d M Y H:i', strtotime($l['ChangedAt'])) ?></td>
      <td><?= htmlspecialchars($l['TableName']) ?></td>
      <td>#<?= $l['RecordID'] ?></td>
      <td><span class="badge badge-<?= $actionColor[$l['ActionType']] ?? 'secondary' ?>"><?= $l['ActionType'] ?></span></td>
      <td><?= htmlspecialchars($l['staff_name'] ?? 'System') ?></td>
      <td>
        <details>
          <summary style="cursor:pointer;font-size:12px;color:var(--text-muted);">View JSON</summary>
          <?php if ($l['OldValues']): ?><div style="font-size:11px;color:var(--text-muted);margin-top:4px;"><strong>Before:</strong> <code><?= htmlspecialchars(substr($l['OldValues'],0,300)) ?></code></div><?php endif; ?>
          <?php if ($l['NewValues']): ?><div style="font-size:11px;color:var(--text-muted);margin-top:4px;"><strong>After:</strong> <code><?= htmlspecialchars(substr($l['NewValues'],0,300)) ?></code></div><?php endif; ?>
        </details>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="6"><div class="empty-state">No audit activity recorded yet.</div></td></tr><?php endif; ?>
  </table>
</div>
<div style="font-size:11.5px;color:var(--text-muted);margin-top:10px;">
  Showing the 200 most recent entries. Every insert/update/delete on Member, Loan, Collection,
  Task, Interaction, Staff, Branch, Center, and Repayment_Schedule is captured here automatically by database triggers.
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
