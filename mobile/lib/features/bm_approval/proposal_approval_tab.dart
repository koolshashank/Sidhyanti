import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/loans_api.dart';
import '../../core/models/approval.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'approval_list_tab.dart';

class ProposalApprovalTab extends StatelessWidget {
  const ProposalApprovalTab({super.key});

  @override
  Widget build(BuildContext context) {
    final loansApi = context.read<LoansApi>();
    return ApprovalListTab<LoanApprovalItem>(
      fetch: () => loansApi.pendingApprovals('Proposal'),
      emptyLabel: 'No loan proposals awaiting approval.',
      onReview: (item, approve, remarks) => loansApi.reviewLoanApproval(
        loanApprovalId: item.loanApprovalId,
        approve: approve,
        remarks: remarks,
      ),
      itemBuilder: (context, item) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(item.memberName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5)),
          const SizedBox(height: 2),
          Text('Loan ${item.loanNo}', style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
          if (item.requestedByName != null) ...[
            const SizedBox(height: 4),
            Text('Requested by ${item.requestedByName}', style: TextStyle(fontSize: 12, color: AppColors.textFaint(context))),
          ],
          if (item.createdAt != null) ...[
            const SizedBox(height: 2),
            Text(fdate(item.createdAt), style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context))),
          ],
        ],
      ),
    );
  }
}
