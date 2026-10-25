import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/operational_reports_api.dart';
import '../../core/models/operational_report.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'widgets/report_list_scaffold.dart';

/// Loans 90+ days overdue (same "PAR 90+" bucket as the web dashboard) --
/// tap through to a loan's detail screen to request a write-off.
class NpaLoansScreen extends StatelessWidget {
  const NpaLoansScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<OperationalReportsApi>();
    return ReportListScaffold<NpaLoanRow>(
      title: 'NPA Loans',
      fetch: api.npaLoans,
      emptyLabel: 'No NPA loans 🎉',
      summaryBuilder: (context, items) {
        final total = items.fold<double>(0, (s, i) => s + i.outstanding);
        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: const Color(0xFFFDEEEE), borderRadius: BorderRadius.circular(14)),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('90+ days overdue, still Active', style: TextStyle(fontSize: 11, color: AppColors.textMuted(context))),
              const SizedBox(height: 4),
              Row(
                children: [
                  Text('${items.length} loans', style: const TextStyle(fontWeight: FontWeight.w700)),
                  const Spacer(),
                  Text(money(total), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Colors.red)),
                ],
              ),
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
                      Text(money(item.outstanding), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Colors.red)),
                    ],
                  ),
                  Text(
                    '${item.loanNo}${item.loanCycle != null ? ' · Cycle ${item.loanCycle}' : ''} · ${item.centerName ?? '-'} · ${item.officerName ?? 'Unassigned'}',
                    style: TextStyle(fontSize: 12, color: AppColors.textMuted(context)),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Text(
                        '${item.daysOverdue} days overdue',
                        style: TextStyle(fontSize: 11.5, color: Colors.red.shade400, fontWeight: FontWeight.w600),
                      ),
                      if (item.hasPendingWriteOff) ...[
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(color: const Color(0xFFC8952C).withValues(alpha: 0.14), borderRadius: BorderRadius.circular(20)),
                          child: const Text('Write-off pending', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Color(0xFFC8952C))),
                        ),
                      ],
                    ],
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
