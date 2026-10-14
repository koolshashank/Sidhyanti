import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/operational_reports_api.dart';
import '../../core/models/operational_report.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'widgets/report_list_scaffold.dart';

class DefaultCollectionScreen extends StatelessWidget {
  const DefaultCollectionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<OperationalReportsApi>();
    return ReportListScaffold<DefaultCollectionRow>(
      title: 'Default Collection (30 days)',
      fetch: api.defaultCollection,
      emptyLabel: 'No default-loan recoveries in the last 30 days.',
      summaryBuilder: (context, items) {
        final total = items.fold<double>(0, (s, i) => s + i.amountCollected);
        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: const Color(0xFFE8F7F0), borderRadius: BorderRadius.circular(14)),
          child: Row(
            children: [
              Text('${items.length} payments', style: const TextStyle(fontWeight: FontWeight.w700)),
              const Spacer(),
              Text(money(total), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF2FBF87))),
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
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(child: Text(item.memberName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
                      Text(money(item.amountCollected), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF2FBF87))),
                    ],
                  ),
                  Text(
                    '${item.loanNo}${item.loanCycle != null ? ' · Cycle ${item.loanCycle}' : ''} · ${item.centerName ?? '-'} · ${item.officerName ?? 'Unassigned'}',
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted(context)),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Tenure ended ${fdate(item.tenureEndedOn)} · Collected ${fdate(item.collectionDate)}',
                    style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context), fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
