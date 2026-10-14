import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/operational_reports_api.dart';
import '../../core/models/operational_report.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'widgets/report_list_scaffold.dart';

class ArrearsClientsScreen extends StatelessWidget {
  const ArrearsClientsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<OperationalReportsApi>();
    return ReportListScaffold<ArrearsClientRow>(
      title: 'Arrears Clients',
      fetch: api.arrearsClients,
      emptyLabel: 'No clients currently in arrears 🎉',
      summaryBuilder: (context, items) {
        final total = items.fold<double>(0, (s, i) => s + i.brokenAmount);
        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: const Color(0xFFFDEEEE), borderRadius: BorderRadius.circular(14)),
          child: Row(
            children: [
              Text('${items.length} loans', style: const TextStyle(fontWeight: FontWeight.w700)),
              const Spacer(),
              Text(money(total), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Colors.red)),
            ],
          ),
        );
      },
      itemBuilder: (context, item) => Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surface(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border(context)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text(item.memberName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
                Text(money(item.brokenAmount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Colors.red)),
              ],
            ),
            Text(
              '${item.loanNo} · ${item.centerName ?? '-'} · ${item.officerName ?? 'Unassigned'}',
              style: TextStyle(fontSize: 12, color: AppColors.textMuted(context)),
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                _Chip(label: 'Broken from #${item.firstBrokenInstallment}'),
                const SizedBox(width: 8),
                _Chip(label: '${item.brokenCount} installments'),
                const SizedBox(width: 8),
                _Chip(label: '${item.daysOverdue} days', color: Colors.red.shade400),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  final String label;
  final Color? color;

  const _Chip({required this.label, this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: (color ?? Colors.grey).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(label, style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: color ?? Colors.grey.shade700)),
    );
  }
}
