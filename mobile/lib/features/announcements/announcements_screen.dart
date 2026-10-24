import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/notifications_api.dart';
import '../../core/models/notification_item.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../l10n/generated/app_localizations.dart';

/// A dedicated feed of Admin-broadcast notices (announcements.php, web) --
/// reuses the exact same Notification/list.php machinery as the bell icon,
/// just filtered to RelatedEntityType='Announcement' so it doesn't mix with
/// task-related notifications (approvals, etc.).
class AnnouncementsScreen extends StatefulWidget {
  const AnnouncementsScreen({super.key});

  @override
  State<AnnouncementsScreen> createState() => _AnnouncementsScreenState();
}

class _AnnouncementsScreenState extends State<AnnouncementsScreen> {
  List<NotificationItem> _items = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await context.read<NotificationsApi>().list(relatedEntityType: 'Announcement');
      if (!mounted) return;
      setState(() => _items = result.items);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _markRead(NotificationItem item) async {
    if (item.isRead) return;
    setState(() => _items = _items.map((i) => i.notificationId == item.notificationId ? i.copyWithRead() : i).toList());
    try {
      await context.read<NotificationsApi>().markRead(id: item.notificationId);
    } on ApiException {
      // Best-effort -- the read/unread dot is cosmetic here.
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.announcementsTitle)),
      body: _loading && _items.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _items.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: _load, child: Text(l10n.commonRetry)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _items.isEmpty
                      ? ListView(
                          padding: const EdgeInsets.all(40),
                          children: [
                            Center(child: Text(l10n.announcementsEmpty, style: TextStyle(color: AppColors.textMuted(context)))),
                          ],
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                          itemCount: _items.length,
                          itemBuilder: (context, index) => _AnnouncementTile(item: _items[index], onTap: () => _markRead(_items[index])),
                        ),
                ),
    );
  }
}

class _AnnouncementTile extends StatelessWidget {
  final NotificationItem item;
  final VoidCallback onTap;

  const _AnnouncementTile({required this.item, required this.onTap});

  static const _typeIcon = {
    'Info': Icons.campaign_outlined,
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
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
                  child: Icon(_typeIcon[item.typeName] ?? Icons.campaign_outlined, color: color, size: 18),
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
                if (!item.isRead) Container(margin: const EdgeInsets.only(top: 4, left: 4), width: 8, height: 8, decoration: const BoxDecoration(color: Color(0xFFE65100), shape: BoxShape.circle)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
