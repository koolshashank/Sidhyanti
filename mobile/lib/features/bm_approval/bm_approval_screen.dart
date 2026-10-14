import 'package:flutter/material.dart';

import 'leave_approval_tab.dart';
import 'partial_payment_approval_tab.dart';
import 'preclose_approval_tab.dart';
import 'proposal_approval_tab.dart';

/// Branch Manager's approval hub -- four sign-off queues in one screen:
/// loan proposals, preclose requests, staff leave, and partial payments.
/// (Center approval has its own dedicated screen/card on the Operations grid.)
class BmApprovalScreen extends StatelessWidget {
  /// Opens straight into one tab (and highlights [highlightLoanId] there)
  /// when this screen was reached from a notification tap rather than the
  /// Operations grid -- one of 'proposals', 'preclose', 'leave', 'partial_payments'.
  final String? initialTab;
  final int? highlightLoanId;

  const BmApprovalScreen({super.key, this.initialTab, this.highlightLoanId});

  static const _tabIndex = {'proposals': 0, 'preclose': 1, 'leave': 2, 'partial_payments': 3};

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 4,
      initialIndex: _tabIndex[initialTab] ?? 0,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('BM Approval'),
          bottom: const TabBar(
            isScrollable: true,
            tabs: [
              Tab(text: 'Proposals'),
              Tab(text: 'Preclose'),
              Tab(text: 'Leave'),
              Tab(text: 'Partial Payments'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            const ProposalApprovalTab(),
            PrecloseApprovalTab(highlightLoanId: highlightLoanId),
            const LeaveApprovalTab(),
            PartialPaymentApprovalTab(highlightLoanId: highlightLoanId),
          ],
        ),
      ),
    );
  }
}
