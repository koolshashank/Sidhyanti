import 'package:flutter/material.dart';

import '../../../core/models/collection_due_item.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../collect_arrear_sheet.dart';
import '../record_payment_sheet.dart';

/// A single borrower row shared by both the Collection-tab center detail
/// screen and the Arrear-tab center detail screen -- shows the EMI/overdue
/// amount, a Collect EMI action (or a Paid badge once collected), and an
/// expandable Total Disbursed / Total Collected / Remaining breakdown.
class CollectionBorrowerTile extends StatefulWidget {
  final CollectionDueItem item;
  final void Function(int scheduleId, double amount) onCollected;

  const CollectionBorrowerTile({super.key, required this.item, required this.onCollected});

  @override
  State<CollectionBorrowerTile> createState() => _CollectionBorrowerTileState();
}

class _CollectionBorrowerTileState extends State<CollectionBorrowerTile> {
  bool _expanded = false;

  Future<void> _collect() async {
    final isOverdueRow = widget.item.status == 'Overdue';
    if (isOverdueRow) {
      final result = await showCollectArrearSheet(context, widget.item);
      if (result != null && mounted) {
        if (result.installmentsCleared > 0) {
          widget.onCollected(widget.item.scheduleId, 0);
        }
        final message = result.hasPendingApproval
            ? '${result.installmentsCleared} installment(s) cleared — remainder pending Branch Manager approval'
            : '${result.installmentsCleared} installment(s) cleared';
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          backgroundColor: result.hasPendingApproval ? const Color(0xFFC8952C) : null,
          content: Text(message),
        ));
      }
      return;
    }

    final result = await showRecordPaymentSheet(context, widget.item);
    if (result != null && mounted) {
      final isPending = result.status == 'PendingApproval';
      if (!isPending) {
        widget.onCollected(result.scheduleId, result.collectedAmount);
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        backgroundColor: isPending ? const Color(0xFFC8952C) : null,
        content: Text(isPending ? 'Submitted — pending Branch Manager approval' : 'Payment recorded'),
      ));
    }
  }

  String get _initials {
    final trimmed = widget.item.borrowerName.trim();
    if (trimmed.isEmpty) return '?';
    final parts = trimmed.split(RegExp(r'\s+'));
    final first = parts.first.isNotEmpty ? parts.first[0] : '';
    final second = parts.length > 1 && parts.last.isNotEmpty ? parts.last[0] : '';
    return (first + second).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;
    final isOverdueRow = item.status == 'Overdue';
    final amountColor = isOverdueRow ? Colors.red.shade500 : const Color(0xFFFF6F4A);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border(context)),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.03), blurRadius: 10, offset: const Offset(0, 4)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 44,
                  height: 44,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: LinearGradient(colors: [Color(0xFFE65100), Color(0xFFBF360C)]),
                  ),
                  child: Text(_initials, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.borrowerName, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.textPrimary(context))),
                      const SizedBox(height: 3),
                      Text(item.loanNo, style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context), fontWeight: FontWeight.w600)),
                      if (isOverdueRow) ...[
                        const SizedBox(height: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(20)),
                          child: Text(
                            '${item.daysOverdue ?? 0} days overdue',
                            style: TextStyle(color: Colors.red.shade600, fontSize: 10.5, fontWeight: FontWeight.w700),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      isOverdueRow ? 'OVERDUE' : 'EMI AMOUNT',
                      style: TextStyle(fontSize: 9.5, letterSpacing: 0.4, color: AppColors.textFaint(context), fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      money(isOverdueRow ? item.arrearAmount : item.dueAmount),
                      style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: amountColor),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Container(height: 1, color: AppColors.divider(context)),
          Padding(
            padding: const EdgeInsets.fromLTRB(10, 8, 14, 8),
            child: Row(
              children: [
                Expanded(
                  child: InkWell(
                    borderRadius: BorderRadius.circular(10),
                    onTap: () => setState(() => _expanded = !_expanded),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 4),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text('Loan details', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.textMuted(context))),
                          Icon(
                            _expanded ? Icons.keyboard_arrow_up_rounded : Icons.keyboard_arrow_down_rounded,
                            size: 18,
                            color: AppColors.textFaint(context),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                if (item.isPending)
                  SizedBox(
                    height: 34,
                    child: ElevatedButton(
                      onPressed: _collect,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFFF9F4A),
                        foregroundColor: Colors.white,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(horizontal: 18),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        textStyle: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700),
                      ),
                      child: Text(isOverdueRow ? 'Collect Arrear' : 'Collect EMI'),
                    ),
                  )
                else
                  Container(
                    height: 34,
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: const Color(0xFF2FBF87).withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.check_circle_rounded, size: 15, color: Color(0xFF2FBF87)),
                        const SizedBox(width: 5),
                        const Text('Paid', style: TextStyle(color: Color(0xFF2FBF87), fontWeight: FontWeight.w700, fontSize: 12.5)),
                      ],
                    ),
                  ),
              ],
            ),
          ),
          AnimatedCrossFade(
            duration: const Duration(milliseconds: 180),
            crossFadeState: _expanded ? CrossFadeState.showFirst : CrossFadeState.showSecond,
            firstChild: Padding(
              padding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
              child: Row(
                children: [
                  Expanded(child: _StatChip(label: 'Disbursed', value: item.totalDisbursed, color: const Color(0xFF2FBF87))),
                  const SizedBox(width: 8),
                  Expanded(child: _StatChip(label: 'Collected', value: item.totalCollected, color: const Color(0xFF4A9FFF))),
                  const SizedBox(width: 8),
                  Expanded(child: _StatChip(label: 'Remaining', value: item.remaining, color: const Color(0xFFFF6F4A))),
                ],
              ),
            ),
            secondChild: const SizedBox(width: double.infinity),
          ),
        ],
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  final String label;
  final double value;
  final Color color;

  const _StatChip({required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(12)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(fontSize: 10, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
          const SizedBox(height: 3),
          Text(
            money(value),
            style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w800, color: color),
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}
