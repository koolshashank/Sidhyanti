<?php
$pageTitle = 'Loan Schemes';
$crumb = 'Manage your lending products / schemes';
$active = 'schemes';
require_once __DIR__ . '/includes/header.php';

$productTypes = $pdo->query("SELECT ProductTypeID, ProductTypeName FROM Product_Type_Master ORDER BY ProductTypeName")->fetchAll();

$productFilter = $_GET['product'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');

$conditions = [];
$params = [];
if ($productFilter) { $conditions[] = 's.ProductTypeID = ?'; $params[] = $productFilter; }
if ($statusFilter === 'active') { $conditions[] = 's.IsActive = 1'; }
elseif ($statusFilter === 'inactive') { $conditions[] = 's.IsActive = 0'; }
if ($q !== '') {
    $conditions[] = '(s.SchemeName LIKE ? OR s.SchemeCode LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%";
}
$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Loan_Scheme s $whereSql");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
  SELECT s.*, pt.ProductTypeName
  FROM Loan_Scheme s
  LEFT JOIN Product_Type_Master pt ON s.ProductTypeID = pt.ProductTypeID
  $whereSql
  ORDER BY s.IsActive DESC, s.SchemeName
  OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
");
// SQL Server's OFFSET/FETCH row counts must be bound as actual integers —
// a plain execute([...]) sends every value as a string and SQL Server rejects that.
$paramIndex = 1;
foreach ($params as $val) { $stmt->bindValue($paramIndex++, $val); }
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $perPage, PDO::PARAM_INT);
$stmt->execute();
$schemes = $stmt->fetchAll();

function schemesLink($overrides) {
    global $productFilter, $statusFilter, $q;
    $base = ['product' => $productFilter, 'status' => $statusFilter, 'q' => $q];
    return '?' . http_build_query(array_merge($base, $overrides));
}

$allSchemes = $pdo->query("SELECT ProductTypeID, InterestRatePercent, IsActive FROM Loan_Scheme")->fetchAll();
$totalSchemesAll = count($allSchemes);
$activeSchemesAll = count(array_filter($allSchemes, fn($s) => $s['IsActive']));
$productTypesUsed = count(array_unique(array_filter(array_column($allSchemes, 'ProductTypeID'))));
$avgInterest = $totalSchemesAll ? round(array_sum(array_column($allSchemes, 'InterestRatePercent')) / $totalSchemesAll, 1) : 0;

$hasActiveFilter = ($q || $productFilter || $statusFilter);
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--navy);">
      <div class="icon-box bg-navy">📦</div>
      <div class="stat-body">
        <div class="num"><?= number_format($totalSchemesAll) ?></div>
        <div class="lbl">Total Schemes</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--teal);">
      <div class="icon-box bg-teal">✅</div>
      <div class="stat-body">
        <div class="num"><?= number_format($activeSchemesAll) ?></div>
        <div class="lbl">Active Schemes</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--gold);">
      <div class="icon-box bg-gold">🏷️</div>
      <div class="stat-body">
        <div class="num"><?= $productTypesUsed ?></div>
        <div class="lbl">Product Types</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card-x stat-card-h" style="--accent:var(--danger);">
      <div class="icon-box bg-danger-soft">📈</div>
      <div class="stat-body">
        <div class="num"><?= $avgInterest ?>%</div>
        <div class="lbl">Avg Interest Rate</div>
      </div>
    </div>
  </div>
</div>

<?php if (isset($_GET['created'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Scheme created successfully.</div>
<?php elseif (isset($_GET['updated'])): ?>
  <div class="badge badge-success" style="display:block;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">Scheme updated successfully.</div>
<?php endif; ?>

<div class="card-x mb-3">
  <form method="GET" class="row g-3 align-items-end">
    <div class="col-md-4">
      <label class="form-label">Search</label>
      <div class="search-box" style="width:100%;">
        <span class="ic">🔍</span>
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Scheme name or code...">
      </div>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Product Type</label>
      <select class="form-select" name="product" onchange="this.form.submit()">
        <option value="">All Product Types</option>
        <?php foreach ($productTypes as $pt): ?>
          <option value="<?= $pt['ProductTypeID'] ?>" <?= $productFilter==$pt['ProductTypeID']?'selected':'' ?>><?= htmlspecialchars($pt['ProductTypeName']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label">Status</label>
      <select class="form-select" name="status" onchange="this.form.submit()">
        <option value="">Any Status</option>
        <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
      </select>
    </div>
    <div class="col-md-2 d-flex gap-2">
      <button class="btn-navy" type="submit" style="flex:1;">Search</button>
      <?php if ($hasActiveFilter): ?><a href="schemes.php" class="btn-outline-x" style="color:var(--danger);">Clear</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div style="color:var(--text-muted);font-size:13px;">Every new loan can be issued against one of these pre-configured products.</div>
  <a href="scheme_add.php" class="btn-navy">+ New Scheme</a>
</div>

<div class="card-x">
  <div class="table-scroll">
  <table class="table-x">
    <tr><th>Code</th><th>Scheme Name</th><th>Product Type</th><th>Amount Range</th><th>Tenure</th><th>Interest</th><th>Frequency</th><th>Status</th><th></th></tr>
    <?php foreach ($schemes as $s): ?>
    <tr>
      <td><?= htmlspecialchars($s['SchemeCode']) ?></td>
      <td style="font-weight:600;"><?= htmlspecialchars($s['SchemeName']) ?></td>
      <td><?= htmlspecialchars($s['ProductTypeName'] ?? '-') ?></td>
      <td><?= $s['MinLoanAmount'] || $s['MaxLoanAmount'] ? money($s['MinLoanAmount']).' - '.money($s['MaxLoanAmount']) : ($s['DefaultLoanAmount'] ? money($s['DefaultLoanAmount']) : '-') ?></td>
      <td><?= $s['TenureMonths'] ?> months</td>
      <td style="font-weight:700;color:var(--navy);"><?= $s['InterestRatePercent'] ?>% <?= $s['InterestMethod'] ? '('.htmlspecialchars($s['InterestMethod']).')' : '' ?></td>
      <td><?= htmlspecialchars($s['PaymentFrequency']) ?></td>
      <td><span class="badge badge-<?= $s['IsActive']?'success':'secondary' ?>"><?= $s['IsActive']?'Active':'Inactive' ?></span></td>
      <td><a href="scheme_view.php?id=<?= $s['SchemeID'] ?>" class="btn-outline-x" style="font-size:12px;padding:5px 12px;">View</a></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$schemes): ?><tr><td colspan="9"><div class="empty-state">No schemes match your filters.</div></td></tr><?php endif; ?>
  </table>
  </div>

  <div class="pagination-bar">
    <div class="info">Showing <?= $totalFiltered ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalFiltered) ?> of <?= number_format($totalFiltered) ?> schemes</div>
    <div class="pager">
      <a class="<?= $page<=1?'disabled':'' ?>" href="<?= schemesLink(['page'=>$page-1]) ?>">‹ Prev</a>
      <span class="current">Page <?= $page ?> of <?= $totalPages ?></span>
      <a class="<?= $page>=$totalPages?'disabled':'' ?>" href="<?= schemesLink(['page'=>$page+1]) ?>">Next ›</a>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
