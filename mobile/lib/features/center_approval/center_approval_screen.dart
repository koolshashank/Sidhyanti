import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/centers_api.dart';
import '../../core/models/approval.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../bm_approval/approval_list_tab.dart';

class CenterApprovalScreen extends StatelessWidget {
  const CenterApprovalScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final centersApi = context.read<CentersApi>();
    return Scaffold(
      appBar: AppBar(title: const Text('Center Approval')),
      body: ApprovalListTab<PendingCenterItem>(
        fetch: centersApi.pendingCenters,
        emptyLabel: 'No centers awaiting approval.',
        onReview: (item, approve, remarks) => centersApi.reviewCenter(
          centerId: item.centerId,
          approve: approve,
          remarks: remarks,
        ),
        onItemTap: (context, item) => context.push<bool>('/centers/${item.centerId}/review'),
        itemBuilder: (context, item) => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text(item.centerName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
                Text(item.centerCode, style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context), fontWeight: FontWeight.w600)),
              ],
            ),
            const SizedBox(height: 4),
            if (item.centerPlace != null || item.address != null)
              Text(item.centerPlace ?? item.address ?? '', style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
            if (item.meetingDay != null) ...[
              const SizedBox(height: 2),
              Text('Meets ${item.meetingDay} · ${item.meetingTime ?? ''}', style: TextStyle(fontSize: 12, color: AppColors.textMuted(context))),
            ],
            if (item.managedByName != null) ...[
              const SizedBox(height: 4),
              Text('Managed by ${item.managedByName}', style: TextStyle(fontSize: 12, color: AppColors.textFaint(context))),
            ],
            if (item.createdAt != null) ...[
              const SizedBox(height: 2),
              Text(fdate(item.createdAt), style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context))),
            ],
            const SizedBox(height: 6),
            Row(
              children: [
                Icon(Icons.visibility_outlined, size: 13, color: Colors.grey.shade500),
                const SizedBox(width: 4),
                Text('Tap to view full details & edit', style: TextStyle(fontSize: 11, color: Colors.grey.shade500, fontStyle: FontStyle.italic)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
