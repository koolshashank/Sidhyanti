<?php
$pageTitle = 'Loan Approvals';
$crumb = 'Review loan proposals and preclose requests';
$active = 'loan_approvals';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/permission_helper.php';
require_once __DIR__ . '/includes/loan_approval_helper.php';
require_once __DIR__ . '/includes/loan_preclose_helper.php';
require_once __DIR__ . '/api/includes/scope_helper.php';
require_once __DIR__ . '/api/includes/serialize_helper.php';

$claims = current_staff();
$canProposal = role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_loan_proposal');
$canPreclose = role_has_permission($pdo, $claims['RoleID'] ?? null, 'approve_loan_preclose');

if (!$canProposal && !$canPreclose) {
    echo '<div class="empty-state">You do not have permission to review loan approvals.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$reviewError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['loan_approval_id'])) {
    $loanApprovalId = (int)$_POST['loan_approval_id'];
    $action = trim((string)($_POST['action'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? '')) ?: null;

    if ($loanApprovalId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $laStmt = $pdo->prepare("
          SELECT la.LoanApprovalID, la.LoanID, la.RequestType, la.RequestedByStaffID, la.Status,
                 l.LoanNo, l.BranchID, l.LoanOfficerStaffID, l.LoanAmountSanctioned, l.LoanAmountApplied
          FROM Loan_Approval la JOIN Loan l ON la.LoanID = l.LoanID
          WHERE la.LoanApprovalID = ?
        ");
        $laStmt->execute([$loanApprovalId]);
        $la = $laStmt->fetch();
        $permissionKey = ($la['RequestType'] ?? '') === 'Preclose' ? 'approve_loan_preclose' : 'approve_loan_proposal';

        if (!$la) {
            $reviewError = 'Approval request not found.';
        } elseif (!role_has_permission($pdo, $claims['RoleID'] ?? null, $permissionKey)) {
            $reviewError = 'You do not have permission to review this request.';
        } elseif (!in_array((int)$la['BranchID'], scoped_branch_ids($claims), true) && ($claims['RoleName'] ?? '') !== 'Admin') {
            $reviewError = 'This loan is outside your branch scope.';
        } else {
            try {
                review_loan_approval($pdo, $la, $action, $remarks, $claims);
                header("Location: loan_approvals.php?tab=" . ($la['RequestType'] === 'Preclose' ? 'preclose' : 'proposal') . "&reviewed=1");
                exit;
            } catch (LoanApprovalReviewException $e) {
                $reviewError = $e->getMessage();
            }
        }
    } else {
        $reviewError = 'Invalid request.';
    }
}

function fetch_pending_proposals(PDO $pdo, array $claims): array {
    [$whereScope, $scopeParams] = loan_scope_where($claims, 'l');
    $where = $whereScope !== '' ? "AND $whereScope" : '';
    $statusWhere = loan_proposal_status_condition($claims, 'la');
    $stmt = $pdo->prepare("
      SELECT la.LoanApprovalID, la.LoanID, la.RequestRemarks, la.CreatedAt, la.Status,
             l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, m.FirstName, m.LastName, req.FullName AS RequestedByName
      FROM Loan_Approval la
      JOIN Loan l ON la.LoanID = l.LoanID
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Staff req ON la.RequestedByStaffID = req.StaffID
      WHERE la.RequestType = 'Proposal' $statusWhere $where
      ORDER BY la.CreatedAt ASC
    ");
    $stmt->execute($scopeParams);
    return $stmt->fetchAll();
}

function fetch_pending_preclose(PDO $pdo, array $claims): array {
    [$whereScope, $scopeParams] = loan_scope_where($claims, 'l');
    $where = $whereScope !== '' ? "AND $whereScope" : '';
    $stmt = $pdo->prepare("
      SELECT la.LoanApprovalID, la.LoanID, la.RequestRemarks, la.CreatedAt,
             l.LoanNo, l.LoanAmountSanctioned, l.LoanAmountApplied, m.FirstName, m.LastName, req.FullName AS RequestedByName
      FROM Loan_Approval la
      JOIN Loan l ON la.LoanID = l.LoanID
      JOIN Member m ON l.MemberID = m.MemberID
      LEFT JOIN Staff req ON la.RequestedByStaffID = req.StaffID
      WHERE la.RequestType = 'Preclose' AND la.Status = 'Pending' $where
      ORDER BY la.CreatedAt ASC
    ");
    $stmt->execute($scopeParams);
    return $stmt->fetchAll();
}

$proposals = $canProposal ? fetch_pending_proposals($pdo, $claims) : [];
$preclosures = $canPreclose ? fetch_pending_preclose($pdo, $claims) : [];
$activeTab = ($_GET['tab'] ?? '') === 'preclose' ? 'preclose' : 'proposal';
?>

<?php if (isset($_GET['reviewed'])): ?>
<div class="alert alert-success" style="border-radius:10px;">Request reviewed.</div>
<?php endif; ?>
<?php if ($reviewError): ?>
<div class="alert alert-danger" style="border-radius:10px;"><?= htmlspecialchars($reviewError) ?></div>
<?php endif; ?>

<div class="card-x">
  <ul class="nav nav-tabs-x" role="tablist">
    <?php if ($canProposal): ?>
      <li class="nav-item"><button class="nav-link <?= $activeTab === 'proposal' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabProposal" type="button">Loan Proposals (<?= count($proposals) ?>)</button></li>
    <?php endif; ?>
    <?php if ($canPreclose): ?>
      <li class="nav-item"><button class="nav-link <?= $activeTab === 'preclose' || !$canProposal ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tabPreclose" type="button">Preclose Requests (<?= count($preclosures) ?>)</button></li>
    <?php endif; ?>
  </ul>
  <div class="tab-content">
    <?php if ($canProposal): ?>
    <div class="tab-pane fade <?= $activeTab === 'proposal' ? 'show active' : '' ?>" id="tabProposal">
      <?php if (!$proposals): ?>
        <div class="empty-state">No loan proposals awaiting your review.</div>
      <?php else: ?>
        <?php foreach ($proposals as $p): ?>
          <div class="card-x" style="margin:14px 0;border:1px solid var(--border);box-shadow:none;">
            <div style="display:flex;justify-content:space-between;align-items:start;gap:10px;flex-wrap:wrap;">
              <div>
                <div style="font-weight:700;"><a href="loan_view.php?id=<?= $p['LoanID'] ?>"><?= htmlspecialchars(trim($p['FirstName'].' '.$p['LastName'])) ?></a></div>
                <div style="font-size:12.5px;color:var(--text-muted);">Loan <?= htmlspecialchars($p['LoanNo']) ?> · Requested by <?= htmlspecialchars($p['RequestedByName'] ?? '-') ?> · <?= fdate($p['CreatedAt']) ?></div>
                <?php if ($p['RequestRemarks']): ?><div style="font-size:12.5px;margin-top:4px;"><?= htmlspecialchars($p['RequestRemarks']) ?></div><?php endif; ?>
              </div>
              <div style="font-weight:800;font-size:16px;color:var(--navy);"><?= money($p['LoanAmountSanctioned'] ?: $p['LoanAmountApplied']) ?></div>
            </div>
            <form method="POST" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;align-items:center;">
              <input type="hidden" name="loan_approval_id" value="<?= $p['LoanApprovalID'] ?>">
              <input type="text" name="remarks" class="form-control" style="flex:1;min-width:200px;" placeholder="Remarks (optional)">
              <button class="btn-navy" type="submit" name="action" value="approve">Approve</button>
              <button class="btn-outline-x" type="submit" name="action" value="reject" style="color:var(--danger);" onclick="return confirm('Reject this loan proposal?');">Reject</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($canPreclose): ?>
    <div class="tab-pane fade <?= $activeTab === 'preclose' || !$canProposal ? 'show active' : '' ?>" id="tabPreclose">
      <?php if (!$preclosures): ?>
        <div class="empty-state">No preclose requests awaiting your review.</div>
      <?php else: ?>
        <?php foreach ($preclosures as $p): ?>
          <?php $quote = loan_preclose_quote($pdo, (int)$p['LoanID']); ?>
          <div class="card-x" style="margin:14px 0;border:1px solid var(--border);box-shadow:none;">
            <div style="display:flex;justify-content:space-between;align-items:start;gap:10px;flex-wrap:wrap;">
              <div>
                <div style="font-weight:700;"><a href="loan_view.php?id=<?= $p['LoanID'] ?>"><?= htmlspecialchars(trim($p['FirstName'].' '.$p['LastName'])) ?></a></div>
                <div style="font-size:12.5px;color:var(--text-muted);">Loan <?= htmlspecialchars($p['LoanNo']) ?> · Requested by <?= htmlspecialchars($p['RequestedByName'] ?? '-') ?> · <?= fdate($p['CreatedAt']) ?></div>
                <?php if ($p['RequestRemarks']): ?><div style="font-size:12.5px;margin-top:4px;"><?= htmlspecialchars($p['RequestRemarks']) ?></div><?php endif; ?>
              </div>
              <div style="font-weight:800;font-size:16px;color:var(--navy);"><?= money($p['LoanAmountSanctioned'] ?: $p['LoanAmountApplied']) ?></div>
            </div>
            <?php if ($quote): ?>
            <div style="background:var(--bg);border-radius:10px;padding:12px 14px;margin-top:12px;display:flex;flex-wrap:wrap;gap:18px;">
              <div><div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Installments Paid</div><div style="font-weight:800;font-size:15px;"><?= $quote['installments_paid'] ?> / <?= $quote['total_installments'] ?></div></div>
              <div><div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Principal Outstanding</div><div style="font-weight:800;font-size:15px;"><?= money($quote['principal_outstanding']) ?></div></div>
              <div><div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Amount to Pay Now</div><div style="font-weight:800;font-size:15px;color:var(--navy);"><?= money($quote['preclose_amount']) ?></div></div>
              <div><div style="font-size:10.5px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:700;">Member Saves (Interest)</div><div style="font-weight:800;font-size:15px;color:var(--success);"><?= money($quote['interest_savings']) ?></div></div>
            </div>
            <?php endif; ?>
            <form method="POST" style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;align-items:center;">
              <input type="hidden" name="loan_approval_id" value="<?= $p['LoanApprovalID'] ?>">
              <input type="text" name="remarks" class="form-control" style="flex:1;min-width:200px;" placeholder="Remarks (optional)">
              <button class="btn-navy" type="submit" name="action" value="approve">Approve</button>
              <button class="btn-outline-x" type="submit" name="action" value="reject" style="color:var(--danger);" onclick="return confirm('Reject this preclose request?');">Reject</button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
