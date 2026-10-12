import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/collections_api.dart';
import '../../core/models/approval.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'approval_list_tab.dart';

class PartialPaymentApprovalTab extends StatelessWidget {
  const PartialPaymentApprovalTab({super.key});

  @override
  Widget build(BuildContext context) {
    final collectionsApi = context.read<CollectionsApi>();
    return ApprovalListTab<PartialPaymentApprovalItem>(
      fetch: collectionsApi.pendingPartialPayments,
      emptyLabel: 'No partial payments awaiting approval.',
      onReview: (item, approve, remarks) => collectionsApi.reviewPartialPayment(
        partialPaymentApprovalId: item.partialPaymentApprovalId,
        approve: approve,
        remarks: remarks,
      ),
      itemBuilder: (context, item) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(item.memberName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
              Text(money(item.requestedAmount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFFFF6F4A))),
            ],
          ),
          const SizedBox(height: 2),
          Text(
            'Loan ${item.loanNo} · Installment #${item.installmentNo} · EMI due ${money(item.emiAmount)}',
            style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
          ),
          if (item.remarks != null && item.remarks!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(item.remarks!, style: TextStyle(fontSize: 12, color: AppColors.textMuted(context))),
          ],
          if (item.requestedByName != null) ...[
            const SizedBox(height: 4),
            Text('Collected by ${item.requestedByName} on ${fdate(item.collectionDate)}', style: TextStyle(fontSize: 12, color: AppColors.textFaint(context))),
          ],
          if (item.overdueInstallmentCount > 0) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: AppColors.surfaceAlt(context), borderRadius: BorderRadius.circular(12)),
              child: Column(
                children: [
                  _ArrearRow(label: 'Broken From Installment', value: '#${item.firstOverdueInstallmentNo}'),
                  _ArrearRow(label: 'Installments Overdue', value: '${item.overdueInstallmentCount}'),
                  const Divider(height: 16),
                  _ArrearRow(label: 'Total Arrear', value: money(item.arrearAmount), bold: true, color: Colors.red.shade400),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ArrearRow extends StatelessWidget {
  final String label;
  final String value;
  final bool bold;
  final Color? color;

  const _ArrearRow({required this.label, required this.value, this.bold = false, this.color});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(
            child: Text(label, style: TextStyle(fontSize: 12.5, fontWeight: bold ? FontWeight.w700 : FontWeight.w500, color: color ?? AppColors.textMuted(context))),
          ),
          Text(value, style: TextStyle(fontSize: 12.5, fontWeight: bold ? FontWeight.w800 : FontWeight.w600, color: color ?? AppColors.textPrimary(context))),
        ],
      ),
    );
  }
}
