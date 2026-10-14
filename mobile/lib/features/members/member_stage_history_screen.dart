import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/models/member.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../l10n/generated/app_localizations.dart';
import 'member_stage_history_viewmodel.dart';

class MemberStageHistoryScreen extends StatelessWidget {
  final int memberId;

  const MemberStageHistoryScreen({super.key, required this.memberId});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => MemberStageHistoryViewModel(context.read<MembersApi>(), memberId),
      child: const _MemberStageHistoryView(),
    );
  }
}

class _MemberStageHistoryView extends StatelessWidget {
  const _MemberStageHistoryView();

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<MemberStageHistoryViewModel>();
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.memberStageHistoryTitle)),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.error != null
              ? _CenteredMessage(message: vm.error!, onRetry: vm.load)
              : vm.items.isEmpty
                  ? _CenteredMessage(message: l10n.memberStageHistoryEmpty)
                  : RefreshIndicator(
                      onRefresh: vm.load,
                      child: ListView.builder(
                        padding: const EdgeInsets.fromLTRB(16, 20, 16, 24),
                        itemCount: vm.items.length,
                        itemBuilder: (context, index) => _TimelineTile(
                          item: vm.items[index],
                          isFirst: index == 0,
                          isLast: index == vm.items.length - 1,
                        ),
                      ),
                    ),
    );
  }
}

const _typeIcons = {
  'Lead Created': Icons.flag_rounded,
  'Stage Change': Icons.sync_alt_rounded,
  'Interaction': Icons.call_rounded,
  'Note': Icons.sticky_note_2_rounded,
  'KYC Document': Icons.description_rounded,
  'Loan Applied': Icons.work_outline_rounded,
  'Loan Stage Change': Icons.trending_up_rounded,
  'GRT': Icons.groups_rounded,
  'TVR': Icons.phone_in_talk_rounded,
  'Disbursement': Icons.payments_rounded,
  'Payment': Icons.check_circle_rounded,
  'Task Created': Icons.push_pin_rounded,
  'Task Completed': Icons.done_all_rounded,
};

class _TimelineTile extends StatelessWidget {
  final JourneyEvent item;
  final bool isFirst;
  final bool isLast;

  const _TimelineTile({required this.item, required this.isFirst, required this.isLast});

  @override
  Widget build(BuildContext context) {
    final icon = _typeIcons[item.eventType] ?? Icons.circle_outlined;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 28,
                height: 28,
                margin: const EdgeInsets.only(top: 2),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: isFirst ? const Color(0xFFE65100) : AppColors.surface(context),
                  border: Border.all(color: const Color(0xFFE65100), width: 2),
                ),
                child: Icon(icon, size: 14, color: isFirst ? Colors.white : const Color(0xFFE65100)),
              ),
              if (!isLast) Expanded(child: Container(width: 2, color: AppColors.border(context))),
            ],
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 20),
              child: Container(
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
                        Expanded(
                          child: Text(item.eventTitle, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5, color: AppColors.textPrimary(context))),
                        ),
                        if (item.amount != null && item.amount! > 0)
                          Text(money(item.amount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF2FBF87))),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Text(
                          item.eventDate != null ? DateFormat('d MMM y, h:mm a').format(item.eventDate!) : '-',
                          style: TextStyle(fontSize: 12, color: AppColors.textFaint(context)),
                        ),
                        if (item.relatedLoanNo != null) ...[
                          const SizedBox(width: 8),
                          Text('· ${item.relatedLoanNo}', style: const TextStyle(fontSize: 12, color: Color(0xFFC8952C), fontWeight: FontWeight.w600)),
                        ],
                      ],
                    ),
                    if (item.performedBy != null) ...[
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          Icon(Icons.person_outline_rounded, size: 14, color: AppColors.textFaint(context)),
                          const SizedBox(width: 4),
                          Text(item.performedBy!, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                        ],
                      ),
                    ],
                    if (item.eventDetail != null && item.eventDetail!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(item.eventDetail!, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CenteredMessage extends StatelessWidget {
  final String message;
  final VoidCallback? onRetry;

  const _CenteredMessage({required this.message, this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center, style: TextStyle(color: AppColors.textMuted(context))),
            if (onRetry != null) ...[
              const SizedBox(height: 12),
              OutlinedButton(onPressed: onRetry, child: Text(AppLocalizations.of(context)!.commonRetry)),
            ],
          ],
        ),
      ),
    );
  }
}
