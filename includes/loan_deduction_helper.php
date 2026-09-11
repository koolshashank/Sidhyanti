<?php
/**
 * Computes the fee/charge breakdown a Branch Manager sees before disbursing
 * a loan, from that loan's Loan_Scheme row (schemes.php / scheme_view.php --
 * these amounts are set once per scheme, not per loan).
 *
 * Loan_Scheme has five separate fee lines, each with its own GST%
 * (ProcessingFee, DocumentCharge, OtherCharges, LoginFee) plus a flat
 * InsuranceFeeAmount. To keep the disbursement list to the three buckets
 * Branch Managers actually care about, DocumentCharge/OtherCharges/LoginFee
 * are combined into one "Other Charges" figure -- Processing Fee and
 * Insurance stay their own line since those are the two callouts requested.
 */
function loan_scheme_deductions(?array $scheme): array {
    $withGst = function ($base, $gstPercent) {
        $base = (float)($base ?? 0);
        $gstPercent = (float)($gstPercent ?? 0);
        return $base + ($base * $gstPercent / 100);
    };

    if (!$scheme) {
        return ['processing_fee' => 0.0, 'insurance_amount' => 0.0, 'other_charges' => 0.0, 'total_deductions' => 0.0];
    }

    $processingFee = $withGst($scheme['ProcessingFeeAmount'] ?? null, $scheme['ProcessingFeeGSTPercent'] ?? null);
    $insuranceAmount = (float)($scheme['InsuranceFeeAmount'] ?? 0);
    $otherCharges = $withGst($scheme['DocumentChargeAmount'] ?? null, $scheme['DocumentChargeGSTPercent'] ?? null)
                  + $withGst($scheme['OtherChargesAmount'] ?? null, $scheme['OtherChargesGSTPercent'] ?? null)
                  + $withGst($scheme['LoginFeeAmount'] ?? null, $scheme['LoginFeeGSTPercent'] ?? null);
    $total = $processingFee + $insuranceAmount + $otherCharges;

    return [
        'processing_fee'   => round($processingFee, 2),
        'insurance_amount' => round($insuranceAmount, 2),
        'other_charges'    => round($otherCharges, 2),
        'total_deductions' => round($total, 2),
    ];
}
