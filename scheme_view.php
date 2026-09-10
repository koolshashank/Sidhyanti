<?php
$pageTitle = 'Scheme Details';
$active = 'schemes';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$productTypes = $pdo->query("SELECT ProductTypeID, ProductTypeName FROM Product_Type_Master ORDER BY ProductTypeName")->fetchAll();

function nz($v) { return ($v === '' || $v === null) ? null : $v; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schemeCode = trim($_POST['scheme_code'] ?? '');
    $schemeName = trim($_POST['scheme_name'] ?? '');
    if ($schemeCode === '' || $schemeName === '') {
        $error = 'Scheme Code and Scheme Name are required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE Loan_Scheme SET
                SchemeCode=:code, SchemeName=:name, ProductTypeID=:product_type, MinLoanAmount=:min_amt, MaxLoanAmount=:max_amt, DefaultLoanAmount=:default_amt,
                TenureMonths=:tenure, InterestRatePercent=:interest_rate, InterestMethod=:interest_method, PaymentFrequency=:frequency,
                ProcessingFeeAmount=:proc_fee, ProcessingFeeGSTPercent=:proc_fee_gst, DocumentChargeAmount=:doc_charge, DocumentChargeGSTPercent=:doc_charge_gst,
                OtherChargesAmount=:other_charges, OtherChargesGSTPercent=:other_charges_gst, LoginFeeAmount=:login_fee, LoginFeeGSTPercent=:login_fee_gst,
                InsuranceFeeAmount=:ins_fee, InsurancePeriodMonths=:ins_period, SumAssuredClient=:sac, SumAssuredGuarantor=:sag, InsuredBy=:insured_by,
                NumInstallmentsSlot1=:slot1_n, InstallmentAmountSlot1=:slot1_amt, NumInstallmentsSlot2=:slot2_n, InstallmentAmountSlot2=:slot2_amt,
                NumInstallmentsSlot3=:slot3_n, InstallmentAmountSlot3=:slot3_amt, EffectiveFrom=:eff_from, EffectiveTo=:eff_to, IsActive=:is_active,
                UpdatedBy=:updated_by, UpdatedAt=GETDATE()
                WHERE SchemeID=:id");
            $stmt->execute([
                ':code' => $schemeCode, ':name' => $schemeName, ':product_type' => nz($_POST['product_type_id']),
                ':min_amt' => nz($_POST['min_amount']), ':max_amt' => nz($_POST['max_amount']), ':default_amt' => nz($_POST['default_amount']),
                ':tenure' => (int)$_POST['tenure_months'], ':interest_rate' => $_POST['interest_rate'], ':interest_method' => nz($_POST['interest_method']),
                ':frequency' => $_POST['payment_frequency'],
                ':proc_fee' => nz($_POST['processing_fee']), ':proc_fee_gst' => nz($_POST['processing_fee_gst']),
                ':doc_charge' => nz($_POST['document_charge']), ':doc_charge_gst' => nz($_POST['document_charge_gst']),
                ':other_charges' => nz($_POST['other_charges']), ':other_charges_gst' => nz($_POST['other_charges_gst']),
                ':login_fee' => nz($_POST['login_fee']), ':login_fee_gst' => nz($_POST['login_fee_gst']),
                ':ins_fee' => nz($_POST['insurance_fee']), ':ins_period' => nz($_POST['insurance_period_months']),
                ':sac' => nz($_POST['sum_assured_client']), ':sag' => nz($_POST['sum_assured_guarantor']), ':insured_by' => nz($_POST['insured_by']),
                ':slot1_n' => nz($_POST['slot1_num']), ':slot1_amt' => nz($_POST['slot1_amount']),
                ':slot2_n' => nz($_POST['slot2_num']), ':slot2_amt' => nz($_POST['slot2_amount']),
                ':slot3_n' => nz($_POST['slot3_num']), ':slot3_amt' => nz($_POST['slot3_amount']),
                ':eff_from' => nz($_POST['effective_from']), ':eff_to' => nz($_POST['effective_to']),
                ':is_active' => isset($_POST['is_active']) ? 1 : 0, ':updated_by' => current_staff()['StaffID'], ':id' => $id,
            ]);
            header("Location: schemes.php?updated=1");
            exit;
        } catch (PDOException $e) {
            $error = 'Could not update scheme — the scheme code may already be in use.';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM Loan_Scheme WHERE SchemeID = ?");
$stmt->execute([$id]);
$s = $stmt->fetch();
if (!$s) { echo '<div class="empty-state">Scheme not found.</div>'; require __DIR__.'/includes/footer.php'; exit; }
$crumb = 'Scheme ' . $s['SchemeCode'];

// POST values take priority on a failed submit so the admin doesn't lose their edits
$v = fn($key) => htmlspecialchars($_POST[$key] ?? $s[$key] ?? '');
?>
<div class="card-x" style="max-width:960px;">
  <div class="form-card-head">
    <div class="form-card-icon">📦</div>
    <div>
      <h5><?= htmlspecialchars($s['SchemeName']) ?></h5>
      <p>Code <?= htmlspecialchars($s['SchemeCode']) ?> · <span class="badge badge-<?= $s['IsActive']?'success':'secondary' ?>"><?= $s['IsActive']?'Active':'Inactive' ?></span></p>
    </div>
  </div>

  <?php if ($error): ?><div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:18px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST">
    <div class="form-section-label"><span class="dot">🏷️</span> Basic Information</div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Scheme Code <span class="required-mark">*</span></label>
        <input class="form-control" name="scheme_code" required value="<?= $v('SchemeCode') ?>">
      </div>
      <div class="col-md-8">
        <label class="form-label">Scheme Name <span class="required-mark">*</span></label>
        <input class="form-control" name="scheme_name" required value="<?= $v('SchemeName') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Product Type</label>
        <select class="form-select" name="product_type_id">
          <option value="">Select Product Type</option>
          <?php foreach ($productTypes as $pt): ?><option value="<?= $pt['ProductTypeID'] ?>" <?= $s['ProductTypeID']==$pt['ProductTypeID']?'selected':'' ?>><?= htmlspecialchars($pt['ProductTypeName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Payment Frequency <span class="required-mark">*</span></label>
        <select class="form-select" name="payment_frequency" required>
          <?php foreach (['Weekly','Fortnightly','Monthly'] as $f): ?><option value="<?= $f ?>" <?= $s['PaymentFrequency']==$f?'selected':'' ?>><?= $f ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Interest Method</label>
        <select class="form-select" name="interest_method">
          <option value="">Not set</option>
          <?php foreach (['Flat','Reducing'] as $m): ?><option value="<?= $m ?>" <?= $s['InterestMethod']==$m?'selected':'' ?>><?= $m ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Tenure (months) <span class="required-mark">*</span></label>
        <input type="number" class="form-control" name="tenure_months" required min="1" value="<?= $v('TenureMonths') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Interest Rate (%) <span class="required-mark">*</span></label>
        <input type="number" step="0.01" class="form-control" name="interest_rate" required value="<?= $v('InterestRatePercent') ?>">
      </div>
    </div>

    <div class="form-section-label"><span class="dot">💰</span> Loan Amount</div>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Min Amount</label><input type="number" step="0.01" class="form-control" name="min_amount" value="<?= $v('MinLoanAmount') ?>"></div>
      <div class="col-md-4"><label class="form-label">Max Amount</label><input type="number" step="0.01" class="form-control" name="max_amount" value="<?= $v('MaxLoanAmount') ?>"></div>
      <div class="col-md-4"><label class="form-label">Default Amount</label><input type="number" step="0.01" class="form-control" name="default_amount" value="<?= $v('DefaultLoanAmount') ?>"></div>
    </div>

    <div class="form-section-label"><span class="dot">🧾</span> Fees &amp; Charges</div>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label">Processing Fee (₹)</label><input type="number" step="0.01" class="form-control" name="processing_fee" value="<?= $v('ProcessingFeeAmount') ?>"></div>
      <div class="col-md-3"><label class="form-label">Processing Fee GST (%)</label><input type="number" step="0.01" class="form-control" name="processing_fee_gst" value="<?= $v('ProcessingFeeGSTPercent') ?>"></div>
      <div class="col-md-3"><label class="form-label">Document Charge (₹)</label><input type="number" step="0.01" class="form-control" name="document_charge" value="<?= $v('DocumentChargeAmount') ?>"></div>
      <div class="col-md-3"><label class="form-label">Document Charge GST (%)</label><input type="number" step="0.01" class="form-control" name="document_charge_gst" value="<?= $v('DocumentChargeGSTPercent') ?>"></div>
      <div class="col-md-3"><label class="form-label">Other Charges (₹)</label><input type="number" step="0.01" class="form-control" name="other_charges" value="<?= $v('OtherChargesAmount') ?>"></div>
      <div class="col-md-3"><label class="form-label">Other Charges GST (%)</label><input type="number" step="0.01" class="form-control" name="other_charges_gst" value="<?= $v('OtherChargesGSTPercent') ?>"></div>
      <div class="col-md-3"><label class="form-label">Login Fee (₹)</label><input type="number" step="0.01" class="form-control" name="login_fee" value="<?= $v('LoginFeeAmount') ?>"></div>
      <div class="col-md-3"><label class="form-label">Login Fee GST (%)</label><input type="number" step="0.01" class="form-control" name="login_fee_gst" value="<?= $v('LoginFeeGSTPercent') ?>"></div>
    </div>

    <div class="form-section-label"><span class="dot">🛡️</span> Insurance</div>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label">Insurance Fee (₹)</label><input type="number" step="0.01" class="form-control" name="insurance_fee" value="<?= $v('InsuranceFeeAmount') ?>"></div>
      <div class="col-md-3"><label class="form-label">Insurance Period (months)</label><input type="number" class="form-control" name="insurance_period_months" value="<?= $v('InsurancePeriodMonths') ?>"></div>
      <div class="col-md-3"><label class="form-label">Sum Assured — Client (₹)</label><input type="number" step="0.01" class="form-control" name="sum_assured_client" value="<?= $v('SumAssuredClient') ?>"></div>
      <div class="col-md-3"><label class="form-label">Sum Assured — Guarantor (₹)</label><input type="number" step="0.01" class="form-control" name="sum_assured_guarantor" value="<?= $v('SumAssuredGuarantor') ?>"></div>
      <div class="col-md-4"><label class="form-label">Insured By</label><input class="form-control" name="insured_by" value="<?= $v('InsuredBy') ?>"></div>
    </div>

    <div class="form-section-label"><span class="dot">🔢</span> Installment Slots <span style="text-transform:none;font-weight:500;color:var(--text-muted);">(optional — for step-up EMI schemes)</span></div>
    <div class="row g-3">
      <div class="col-md-2"><label class="form-label">Slot 1 — # Inst.</label><input type="number" class="form-control" name="slot1_num" value="<?= $v('NumInstallmentsSlot1') ?>"></div>
      <div class="col-md-4"><label class="form-label">Slot 1 — Amount (₹)</label><input type="number" step="0.01" class="form-control" name="slot1_amount" value="<?= $v('InstallmentAmountSlot1') ?>"></div>
      <div class="col-md-2"><label class="form-label">Slot 2 — # Inst.</label><input type="number" class="form-control" name="slot2_num" value="<?= $v('NumInstallmentsSlot2') ?>"></div>
      <div class="col-md-4"><label class="form-label">Slot 2 — Amount (₹)</label><input type="number" step="0.01" class="form-control" name="slot2_amount" value="<?= $v('InstallmentAmountSlot2') ?>"></div>
      <div class="col-md-2"><label class="form-label">Slot 3 — # Inst.</label><input type="number" class="form-control" name="slot3_num" value="<?= $v('NumInstallmentsSlot3') ?>"></div>
      <div class="col-md-4"><label class="form-label">Slot 3 — Amount (₹)</label><input type="number" step="0.01" class="form-control" name="slot3_amount" value="<?= $v('InstallmentAmountSlot3') ?>"></div>
    </div>

    <div class="form-section-label"><span class="dot">📅</span> Validity &amp; Status</div>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Effective From</label><input type="date" class="form-control" name="effective_from" value="<?= $v('EffectiveFrom') ?>"></div>
      <div class="col-md-4"><label class="form-label">Effective To</label><input type="date" class="form-control" name="effective_to" value="<?= $v('EffectiveTo') ?>"></div>
      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveScheme" <?= $s['IsActive']?'checked':'' ?>>
          <label class="form-check-label" for="isActiveScheme" style="font-size:13.5px;font-weight:600;">Scheme is active</label>
        </div>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-navy">Save Changes</button>
      <a href="schemes.php" class="btn-outline-x">Back to Schemes</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
