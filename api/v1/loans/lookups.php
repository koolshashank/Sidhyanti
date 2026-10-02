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

$schemes = $pdo->query("
  SELECT SchemeID, SchemeName, TenureMonths, DefaultLoanAmount, InterestRatePercent, InstallmentAmountSlot1
  FROM Loan_Scheme WHERE IsActive = 1 ORDER BY SchemeName
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
    ], $schemes),
    'purposes' => array_map(fn($p) => ['id' => (int)$p['PurposeID'], 'name' => $p['PurposeName']], $purposes),
]);
