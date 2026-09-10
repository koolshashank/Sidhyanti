import 'package:flutter/material.dart';

import '../../../core/models/loan.dart';
import '../../../core/utils/formatters.dart';

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
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        columnSpacing: 20,
        columns: const [
          DataColumn(label: Text('#')),
          DataColumn(label: Text('Due Date')),
          DataColumn(label: Text('EMI'), numeric: true),
          DataColumn(label: Text('Status')),
          DataColumn(label: Text('Paid On')),
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
