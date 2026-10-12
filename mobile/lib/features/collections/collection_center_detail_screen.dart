import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/collections_api.dart';
import '../../core/models/collection_due_item.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'collection_center_detail_viewmodel.dart';
import 'widgets/collection_borrower_tile.dart';
import 'widgets/percent_ring.dart';

class CollectionCenterDetailScreen extends StatelessWidget {
  final int centerId;
  final String centerName;
  final DateTime dateFrom;
  final DateTime dateTo;

  const CollectionCenterDetailScreen({
    super.key,
    required this.centerId,
    required this.centerName,
    required this.dateFrom,
    required this.dateTo,
  });

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => CollectionCenterDetailViewModel(context.read<CollectionsApi>(), centerId, dateFrom, dateTo),
      child: _CollectionCenterDetailView(centerName: centerName),
    );
  }
}

class _CollectionCenterDetailView extends StatelessWidget {
  final String centerName;

  const _CollectionCenterDetailView({required this.centerName});

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<CollectionCenterDetailViewModel>();

    final showAttendanceGate = vm.isTodayView && !vm.attendanceComplete;

    return Scaffold(
      appBar: AppBar(title: Text(centerName, overflow: TextOverflow.ellipsis)),
      body: (vm.loading || vm.attendanceLoading) && vm.items.isEmpty && vm.attendanceItems.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : vm.error != null && vm.items.isEmpty && vm.attendanceItems.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(vm.error!),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: vm.load, child: const Text('Retry')),
                    ],
                  ),
                )
              : showAttendanceGate
                  ? _AttendanceGate(vm: vm)
                  : RefreshIndicator(
                  onRefresh: vm.load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (vm.summary != null) _HeaderCard(centerName: centerName, summary: vm.summary!),
                      const SizedBox(height: 16),
                      if (vm.items.isEmpty)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 40),
                          child: Center(child: Text('No collections due here for this period.', style: TextStyle(color: AppColors.textMuted(context)))),
                        )
                      else
                        ...vm.items.map(
                          (item) => CollectionBorrowerTile(
                            item: item,
                            onCollected: (scheduleId, amount) => vm.markCollected(scheduleId, amount),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}

/// Blocks the collection list until every client due today at this center
/// has been marked Present or Absent for the meeting.
class _AttendanceGate extends StatelessWidget {
  final CollectionCenterDetailViewModel vm;

  const _AttendanceGate({required this.vm});

  @override
  Widget build(BuildContext context) {
    final markedCount = vm.attendanceItems.where((i) => i.status != null).length;
    final allMarked = markedCount == vm.attendanceItems.length;

    return RefreshIndicator(
      onRefresh: vm.load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              gradient: const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Color(0xFFBF360C), Color(0xFFE65100)]),
              borderRadius: BorderRadius.circular(20),
              boxShadow: [BoxShadow(color: const Color(0xFFE65100).withValues(alpha: 0.3), blurRadius: 14, offset: const Offset(0, 6))],
            ),
            child: Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(12)),
                  child: const Icon(Icons.how_to_reg_rounded, color: Colors.white, size: 22),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Mark Meeting Attendance', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14.5)),
                      const SizedBox(height: 2),
                      Text('$markedCount of ${vm.attendanceItems.length} marked · Required before collection',
                          style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 11.5)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: vm.markAllPresent,
              icon: const Icon(Icons.done_all_rounded, size: 16),
              label: const Text('Mark all Present'),
            ),
          ),
          ...vm.attendanceItems.map((item) => _AttendanceTile(item: item, vm: vm)),
          if (vm.attendanceError != null) ...[
            const SizedBox(height: 8),
            Text(vm.attendanceError!, style: TextStyle(color: Colors.red.shade600, fontSize: 12.5)),
          ],
          const SizedBox(height: 16),
          SizedBox(
            height: 50,
            child: FilledButton.icon(
              onPressed: (vm.submittingAttendance || !allMarked) ? null : () => vm.submitAttendance(),
              icon: vm.submittingAttendance
                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.arrow_forward_rounded, size: 18),
              label: Text(vm.submittingAttendance ? 'Saving...' : 'Continue to Collection'),
              style: FilledButton.styleFrom(backgroundColor: const Color(0xFFE65100)),
            ),
          ),
        ],
      ),
    );
  }
}

class _AttendanceTile extends StatelessWidget {
  final AttendanceItem item;
  final CollectionCenterDetailViewModel vm;

  const _AttendanceTile({required this.item, required this.vm});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(12),
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
                Text(item.borrowerName, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, color: AppColors.textPrimary(context))),
                Text(money(item.dueAmount), style: TextStyle(fontSize: 11.5, color: AppColors.textMuted(context))),
              ],
            ),
          ),
          _AttendanceButton(
            label: 'Present',
            icon: Icons.check_rounded,
            color: const Color(0xFF2FBF87),
            selected: item.status == 'Present',
            onTap: () => vm.setAttendanceStatus(item.loanId, 'Present'),
          ),
          const SizedBox(width: 8),
          _AttendanceButton(
            label: 'Absent',
            icon: Icons.close_rounded,
            color: Colors.red.shade400,
            selected: item.status == 'Absent',
            onTap: () => vm.setAttendanceStatus(item.loanId, 'Absent'),
          ),
        ],
      ),
    );
  }
}

class _AttendanceButton extends StatelessWidget {
  final String label;
  final IconData icon;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  const _AttendanceButton({required this.label, required this.icon, required this.color, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? color.withValues(alpha: 0.14) : Colors.transparent,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: selected ? color : AppColors.border(context), width: selected ? 1.5 : 1),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 15, color: selected ? color : AppColors.textFaint(context)),
              Text(label, style: TextStyle(fontSize: 9.5, fontWeight: FontWeight.w700, color: selected ? color : AppColors.textFaint(context))),
            ],
          ),
        ),
      ),
    );
  }
}

class _HeaderCard extends StatelessWidget {
  final String centerName;
  final CollectionsSummary summary;

  const _HeaderCard({required this.centerName, required this.summary});

  @override
  Widget build(BuildContext context) {
    final percent = summary.dueAmount > 0 ? (summary.collectedAmount / summary.dueAmount * 100) : 0.0;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Color(0xFFBF360C), Color(0xFFE65100)]),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [BoxShadow(color: const Color(0xFFE65100).withValues(alpha: 0.3), blurRadius: 16, offset: const Offset(0, 8))],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(50)),
            child: PercentRing(percent: percent, size: 56),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${summary.clientCount} clients', style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12.5)),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    _HeaderStat(label: 'Target', value: money(summary.dueAmount)),
                    _HeaderStat(label: 'Received', value: money(summary.collectedAmount)),
                    _HeaderStat(label: 'Balance', value: money(summary.balance)),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HeaderStat extends StatelessWidget {
  final String label;
  final String value;

  const _HeaderStat({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 10.5)),
        const SizedBox(height: 2),
        Text(value, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w800)),
      ],
    );
  }
}
