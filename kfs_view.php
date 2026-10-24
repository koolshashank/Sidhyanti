<?php
/**
 * Key Fact Sheet -- print-friendly view for a single loan. Logged-in staff
 * only (same gate as loan_view.php); the print CSS below hides the normal
 * sidebar/topbar chrome so Ctrl+P / the Print button produces a clean A4
 * sheet the Branch Manager can hand to the client for a wet-ink signature.
 */
$pageTitle = 'Key Fact Sheet';
$active = 'loans';
$extraHead = '<style>
  @media print {
    .sidebar, .sidebar-backdrop, .topbar, .kfs-print-bar { display: none !important; }
    .main { margin: 0 !important; padding: 0 !important; }
    .app-shell { display: block !important; }
    body { background: #fff !important; }
  }
  .kfs-sheet { max-width: 820px; margin: 0 auto; background: #fff; padding: 28px 34px; border-radius: 10px; border: 1px solid #e3e7ee; font-size: 13px; color: #1b2436; }
  .kfs-sheet h1 { font-size: 18px; margin: 0 0 2px; }
  .kfs-sheet .kfs-sub { color: #6b7686; font-size: 12px; margin-bottom: 14px; }
  .kfs-sheet table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  .kfs-sheet table th, .kfs-sheet table td { border: 1px solid #dfe3ea; padding: 6px 10px; text-align: left; font-size: 12.5px; }
  .kfs-sheet table th { background: #f4f6fa; width: 42%; }
  .kfs-section-title { font-weight: 700; font-size: 13.5px; margin: 18px 0 8px; color: #16203a; border-bottom: 2px solid #16203a; padding-bottom: 4px; }
  .kfs-sign-row { display: flex; justify-content: space-between; margin-top: 50px; }
  .kfs-sign-box { width: 45%; border-top: 1px solid #333; padding-top: 6px; font-size: 12px; text-align: center; }
  .kfs-print-bar { max-width: 820px; margin: 0 auto 14px; display: flex; justify-content: flex-end; gap: 10px; }
</style>';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/kfs_helper.php';

$loanId = (int)($_GET['loan_id'] ?? 0);
$kfs = $loanId > 0 ? build_kfs_data($pdo, $loanId) : null;

if (!$kfs) {
    echo '<div class="empty-state">Loan not found.</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_signed'])) {
    $pdo->prepare("UPDATE Loan SET KFSSignedCollected = 1, KFSSignedAt = GETDATE(), KFSSignedByStaffID = ? WHERE LoanID = ?")
        ->execute([current_staff()['StaffID'], $loanId]);
    header('Location: kfs_view.php?loan_id=' . $loanId); exit;
}

function kfs_money($v) { return '₹' . number_format((float)$v, 2); }
?>

<div class="kfs-print-bar">
  <?php if (!$kfs['kfs_signed_collected']): ?>
    <form method="POST" onsubmit="return confirm('Confirm the client has signed the printed copy and it has been collected?');">
      <input type="hidden" name="mark_signed" value="1">
      <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-check2-circle"></i> Mark Signed &amp; Collected</button>
    </form>
  <?php else: ?>
    <span class="badge bg-success-subtle text-success" style="padding:8px 12px;">Signed &amp; collected on <?= htmlspecialchars(date('d M Y', strtotime($kfs['kfs_signed_at']))) ?></span>
  <?php endif; ?>
  <button type="button" class="btn btn-navy btn-sm" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print</button>
</div>

<div class="kfs-sheet">
  <h1><?= htmlspecialchars($kfs['company']['company_name']) ?></h1>
  <div class="kfs-sub">
    <?= htmlspecialchars($kfs['company']['registered_office_address']) ?><br>
    NBFC Registration No.: <?= htmlspecialchars($kfs['company']['nbfc_registration_no']) ?> &nbsp;|&nbsp;
    Customer Care: <?= htmlspecialchars($kfs['company']['customer_care_phone']) ?> / <?= htmlspecialchars($kfs['company']['customer_care_email']) ?>
  </div>
  <h2 style="font-size:15px; text-align:center; margin:10px 0 2px; text-decoration:underline;">KEY FACT STATEMENT (KFS)</h2>
  <div class="kfs-sub" style="text-align:center;">Loan No. <?= htmlspecialchars($kfs['loan_no']) ?> &nbsp;|&nbsp; Generated on <?= htmlspecialchars(date('d M Y', strtotime($kfs['generated_at']))) ?></div>

  <div class="kfs-section-title">1. Borrower Details</div>
  <table>
    <tr><th>Name</th><td><?= htmlspecialchars($kfs['member_name']) ?></td></tr>
    <tr><th>Father / Husband Name</th><td><?= htmlspecialchars($kfs['father_husband_name'] ?? '-') ?></td></tr>
    <tr><th>Address</th><td><?= htmlspecialchars($kfs['member_address']) ?></td></tr>
    <tr><th>Mobile No.</th><td><?= htmlspecialchars($kfs['member_mobile'] ?? '-') ?></td></tr>
    <tr><th>Enrollment No.</th><td><?= htmlspecialchars($kfs['enrollment_no'] ?? '-') ?></td></tr>
    <tr><th>Branch / Center</th><td><?= htmlspecialchars($kfs['branch_name'] ?? '-') ?> / <?= htmlspecialchars($kfs['center_name'] ?? '-') ?></td></tr>
  </table>

  <div class="kfs-section-title">2. Loan Details</div>
  <table>
    <tr><th>Loan Scheme</th><td><?= htmlspecialchars($kfs['scheme_name'] ?? '-') ?></td></tr>
    <tr><th>Purpose</th><td><?= htmlspecialchars($kfs['purpose_name'] ?? '-') ?></td></tr>
    <tr><th>Sanctioned Loan Amount</th><td><?= kfs_money($kfs['loan_amount_sanctioned']) ?></td></tr>
    <tr><th>Tenure</th><td><?= (int)$kfs['tenure_months'] ?> months</td></tr>
    <tr><th>Rate of Interest</th><td><?= htmlspecialchars($kfs['interest_rate_percent']) ?>% p.a. (<?= htmlspecialchars($kfs['interest_method']) ?>)</td></tr>
    <tr><th>Repayment Frequency</th><td><?= htmlspecialchars($kfs['repayment_frequency']) ?></td></tr>
    <tr><th>EMI Amount</th><td><?= kfs_money($kfs['emi_amount']) ?></td></tr>
  </table>

  <div class="kfs-section-title">3. Fees &amp; Charges</div>
  <table>
    <tr><th>Processing Fee</th><td><?= kfs_money($kfs['processing_fee']) ?></td></tr>
    <tr><th>Insurance Charge</th><td><?= kfs_money($kfs['insurance_amount']) ?></td></tr>
    <tr><th>Other Charges (Document, Login, etc.)</th><td><?= kfs_money($kfs['other_charges']) ?></td></tr>
    <tr><th>Total Deductions</th><td><?= kfs_money($kfs['total_deductions']) ?></td></tr>
    <tr><th><strong>Net Amount Disbursed</strong></th><td><strong><?= kfs_money($kfs['net_disbursed_amount']) ?></strong></td></tr>
  </table>

  <div class="kfs-section-title">4. Cost of Loan</div>
  <table>
    <tr><th>Total Interest Payable</th><td><?= kfs_money($kfs['total_interest_payable']) ?></td></tr>
    <tr><th>Total Amount Payable (Principal + Interest)</th><td><?= kfs_money($kfs['total_amount_payable']) ?></td></tr>
    <tr><th><strong>Annual Percentage Rate (APR)</strong></th><td><strong><?= $kfs['apr_percent'] !== null ? htmlspecialchars($kfs['apr_percent']) . '%' : 'N/A' ?></strong></td></tr>
  </table>

  <div class="kfs-section-title">5. EMI Schedule (Indicative)</div>
  <div style="font-size:11px; color:#6b7686; margin-bottom:6px;">Exact due dates are fixed at disbursement; amounts shown here will not change.</div>
  <table>
    <tr><th>Installment No.</th><th>Principal</th><th>Interest</th><th>EMI</th></tr>
    <?php foreach ($kfs['schedule'] as $row): ?>
    <tr>
      <td><?= $row['installment_no'] ?></td>
      <td><?= kfs_money($row['principal']) ?></td>
      <td><?= kfs_money($row['interest']) ?></td>
      <td><?= kfs_money($row['emi']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <div class="kfs-section-title">6. Other Disclosures</div>
  <table>
    <tr><th>Cooling-off / Look-up Period</th><td><?= (int)$kfs['cooling_off_period_days'] ?> days from disbursement, during which the loan may be foreclosed without penalty</td></tr>
    <tr><th>Grievance Redressal Officer</th><td><?= htmlspecialchars($kfs['company']['grievance_officer_name']) ?>, <?= htmlspecialchars($kfs['company']['grievance_officer_phone']) ?></td></tr>
  </table>

  <div class="kfs-sign-row">
    <div class="kfs-sign-box">Client Signature / Thumb Impression</div>
    <div class="kfs-sign-box">Authorized Signatory</div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
