<?php
$pageTitle = 'Audit Log';
$crumb = 'Every create, update & delete — automatically logged';
$active = 'audit';
require_once __DIR__ . '/includes/header.php';

$tableFilter = $_GET['table'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
if ($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = '';
if ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = '';

$tables = $pdo->query("SELECT DISTINCT TableName FROM Audit_Log ORDER BY TableName")->fetchAll(PDO::FETCH_COLUMN);

$where = [];
$params = [];
if ($tableFilter) { $where[] = 'a.TableName = ?'; $params[] = $tableFilter; }
if ($actionFilter) { $where[] = 'a.ActionType = ?'; $params[] = $actionFilter; }
if ($dateFrom) { $where[] = 'a.ChangedAt >= ?'; $params[] = $dateFrom; }
if ($dateTo) { $where[] = 'a.ChangedAt < DATEADD(DAY, 1, ?)'; $params[] = $dateTo; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

if (($_GET['export'] ?? '') === 'csv') {
    require_once __DIR__ . '/includes/csv_export_helper.php';
    $exportStmt = $pdo->prepare("
      SELECT a.ChangedAt, a.TableName, a.RecordID, a.ActionType, s.FullName AS staff_name, a.OldValues, a.NewValues
      FROM Audit_Log a
      LEFT JOIN Staff s ON a.ChangedByStaffID = s.StaffID
      $whereSql
      ORDER BY a.ChangedAt DESC
    ");
    $exportStmt->execute($params);
    export_csv_and_exit(
        'audit_log.csv',
        ['When', 'Table', 'Record ID', 'Action', 'Changed By', 'Before', 'After'],
        array_map(fn($r) => [
            date('d M Y H:i', strtotime($r['ChangedAt'])), $r['TableName'], $r['RecordID'], $r['ActionType'],
            $r['staff_name'] ?? 'System', $r['OldValues'], $r['NewValues'],
        ], $exportStmt->fetchAll())
    );
}

$perPage = 50;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Audit_Log a $whereSql");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
  SELECT a.*, s.FullName AS staff_name
  FROM Audit_Log a
  LEFT JOIN Staff s ON a.ChangedByStaffID = s.StaffID
  $whereSql
  ORDER BY a.ChangedAt DESC
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
");
$i = 1;
foreach ($params as $p) { $stmt->bindValue($i++, $p); }
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$actionColor = ['INSERT'=>'success','UPDATE'=>'warning','DELETE'=>'danger'];

function auditLogLink($overrides) {
    global $tableFilter, $actionFilter, $dateFrom, $dateTo;
    $base = ['table' => $tableFilter, 'action' => $actionFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo];
    return '?' . http_build_query(array_merge($base, $overrides));
}
?>
<div class="card-x mb-3">
  <form method="GET" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label">Table</label>
      <select class="form-select" name="table" onchange="this.form.submit()">
        <option value="">All Tables</option>
        <?php foreach ($tables as $t): ?>
          <option value="<?= htmlspecialchars($t) ?>" <?= $tableFilter===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Action</label>
      <select class="form-select" name="action" onchange="this.form.submit()">
        <option value="">All Actions</option>
        <option value="INSERT" <?= $actionFilter==='INSERT'?'selected':'' ?>>Insert</option>
        <option value="UPDATE" <?= $actionFilter==='UPDATE'?'selected':'' ?>>Update</option>
        <option value="DELETE" <?= $actionFilter==='DELETE'?'selected':'' ?>>Delete</option>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">From</label>
      <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label">To</label>
      <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button class="btn-navy" type="submit" style="flex:1;">Apply</button>
      <?php if ($tableFilter || $actionFilter || $dateFrom || $dateTo): ?><a href="audit_log.php" class="btn-outline-x" style="color:var(--danger);">Clear</a><?php endif; ?>
    </div>
  </form>
  <div style="text-align:right;margin-top:12px;">
    <a href="<?= auditLogLink(['export' => 'csv']) ?>" class="btn-outline-x"><i class="bi bi-download"></i> Export CSV</a>
  </div>
</div>

<div class="card-x">
  <div class="table-scroll">
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
    <?php if (!$logs): ?><tr><td colspan="6"><div class="empty-state">No audit activity matches these filters.</div></td></tr><?php endif; ?>
  </table>
  </div>

  <div class="pagination-bar">
    <div class="info">Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalFiltered) ?> of <?= number_format($totalFiltered) ?> entries</div>
    <div class="pager">
      <a class="<?= $page<=1?'disabled':'' ?>" href="<?= auditLogLink(['page'=>$page-1]) ?>">‹ Prev</a>
      <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>
      <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= auditLogLink(['page'=>$page+1]) ?>">Next ›</a>
    </div>
  </div>
</div>
<div style="font-size:11.5px;color:var(--text-muted);margin-top:10px;">
  Every insert/update/delete on Member, Loan, Collection, Task, Interaction, Staff, Branch, Center, and
  Repayment_Schedule is captured here automatically by database triggers.
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
