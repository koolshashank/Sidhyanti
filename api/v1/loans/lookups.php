<?php
/**
 * GET /api/v1/loans/lookups.php -- lookups needed by the mobile "Loan
 * Proposal" screen: Product (Loan_Scheme) and Nature of Business
 * (Loan_Purpose_Master).
 */

require_once __DIR__ . '/../../includes/api_functions.php';
require_once __DIR__ . '/../../includes/serialize_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, null, 'Method not allowed', 405);
}

require_api_auth();

// ProductTypeID/Name added so the mobile Loan Proposal screen can offer a
// Product Type -> Product (scheme) two-step picker instead of one flat list.
$schemes = $pdo->query("
  SELECT s.SchemeID, s.SchemeName, s.TenureMonths, s.DefaultLoanAmount, s.InterestRatePercent, s.InstallmentAmountSlot1,
         s.ProductTypeID, pt.ProductTypeName
  FROM Loan_Scheme s
  LEFT JOIN Product_Type_Master pt ON s.ProductTypeID = pt.ProductTypeID
  WHERE s.IsActive = 1
  ORDER BY pt.ProductTypeName, s.SchemeName
")->fetchAll();

$purposes = $pdo->query("SELECT PurposeID, PurposeName FROM Loan_Purpose_Master ORDER BY PurposeName")->fetchAll();

json_response(true, [
    'schemes' => array_map(fn($s) => [
        'id' => (int)$s['SchemeID'],
        'name' => $s['SchemeName'],
        'tenure_months' => (int)$s['TenureMonths'],
        'default_amount' => to_money($s['DefaultLoanAmount']),
        'interest_rate_percent' => to_money($s['InterestRatePercent']),
        'emi_amount' => $s['InstallmentAmountSlot1'] !== null ? to_money($s['InstallmentAmountSlot1']) : null,
        'product_type_id' => $s['ProductTypeID'] !== null ? (int)$s['ProductTypeID'] : null,
        'product_type_name' => $s['ProductTypeName'],
    ], $schemes),
    'purposes' => array_map(fn($p) => ['id' => (int)$p['PurposeID'], 'name' => $p['PurposeName']], $purposes),
]);
