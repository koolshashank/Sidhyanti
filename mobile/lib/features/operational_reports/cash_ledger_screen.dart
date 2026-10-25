import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/operational_reports_api.dart';
import '../../core/models/operational_report.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'widgets/report_list_scaffold.dart';

/// Last 30 days of the branch's Day_Closing_Report rows, most recent
/// first -- a running cash ledger (see cash_ledger.php for the web
/// equivalent, which also has a trend chart).
class CashLedgerScreen extends StatelessWidget {
  const CashLedgerScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<OperationalReportsApi>();
    return ReportListScaffold<CashLedgerDay>(
      title: 'Cash Ledger',
      fetch: () async {
        final items = await api.cashLedger();
        return items.reversed.toList();
      },
      emptyLabel: 'No DCR submissions in the last 30 days.',
      itemBuilder: (context, day) => Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surface(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border(context)),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(fdate(day.closingDate), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
                  const SizedBox(height: 4),
                  Text(
                    'In ${money(day.totalInflow)} · Out ${money(day.totalOutflow)}',
                    style: TextStyle(fontSize: 11.5, color: AppColors.textMuted(context)),
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(money(day.grandTotal), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF14A9AB))),
                Text('Closing Balance', style: TextStyle(fontSize: 10, color: AppColors.textFaint(context))),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
