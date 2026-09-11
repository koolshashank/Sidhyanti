<?php
/**
 * Amount-based routing for Loan_Approval rows of RequestType='Proposal':
 * a Branch Manager can approve up to and including LOAN_PROPOSAL_BM_LIMIT,
 * an Area Manager approves everything above it. Preclose/Leave/Partial
 * Payment approvals are unaffected -- this only applies to new loan
 * proposals, per the original request.
 */
const LOAN_PROPOSAL_BM_LIMIT = 20000;

/**
 * Extra SQL condition (starting with "AND") to append to a Proposal-queue
 * query so a Branch Manager only sees loans at/under the limit and an Area
 * Manager only sees loans above it. $alias is the Loan table alias.
 * Returns '' for every other role (no extra filtering).
 */
function loan_proposal_amount_condition(array $claims, string $alias = 'l'): string {
    $roleName = $claims['RoleName'] ?? '';
    $amountExpr = "COALESCE($alias.LoanAmountSanctioned, $alias.LoanAmountApplied)";
    if ($roleName === 'Branch Manager') {
        return "AND $amountExpr <= " . LOAN_PROPOSAL_BM_LIMIT;
    }
    if ($roleName === 'Area Manager') {
        return "AND $amountExpr > " . LOAN_PROPOSAL_BM_LIMIT;
    }
    return '';
}

/**
 * Server-side re-check for the actual approve/reject action (the list
 * query above only controls what's *shown* -- this is what actually stops
 * a Branch Manager approving a loan above their limit via a direct API
 * call). Returns an error message string if not allowed, null if allowed.
 */
function loan_proposal_amount_violation(array $claims, float $loanAmount): ?string {
    $roleName = $claims['RoleName'] ?? '';
    if ($roleName === 'Branch Manager' && $loanAmount > LOAN_PROPOSAL_BM_LIMIT) {
        return 'This loan is above ₹' . number_format(LOAN_PROPOSAL_BM_LIMIT) . ' -- only an Area Manager can approve it';
    }
    if ($roleName === 'Area Manager' && $loanAmount <= LOAN_PROPOSAL_BM_LIMIT) {
        return 'This loan is ₹' . number_format(LOAN_PROPOSAL_BM_LIMIT) . ' or below -- a Branch Manager should approve it';
    }
    return null;
}
