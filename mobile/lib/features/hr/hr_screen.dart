import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../core/api/hr_api.dart';
import '../../core/models/hr.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../shell/account_menu_button.dart';
import 'hr_viewmodel.dart';
import 'leave_request_form.dart';

class HrScreen extends StatelessWidget {
  const HrScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => HrViewModel(context.read<HrApi>()),
      child: const _HrView(),
    );
  }
}

class _HrView extends StatelessWidget {
  const _HrView();

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<HrViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('HR'), actions: const [AccountMenuButton(), SizedBox(width: 8)]),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(vm.error!),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: vm.refresh, child: const Text('Retry')),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: vm.refresh,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      _AttendanceCard(vm: vm),
                      const SizedBox(height: 20),
                      if (vm.history.isNotEmpty) ...[
                        Text('Attendance History', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context))),
                        const SizedBox(height: 10),
                        _AttendanceHistoryCard(history: vm.history),
                        const SizedBox(height: 20),
                      ],
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Leave Requests', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context))),
                          TextButton.icon(
                            onPressed: () async {
                              final ok = await showLeaveRequestForm(context);
                              if (ok == true) {
                                if (context.mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Leave request submitted')));
                                  vm.refresh();
                                }
                              }
                            },
                            icon: const Icon(Icons.add_rounded, size: 18),
                            label: const Text('New Request'),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      if (vm.leaveRequests.isEmpty)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 20),
                          child: Center(child: Text('No leave requests yet.', style: TextStyle(color: AppColors.textMuted(context)))),
                        )
                      else
                        ...vm.leaveRequests.map((lr) => _LeaveRequestTile(item: lr)),
                    ],
                  ),
                ),
    );
  }
}

class _AttendanceCard extends StatelessWidget {
  final HrViewModel vm;

  const _AttendanceCard({required this.vm});

  @override
  Widget build(BuildContext context) {
    final attendance = vm.attendance;
    final checkedIn = attendance?.checkedIn ?? false;
    final checkedOut = attendance?.checkedOut ?? false;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Color(0xFF3A3D9F), Color(0xFF6A4FC7)]),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [BoxShadow(color: const Color(0xFF6A4FC7).withValues(alpha: 0.3), blurRadius: 16, offset: const Offset(0, 8))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(14)),
                child: const Icon(Icons.fingerprint_rounded, color: Colors.white, size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text("Today's Attendance", style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12.5)),
                    Text(
                      checkedOut ? 'Day complete' : checkedIn ? 'Checked in' : 'Not checked in',
                      style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (attendance?.checkInAt != null || attendance?.checkOutAt != null)
            Row(
              children: [
                _TimeChip(label: 'In', time: attendance?.checkInAt),
                const SizedBox(width: 10),
                _TimeChip(label: 'Out', time: attendance?.checkOutAt),
              ],
            ),
          if (attendance?.checkInAt != null || attendance?.checkOutAt != null) const SizedBox(height: 16),
          if (vm.attendanceActionError != null) ...[
            Text(vm.attendanceActionError!, style: const TextStyle(color: Colors.white)),
            const SizedBox(height: 10),
          ],
          SizedBox(
            width: double.infinity,
            height: 46,
            child: ElevatedButton.icon(
              onPressed: vm.checkingInOut || checkedOut
                  ? null
                  : () => checkedIn ? vm.checkOut() : vm.checkIn(),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: const Color(0xFF3A3D9F),
                disabledBackgroundColor: Colors.white.withValues(alpha: 0.4),
              ),
              icon: vm.checkingInOut
                  ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : Icon(checkedIn ? Icons.logout_rounded : Icons.login_rounded, size: 18),
              label: Text(
                checkedOut ? 'Done for today' : checkedIn ? 'Check Out' : 'Check In',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _TimeChip extends StatelessWidget {
  final String label;
  final DateTime? time;

  const _TimeChip({required this.label, required this.time});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
        decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(12)),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 10.5)),
            Text(time != null ? DateFormat('h:mm a').format(time!) : '--:--', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13.5)),
          ],
        ),
      ),
    );
  }
}

class _AttendanceHistoryCard extends StatelessWidget {
  final List<AttendanceHistoryItem> history;

  const _AttendanceHistoryCard({required this.history});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Column(
        children: history.asMap().entries.map((entry) {
          final item = entry.value;
          final isLast = entry.key == history.length - 1;
          return Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              border: isLast ? null : Border(bottom: BorderSide(color: AppColors.divider(context))),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(item.date != null ? DateFormat('EEE, d MMM').format(item.date!) : '-', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                ),
                Text(
                  item.checkInAt != null ? DateFormat('h:mm a').format(item.checkInAt!) : '--',
                  style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
                ),
                const SizedBox(width: 12),
                Icon(Icons.arrow_right_alt_rounded, size: 16, color: AppColors.textFaint(context)),
                const SizedBox(width: 12),
                Text(
                  item.checkOutAt != null ? DateFormat('h:mm a').format(item.checkOutAt!) : '--',
                  style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
                ),
              ],
            ),
          );
        }).toList(),
      ),
    );
  }
}

class _LeaveRequestTile extends StatelessWidget {
  final LeaveRequestItem item;

  const _LeaveRequestTile({required this.item});

  Color get _statusColor {
    switch (item.status) {
      case 'Approved':
        return const Color(0xFF2FBF87);
      case 'Rejected':
        return Colors.red.shade400;
      default:
        return const Color(0xFFC8952C);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
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
              Expanded(child: Text(item.leaveType, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                decoration: BoxDecoration(color: _statusColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                child: Text(item.status, style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: _statusColor)),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text('${fdate(item.fromDate)} - ${fdate(item.toDate)}', style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
          if (item.reason != null && item.reason!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(item.reason!, style: TextStyle(fontSize: 12, color: AppColors.textMuted(context))),
          ],
          if (item.status != 'Pending' && item.remarks != null && item.remarks!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text('Remarks: ${item.remarks}', style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context), fontStyle: FontStyle.italic)),
          ],
        ],
      ),
    );
  }
}
