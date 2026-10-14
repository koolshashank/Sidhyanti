import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/hr_api.dart';
import '../../core/models/approval.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../l10n/generated/app_localizations.dart';
import 'approval_list_tab.dart';

class LeaveApprovalTab extends StatelessWidget {
  const LeaveApprovalTab({super.key});

  @override
  Widget build(BuildContext context) {
    final hrApi = context.read<HrApi>();
    final l10n = AppLocalizations.of(context)!;
    return ApprovalListTab<PendingLeaveRequest>(
      fetch: hrApi.pendingLeaveRequests,
      emptyLabel: l10n.bmApprovalLeaveEmptyLabel,
      onReview: (item, approve, remarks) => hrApi.reviewLeaveRequest(
        leaveRequestId: item.leaveRequestId,
        approve: approve,
        remarks: remarks,
      ),
      itemBuilder: (context, item) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(item.staffName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                decoration: BoxDecoration(color: const Color(0xFFC8952C).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                child: Text(item.leaveType, style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: Color(0xFFC8952C))),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text('${fdate(item.fromDate)} - ${fdate(item.toDate)}', style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
          if (item.reason != null && item.reason!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(item.reason!, style: TextStyle(fontSize: 12, color: AppColors.textMuted(context))),
          ],
        ],
      ),
    );
  }
}
