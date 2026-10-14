import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/operational_reports_api.dart';
import '../../core/models/operational_report.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'widgets/report_list_scaffold.dart';

class DueDisbursementScreen extends StatelessWidget {
  const DueDisbursementScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<OperationalReportsApi>();
    return ReportListScaffold<DueDisbursementRow>(
      title: 'Due Disbursement',
      fetch: api.dueDisbursement,
      emptyLabel: 'No loans awaiting disbursement 🎉',
      summaryBuilder: (context, items) {
        final total = items.fold<double>(0, (s, i) => s + i.amount);
        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: AppColors.warningTint(context), borderRadius: BorderRadius.circular(14)),
          child: Row(
            children: [
              Text('${items.length} loans', style: const TextStyle(fontWeight: FontWeight.w700)),
              const Spacer(),
              Text(money(total), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
            ],
          ),
        );
      },
      itemBuilder: (context, item) => Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(
          color: AppColors.surface(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border(context)),
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: () => context.push('/loans/${item.loanId}'),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(item.memberName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5)),
                        Text(
                          '${item.loanNo} · ${item.centerName ?? '-'}',
                          style: TextStyle(fontSize: 12, color: AppColors.textMuted(context)),
                        ),
                        Text('Approved ${fdate(item.approvalDate)}', style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context))),
                      ],
                    ),
                  ),
                  Text(money(item.amount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
