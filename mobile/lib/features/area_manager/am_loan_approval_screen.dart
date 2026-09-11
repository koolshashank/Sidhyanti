import 'package:flutter/material.dart';

import '../bm_approval/proposal_approval_tab.dart';

/// Area Manager's Loan Approval queue -- the same widget the BM Approval
/// hub uses for its Proposals tab, since the server already filters what
/// each of them sees by loan amount (see includes/loan_approval_helper.php):
/// a BM only gets loans at/under the limit, an AM only gets loans above it.
class AmLoanApprovalScreen extends StatelessWidget {
  const AmLoanApprovalScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Loan Approval')),
      body: const ProposalApprovalTab(),
    );
  }
}
