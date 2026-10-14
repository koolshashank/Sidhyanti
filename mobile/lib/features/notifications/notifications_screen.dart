import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/models/notification_item.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../routing/notification_routing.dart';
import 'notifications_viewmodel.dart';

/// Routes straight to whatever a notification is about, instead of leaving
/// the user to go hunt for it -- see resolveNotificationRoute() (shared
/// with the push-notification tap handler, which has no BuildContext).
void _openNotificationTarget(BuildContext context, NotificationItem item) {
  final path = resolveNotificationRoute(relatedEntityType: item.relatedEntityType, relatedEntityId: item.relatedEntityId, title: item.title);
  if (path != null) context.push(path);
}

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => context.read<NotificationsViewModel>().load());
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<NotificationsViewModel>();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (vm.unreadCount > 0)
            TextButton(
              onPressed: () => context.read<NotificationsViewModel>().markAllRead(),
              child: const Text('Mark all read', style: TextStyle(color: Colors.white)),
            ),
          if (vm.items.isNotEmpty)
            IconButton(
              tooltip: 'Clear all',
              icon: const Icon(Icons.delete_sweep_outlined),
              onPressed: () => _confirmClearAll(context),
            ),
        ],
      ),
      body: vm.loading && vm.items.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : vm.error != null && vm.items.isEmpty
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
              : RefreshIndicator(
                  onRefresh: vm.load,
                  child: vm.items.isEmpty
                      ? ListView(
                          padding: const EdgeInsets.all(40),
                          children: [
                            Center(child: Text("You're all caught up 🎉", style: TextStyle(color: AppColors.textMuted(context)))),
                          ],
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                          itemCount: vm.items.length,
                          itemBuilder: (context, index) {
                            final item = vm.items[index];
                            return Dismissible(
                              key: ValueKey(item.notificationId),
                              direction: DismissDirection.endToStart,
                              background: Container(
                                margin: const EdgeInsets.only(bottom: 10),
                                padding: const EdgeInsets.symmetric(horizontal: 20),
                                alignment: Alignment.centerRight,
                                decoration: BoxDecoration(color: Colors.red.shade400, borderRadius: BorderRadius.circular(16)),
                                child: const Icon(Icons.delete_outline_rounded, color: Colors.white),
                              ),
                              onDismissed: (_) => context.read<NotificationsViewModel>().dismiss(item.notificationId),
                              child: _NotificationTile(item: item),
                            );
                          },
                        ),
                ),
    );
  }

  Future<void> _confirmClearAll(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Clear all notifications?'),
        content: const Text('This will remove every notification from this list.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Clear all')),
        ],
      ),
    );
    if (confirmed == true && context.mounted) {
      context.read<NotificationsViewModel>().clearAll();
    }
  }
}

class _NotificationTile extends StatelessWidget {
  final NotificationItem item;

  const _NotificationTile({required this.item});

  static const _typeIcon = {
    'Info': Icons.info_outline_rounded,
    'Warning': Icons.warning_amber_rounded,
    'Success': Icons.check_circle_outline_rounded,
    'Danger': Icons.error_outline_rounded,
  };
  static const _typeColor = {
    'Info': Color(0xFFBF360C),
    'Warning': Color(0xFFC8952C),
    'Success': Color(0xFF2FBF87),
    'Danger': Color(0xFFDC3545),
  };

  @override
  Widget build(BuildContext context) {
    final color = _typeColor[item.typeName] ?? const Color(0xFFBF360C);
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: item.isRead
            ? AppColors.surface(context)
            : (AppColors.isDark(context) ? const Color(0xFF1A2233) : const Color(0xFFF4F8FF)),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () {
            if (!item.isRead) context.read<NotificationsViewModel>().markRead(item.notificationId);
            _openNotificationTarget(context, item);
          },
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
                  child: Icon(_typeIcon[item.typeName] ?? Icons.notifications_none_rounded, color: color, size: 18),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                      if (item.message != null && item.message!.isNotEmpty) ...[
                        const SizedBox(height: 3),
                        Text(item.message!, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                      ],
                      const SizedBox(height: 5),
                      Text(fdate(item.createdAt), style: TextStyle(fontSize: 11, color: AppColors.textFaint(context))),
                    ],
                  ),
                ),
                if (!item.isRead) Container(margin: const EdgeInsets.only(top: 4, right: 4), width: 8, height: 8, decoration: const BoxDecoration(color: Color(0xFFE65100), shape: BoxShape.circle)),
                InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: () => context.read<NotificationsViewModel>().dismiss(item.notificationId),
                  child: Padding(
                    padding: const EdgeInsets.all(2),
                    child: Icon(Icons.close_rounded, size: 18, color: AppColors.textFaint(context)),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
