import 'package:flutter/material.dart';

import '../../../core/models/loan.dart';
import '../../../core/utils/formatters.dart';
import '../../../l10n/generated/app_localizations.dart';

class RepaymentScheduleTable extends StatelessWidget {
  final List<RepaymentInstallment> schedule;

  const RepaymentScheduleTable({super.key, required this.schedule});

  Color _statusColor(String status) {
    switch (status) {
      case 'Paid':
        return Colors.green;
      case 'Overdue':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        columnSpacing: 20,
        columns: [
          DataColumn(label: Text(l10n.repaymentScheduleColNo)),
          DataColumn(label: Text(l10n.repaymentScheduleColDueDate)),
          DataColumn(label: Text(l10n.repaymentScheduleColEmi), numeric: true),
          DataColumn(label: Text(l10n.repaymentScheduleColStatus)),
          DataColumn(label: Text(l10n.repaymentScheduleColPaidOn)),
        ],
        rows: schedule.map((row) {
          final color = _statusColor(row.status);
          return DataRow(cells: [
            DataCell(Text('${row.installmentNo}')),
            DataCell(Text(fdate(row.dueDate))),
            DataCell(Text(money(row.emiAmount))),
            DataCell(Chip(
              label: Text(row.status, style: TextStyle(color: color, fontSize: 12)),
              backgroundColor: color.withValues(alpha: 0.1),
              visualDensity: VisualDensity.compact,
              side: BorderSide.none,
            )),
            DataCell(Text(row.collectionDate != null ? fdate(row.collectionDate) : '-')),
          ]);
        }).toList(),
      ),
    );
  }
}
