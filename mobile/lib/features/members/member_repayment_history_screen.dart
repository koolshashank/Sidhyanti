import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/models/member.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'member_repayment_history_viewmodel.dart';

class MemberRepaymentHistoryScreen extends StatelessWidget {
  final int memberId;

  const MemberRepaymentHistoryScreen({super.key, required this.memberId});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => MemberRepaymentHistoryViewModel(context.read<MembersApi>(), memberId),
      child: const _MemberRepaymentHistoryView(),
    );
  }
}

class _MemberRepaymentHistoryView extends StatelessWidget {
  const _MemberRepaymentHistoryView();

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<MemberRepaymentHistoryViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('Repayment History')),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.error != null
              ? _CenteredMessage(message: vm.error!, onRetry: vm.load)
              : vm.items.isEmpty
                  ? const _CenteredMessage(message: 'No repayment schedule found for this member.')
                  : RefreshIndicator(
                      onRefresh: vm.load,
                      child: _buildGroupedList(context, vm.items),
                    ),
    );
  }

  Widget _buildGroupedList(BuildContext context, List<MemberRepaymentItem> items) {
    final byLoan = <String, List<MemberRepaymentItem>>{};
    for (final item in items) {
      byLoan.putIfAbsent(item.loanNo, () => []).add(item);
    }

    return ListView(
      padding: const EdgeInsets.all(16),
      children: byLoan.entries.map((entry) {
        final loanItems = entry.value;
        final paidCount = loanItems.where((i) => i.isPaid).length;
        return Container(
          margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(
            color: AppColors.surface(context),
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border(context)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(entry.key, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                    ),
                    Text(
                      '$paidCount/${loanItems.length} paid',
                      style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context), fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),
              ...loanItems.map((item) => _InstallmentRow(item: item)),
            ],
          ),
        );
      }).toList(),
    );
  }
}

class _InstallmentRow extends StatelessWidget {
  final MemberRepaymentItem item;

  const _InstallmentRow({required this.item});

  Color get _statusColor {
    switch (item.status) {
      case 'Paid':
        return const Color(0xFF2FBF87);
      case 'Overdue':
        return Colors.red.shade400;
      default:
        return const Color(0xFFC8952C);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        children: [
          Container(
            width: 30,
            height: 30,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: _statusColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(9)),
            child: Text('${item.installmentNo}', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: _statusColor)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Due ${fdate(item.dueDate)}', style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                if (item.isPaid && item.collectionDate != null)
                  Text('Collected ${fdate(item.collectionDate)}', style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context))),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(money(item.emiAmount), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
              Container(
                margin: const EdgeInsets.only(top: 2),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(color: _statusColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                child: Text(item.status, style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: _statusColor)),
              ),
            ],
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
