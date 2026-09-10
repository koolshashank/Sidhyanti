import 'package:flutter/material.dart';

import 'leave_approval_tab.dart';
import 'partial_payment_approval_tab.dart';
import 'preclose_approval_tab.dart';
import 'proposal_approval_tab.dart';

/// Branch Manager's approval hub -- four sign-off queues in one screen:
/// loan proposals, preclose requests, staff leave, and partial payments.
/// (Center approval has its own dedicated screen/card on the Operations grid.)
class BmApprovalScreen extends StatelessWidget {
  const BmApprovalScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 4,
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
        body: const TabBarView(
          children: [
            ProposalApprovalTab(),
            PrecloseApprovalTab(),
            LeaveApprovalTab(),
            PartialPaymentApprovalTab(),
          ],
        ),
      ),
    );
  }
}
