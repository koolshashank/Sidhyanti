import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/models/member.dart';
import '../../core/theme/app_colors.dart';
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

    return Scaffold(
      appBar: AppBar(title: const Text('Lead Stage History')),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.error != null
              ? _CenteredMessage(message: vm.error!, onRetry: vm.load)
              : vm.items.isEmpty
                  ? const _CenteredMessage(message: 'No stage history found for this member.')
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

class _TimelineTile extends StatelessWidget {
  final MemberStageHistoryItem item;
  final bool isFirst;
  final bool isLast;

  const _TimelineTile({required this.item, required this.isFirst, required this.isLast});

  @override
  Widget build(BuildContext context) {
    final title = item.fromStage == null
        ? item.toStage ?? 'Created'
        : '${item.fromStage} → ${item.toStage}';

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 16,
                height: 16,
                margin: const EdgeInsets.only(top: 4),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: isFirst ? const Color(0xFFE65100) : AppColors.surface(context),
                  border: Border.all(color: const Color(0xFFE65100), width: 2.5),
                ),
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
                    Text(title, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14.5, color: AppColors.textPrimary(context))),
                    const SizedBox(height: 4),
                    Text(
                      item.changedAt != null ? DateFormat('d MMM y, h:mm a').format(item.changedAt!) : '-',
                      style: TextStyle(fontSize: 12, color: AppColors.textFaint(context)),
                    ),
                    if (item.changedBy != null) ...[
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          Icon(Icons.person_outline_rounded, size: 14, color: AppColors.textFaint(context)),
                          const SizedBox(width: 4),
                          Text(item.changedBy!, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                        ],
                      ),
                    ],
                    if (item.remarks != null && item.remarks!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(item.remarks!, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context), fontStyle: FontStyle.italic)),
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
              OutlinedButton(onPressed: onRetry, child: const Text('Retry')),
            ],
          ],
        ),
      ),
    );
  }
}
