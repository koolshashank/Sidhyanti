<?php
/**
 * Masks a bank account number for display, keeping only the last 4 digits
 * visible (e.g. "1234567890" -> "XXXXXX7890") so it can't be read off the
 * screen and typed back in as the "confirm account number" step. Shared by
 * the mobile API (api/v1/loans/pending_disbursement.php) and the web CRM
 * (loan_view.php) -- side-effect-free, safe to require from either context.
 */
function mask_account_number(string $accountNo): string {
    $len = strlen($accountNo);
    if ($len <= 4) {
        return $accountNo;
    }
    return str_repeat('X', $len - 4) . substr($accountNo, -4);
}
