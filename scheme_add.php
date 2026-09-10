<?php
$pageTitle = 'New Scheme';
$crumb = 'Configure a new lending product';
$active = 'schemes';
require_once __DIR__ . '/includes/header.php';

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
            $stmt = $pdo->prepare("INSERT INTO Loan_Scheme (
                SchemeCode, SchemeName, ProductTypeID, MinLoanAmount, MaxLoanAmount, DefaultLoanAmount,
                TenureMonths, InterestRatePercent, InterestMethod, PaymentFrequency,
                ProcessingFeeAmount, ProcessingFeeGSTPercent, DocumentChargeAmount, DocumentChargeGSTPercent,
                OtherChargesAmount, OtherChargesGSTPercent, LoginFeeAmount, LoginFeeGSTPercent,
                InsuranceFeeAmount, InsurancePeriodMonths, SumAssuredClient, SumAssuredGuarantor, InsuredBy,
                NumInstallmentsSlot1, InstallmentAmountSlot1, NumInstallmentsSlot2, InstallmentAmountSlot2,
                NumInstallmentsSlot3, InstallmentAmountSlot3, EffectiveFrom, EffectiveTo, IsActive, CreatedBy, CreatedAt
            ) VALUES (
                :code, :name, :product_type, :min_amt, :max_amt, :default_amt,
                :tenure, :interest_rate, :interest_method, :frequency,
                :proc_fee, :proc_fee_gst, :doc_charge, :doc_charge_gst,
                :other_charges, :other_charges_gst, :login_fee, :login_fee_gst,
                :ins_fee, :ins_period, :sac, :sag, :insured_by,
                :slot1_n, :slot1_amt, :slot2_n, :slot2_amt,
                :slot3_n, :slot3_amt, :eff_from, :eff_to, :is_active, :created_by, GETDATE()
            )");
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
                ':is_active' => isset($_POST['is_active']) ? 1 : 0, ':created_by' => current_staff()['StaffID'],
            ]);
            header('Location: schemes.php?created=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Could not create scheme — the scheme code may already be in use.';
        }
    }
}
?>
<div class="card-x" style="max-width:960px;">
  <div class="form-card-head">
    <div class="form-card-icon">📦</div>
    <div>
      <h5>Scheme Details</h5>
      <p>Configure a new lending product that loans can be issued against.</p>
    </div>
  </div>

  <?php if ($error): ?><div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:18px;font-size:13px;font-weight:600;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST">
    <div class="form-section-label"><span class="dot">🏷️</span> Basic Information</div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Scheme Code <span class="required-mark">*</span></label>
        <input class="form-control" name="scheme_code" required value="<?= htmlspecialchars($_POST['scheme_code'] ?? '') ?>" placeholder="e.g. SCH-35">
      </div>
      <div class="col-md-8">
        <label class="form-label">Scheme Name <span class="required-mark">*</span></label>
        <input class="form-control" name="scheme_name" required value="<?= htmlspecialchars($_POST['scheme_name'] ?? '') ?>" placeholder="e.g. JLG #5">
      </div>
      <div class="col-md-4">
        <label class="form-label">Product Type</label>
        <select class="form-select" name="product_type_id">
          <option value="">Select Product Type</option>
          <?php foreach ($productTypes as $pt): ?><option value="<?= $pt['ProductTypeID'] ?>"><?= htmlspecialchars($pt['ProductTypeName']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Payment Frequency <span class="required-mark">*</span></label>
        <select class="form-select" name="payment_frequency" required>
          <option value="Weekly">Weekly</option>
          <option value="Fortnightly">Fortnightly</option>
          <option value="Monthly">Monthly</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Interest Method</label>
        <select class="form-select" name="interest_method">
          <option value="">Not set</option>
          <option value="Flat">Flat</option>
          <option value="Reducing">Reducing</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Tenure (months) <span class="required-mark">*</span></label>
        <input type="number" class="form-control" name="tenure_months" required min="1" value="<?= htmlspecialchars($_POST['tenure_months'] ?? '26') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Interest Rate (%) <span class="required-mark">*</span></label>
        <input type="number" step="0.01" class="form-control" name="interest_rate" required value="<?= htmlspecialchars($_POST['interest_rate'] ?? '24') ?>">
      </div>
    </div>

    <div class="form-section-label"><span class="dot">💰</span> Loan Amount</div>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Min Amount</label><input type="number" step="0.01" class="form-control" name="min_amount" value="<?= htmlspecialchars($_POST['min_amount'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Max Amount</label><input type="number" step="0.01" class="form-control" name="max_amount" value="<?= htmlspecialchars($_POST['max_amount'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Default Amount</label><input type="number" step="0.01" class="form-control" name="default_amount" value="<?= htmlspecialchars($_POST['default_amount'] ?? '') ?>"></div>
    </div>

    <div class="form-section-label"><span class="dot">🧾</span> Fees &amp; Charges</div>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label">Processing Fee (₹)</label><input type="number" step="0.01" class="form-control" name="processing_fee" value="<?= htmlspecialchars($_POST['processing_fee'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Processing Fee GST (%)</label><input type="number" step="0.01" class="form-control" name="processing_fee_gst" value="<?= htmlspecialchars($_POST['processing_fee_gst'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Document Charge (₹)</label><input type="number" step="0.01" class="form-control" name="document_charge" value="<?= htmlspecialchars($_POST['document_charge'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Document Charge GST (%)</label><input type="number" step="0.01" class="form-control" name="document_charge_gst" value="<?= htmlspecialchars($_POST['document_charge_gst'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Other Charges (₹)</label><input type="number" step="0.01" class="form-control" name="other_charges" value="<?= htmlspecialchars($_POST['other_charges'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Other Charges GST (%)</label><input type="number" step="0.01" class="form-control" name="other_charges_gst" value="<?= htmlspecialchars($_POST['other_charges_gst'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Login Fee (₹)</label><input type="number" step="0.01" class="form-control" name="login_fee" value="<?= htmlspecialchars($_POST['login_fee'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Login Fee GST (%)</label><input type="number" step="0.01" class="form-control" name="login_fee_gst" value="<?= htmlspecialchars($_POST['login_fee_gst'] ?? '') ?>"></div>
    </div>

    <div class="form-section-label"><span class="dot">🛡️</span> Insurance</div>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label">Insurance Fee (₹)</label><input type="number" step="0.01" class="form-control" name="insurance_fee" value="<?= htmlspecialchars($_POST['insurance_fee'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Insurance Period (months)</label><input type="number" class="form-control" name="insurance_period_months" value="<?= htmlspecialchars($_POST['insurance_period_months'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Sum Assured — Client (₹)</label><input type="number" step="0.01" class="form-control" name="sum_assured_client" value="<?= htmlspecialchars($_POST['sum_assured_client'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Sum Assured — Guarantor (₹)</label><input type="number" step="0.01" class="form-control" name="sum_assured_guarantor" value="<?= htmlspecialchars($_POST['sum_assured_guarantor'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Insured By</label><input class="form-control" name="insured_by" value="<?= htmlspecialchars($_POST['insured_by'] ?? '') ?>" placeholder="e.g. LIC, HDFC Ergo"></div>
    </div>

    <div class="form-section-label"><span class="dot">🔢</span> Installment Slots <span style="text-transform:none;font-weight:500;color:var(--text-muted);">(optional — for step-up EMI schemes)</span></div>
    <div class="row g-3">
      <div class="col-md-2"><label class="form-label">Slot 1 — # Inst.</label><input type="number" class="form-control" name="slot1_num" value="<?= htmlspecialchars($_POST['slot1_num'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Slot 1 — Amount (₹)</label><input type="number" step="0.01" class="form-control" name="slot1_amount" value="<?= htmlspecialchars($_POST['slot1_amount'] ?? '') ?>"></div>
      <div class="col-md-2"><label class="form-label">Slot 2 — # Inst.</label><input type="number" class="form-control" name="slot2_num" value="<?= htmlspecialchars($_POST['slot2_num'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Slot 2 — Amount (₹)</label><input type="number" step="0.01" class="form-control" name="slot2_amount" value="<?= htmlspecialchars($_POST['slot2_amount'] ?? '') ?>"></div>
      <div class="col-md-2"><label class="form-label">Slot 3 — # Inst.</label><input type="number" class="form-control" name="slot3_num" value="<?= htmlspecialchars($_POST['slot3_num'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Slot 3 — Amount (₹)</label><input type="number" step="0.01" class="form-control" name="slot3_amount" value="<?= htmlspecialchars($_POST['slot3_amount'] ?? '') ?>"></div>
    </div>

    <div class="form-section-label"><span class="dot">📅</span> Validity &amp; Status</div>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Effective From</label><input type="date" class="form-control" name="effective_from" value="<?= htmlspecialchars($_POST['effective_from'] ?? date('Y-m-d')) ?>"></div>
      <div class="col-md-4"><label class="form-label">Effective To</label><input type="date" class="form-control" name="effective_to" value="<?= htmlspecialchars($_POST['effective_to'] ?? '') ?>"></div>
      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="isActiveScheme" checked>
          <label class="form-check-label" for="isActiveScheme" style="font-size:13.5px;font-weight:600;">Scheme is active</label>
        </div>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn-navy">➕ Create Scheme</button>
      <a href="schemes.php" class="btn-outline-x">Cancel</a>
    </div>
  </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
