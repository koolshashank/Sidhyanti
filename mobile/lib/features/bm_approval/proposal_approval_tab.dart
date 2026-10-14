import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/loans_api.dart';
import '../../core/models/approval.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../l10n/generated/app_localizations.dart';
import 'approval_list_tab.dart';

class ProposalApprovalTab extends StatelessWidget {
  const ProposalApprovalTab({super.key});

  @override
  Widget build(BuildContext context) {
    final loansApi = context.read<LoansApi>();
    final l10n = AppLocalizations.of(context)!;
    return ApprovalListTab<LoanApprovalItem>(
      fetch: () => loansApi.pendingApprovals('Proposal'),
      emptyLabel: l10n.bmApprovalProposalsEmptyLabel,
      onReview: (item, approve, remarks) => loansApi.reviewLoanApproval(
        loanApprovalId: item.loanApprovalId,
        approve: approve,
        remarks: remarks,
      ),
      onItemTap: (context, item) => context.push<bool>('/loans/${item.loanId}/review'),
      showInlineActions: false,
      itemBuilder: (context, item) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(item.memberName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
              Text(money(item.loanAmount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF2FBF87))),
            ],
          ),
          const SizedBox(height: 2),
          Text(l10n.bmApprovalLoanNo(item.loanNo), style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
          if (item.requestedByName != null) ...[
            const SizedBox(height: 4),
            Text(l10n.bmApprovalRequestedBy(item.requestedByName!), style: TextStyle(fontSize: 12, color: AppColors.textFaint(context))),
          ],
          if (item.createdAt != null) ...[
            const SizedBox(height: 2),
            Text(fdate(item.createdAt), style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context))),
          ],
          const SizedBox(height: 6),
          Row(
            children: [
              Icon(Icons.visibility_outlined, size: 13, color: Colors.grey.shade500),
              const SizedBox(width: 4),
              Text(l10n.bmApprovalProposalTapHint, style: TextStyle(fontSize: 11, color: Colors.grey.shade500, fontStyle: FontStyle.italic)),
            ],
          ),
        ],
      ),
    );
  }
}
