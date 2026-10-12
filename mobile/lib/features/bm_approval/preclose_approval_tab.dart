import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/loans_api.dart';
import '../../core/models/approval.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'approval_list_tab.dart';

class PrecloseApprovalTab extends StatelessWidget {
  const PrecloseApprovalTab({super.key});

  @override
  Widget build(BuildContext context) {
    final loansApi = context.read<LoansApi>();
    return ApprovalListTab<LoanApprovalItem>(
      fetch: () => loansApi.pendingApprovals('Preclose'),
      emptyLabel: 'No preclose requests awaiting approval.',
      onReview: (item, approve, remarks) => loansApi.reviewLoanApproval(
        loanApprovalId: item.loanApprovalId,
        approve: approve,
        remarks: remarks,
      ),
      itemBuilder: (context, item) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(item.memberName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
              Text(money(item.loanAmount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5, color: Color(0xFFBF360C))),
            ],
          ),
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
          if (item.requestRemarks != null && item.requestRemarks!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(item.requestRemarks!, style: TextStyle(fontSize: 12.5, color: AppColors.textPrimary(context))),
          ],
          if (item.precloseQuote != null) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: AppColors.surfaceAlt(context), borderRadius: BorderRadius.circular(12)),
              child: Column(
                children: [
                  _QuoteRow(
                    label: 'Installments Paid',
                    value: '${item.precloseQuote!.installmentsPaid} / ${item.precloseQuote!.totalInstallments}',
                  ),
                  _QuoteRow(label: 'Principal Outstanding', value: money(item.precloseQuote!.principalOutstanding)),
                  const Divider(height: 16),
                  _QuoteRow(label: 'Amount to Pay (Preclose)', value: money(item.precloseQuote!.precloseAmount), bold: true),
                  _QuoteRow(
                    label: 'Savings on Preclose (Interest)',
                    value: money(item.precloseQuote!.interestSavings),
                    bold: true,
                    color: const Color(0xFF2FBF87),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _QuoteRow extends StatelessWidget {
  final String label;
  final String value;
  final bool bold;
  final Color? color;

  const _QuoteRow({required this.label, required this.value, this.bold = false, this.color});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(fontSize: 12.5, fontWeight: bold ? FontWeight.w700 : FontWeight.w500, color: color ?? AppColors.textMuted(context)),
            ),
          ),
          Text(
            value,
            style: TextStyle(fontSize: 12.5, fontWeight: bold ? FontWeight.w800 : FontWeight.w600, color: color ?? AppColors.textPrimary(context)),
          ),
        ],
      ),
    );
  }
}
