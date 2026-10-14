import 'package:flutter/material.dart';

import '../../../core/models/loan.dart';
import '../../../core/utils/formatters.dart';
import '../../../l10n/generated/app_localizations.dart';

class OverdueBanner extends StatelessWidget {
  final OverdueSummary overdue;

  const OverdueBanner({super.key, required this.overdue});

  @override
  Widget build(BuildContext context) {
    if (overdue.count == 0) return const SizedBox.shrink();
    final l10n = AppLocalizations.of(context)!;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.red.shade50,
        border: Border.all(color: Colors.red.shade200),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        children: [
          Icon(Icons.warning_amber_rounded, color: Colors.red.shade700),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              l10n.overdueBannerMessage(overdue.count, money(overdue.amount), overdue.maxDaysOverdue),
              style: TextStyle(color: Colors.red.shade900),
            ),
          ),
        ],
      ),
    );
  }
}
