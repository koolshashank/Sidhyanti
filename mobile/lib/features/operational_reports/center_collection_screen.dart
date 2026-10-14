import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/operational_reports_api.dart';
import '../../core/models/operational_report.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'widgets/report_list_scaffold.dart';

class CenterCollectionScreen extends StatelessWidget {
  const CenterCollectionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<OperationalReportsApi>();
    return ReportListScaffold<CenterCollectionRow>(
      title: 'Center-wise Collection (30 days)',
      fetch: api.centerCollection,
      emptyLabel: 'No collection activity in the last 30 days.',
      summaryBuilder: (context, items) {
        final due = items.fold<double>(0, (s, i) => s + i.dueAmount);
        final collected = items.fold<double>(0, (s, i) => s + i.collectedAmount);
        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: AppColors.surfaceAlt(context), borderRadius: BorderRadius.circular(14)),
          child: Row(
            children: [
              Expanded(child: _Stat(label: 'Due', value: money(due))),
              Expanded(child: _Stat(label: 'Collected', value: money(collected), color: const Color(0xFF2FBF87))),
              Expanded(child: _Stat(label: 'Balance', value: money(due - collected), color: Colors.red.shade400)),
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
                Expanded(child: Text(item.centerName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
                Text('${item.clientCount} clients', style: TextStyle(fontSize: 12, color: AppColors.textMuted(context))),
              ],
            ),
            if (item.branchName != null) Text(item.branchName!, style: TextStyle(fontSize: 12, color: AppColors.textFaint(context))),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(child: _Stat(label: 'Due', value: money(item.dueAmount))),
                Expanded(child: _Stat(label: 'Collected', value: money(item.collectedAmount), color: const Color(0xFF2FBF87))),
                Expanded(child: _Stat(label: 'Balance', value: money(item.balance), color: Colors.red.shade400)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  final String label;
  final String value;
  final Color? color;

  const _Stat({required this.label, required this.value, this.color});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: TextStyle(fontSize: 10.5, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
        Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: color ?? AppColors.textPrimary(context))),
      ],
    );
  }
}
