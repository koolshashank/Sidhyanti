import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/operational_reports_api.dart';
import '../../core/models/operational_report.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'widgets/report_list_scaffold.dart';

class TodayPendingCollectionScreen extends StatelessWidget {
  const TodayPendingCollectionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<OperationalReportsApi>();
    return ReportListScaffold<TodayPendingRow>(
      title: "Today's Pending Collection",
      fetch: api.todayPendingCollection,
      emptyLabel: 'No pending collections for today 🎉',
      summaryBuilder: (context, items) {
        final total = items.fold<double>(0, (s, i) => s + i.emiAmount);
        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: AppColors.warningTint(context), borderRadius: BorderRadius.circular(14)),
          child: Row(
            children: [
              Text('${items.length} installments', style: const TextStyle(fontWeight: FontWeight.w700)),
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
                        Text(item.borrowerName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5)),
                        Text(
                          '${item.loanNo} · ${item.centerName ?? '-'}',
                          style: TextStyle(fontSize: 12, color: AppColors.textMuted(context)),
                        ),
                        if (item.officerName != null) Text(item.officerName!, style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context))),
                      ],
                    ),
                  ),
                  Text(money(item.emiAmount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
