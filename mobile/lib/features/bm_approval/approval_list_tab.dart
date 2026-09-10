import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';

/// Shared shape for every BM Approval tab (Proposals/Preclose/Leave/Partial
/// Payments): a pull-to-refresh list of pending items, each row rendered by
/// [itemBuilder] with an Approve/Reject action pair that opens a small
/// remarks dialog before calling [onReview].
class ApprovalListTab<T> extends StatefulWidget {
  final Future<List<T>> Function() fetch;
  final Widget Function(BuildContext context, T item) itemBuilder;
  final Future<void> Function(T item, bool approve, String? remarks) onReview;
  final String emptyLabel;

  const ApprovalListTab({
    super.key,
    required this.fetch,
    required this.itemBuilder,
    required this.onReview,
    required this.emptyLabel,
  });

  @override
  State<ApprovalListTab<T>> createState() => _ApprovalListTabState<T>();
}

class _ApprovalListTabState<T> extends State<ApprovalListTab<T>> with AutomaticKeepAliveClientMixin {
  List<T> _items = [];
  bool _loading = true;
  String? _error;

  @override
  bool get wantKeepAlive => true;

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
      final items = await widget.fetch();
      if (!mounted) return;
      setState(() => _items = items);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _review(T item, bool approve) async {
    final remarks = await _showRemarksDialog(approve: approve);
    if (remarks == null) return; // cancelled
    try {
      await widget.onReview(item, approve, remarks.isEmpty ? null : remarks);
      if (!mounted) return;
      setState(() => _items = _items.where((i) => i != item).toList());
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(approve ? 'Approved' : 'Rejected')),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<String?> _showRemarksDialog({required bool approve}) {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(approve ? 'Approve this request?' : 'Reject this request?'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(labelText: 'Remarks (optional)', border: OutlineInputBorder()),
          maxLines: 2,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: approve ? const Color(0xFF2FBF87) : const Color(0xFFDC3545)),
            onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()),
            child: Text(approve ? 'Approve' : 'Reject'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    if (_loading && _items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _items.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: _items.isEmpty
          ? ListView(
              padding: const EdgeInsets.all(40),
              children: [
                Center(child: Text(widget.emptyLabel, style: TextStyle(color: AppColors.textMuted(context)))),
              ],
            )
          : ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              itemCount: _items.length,
              itemBuilder: (context, index) {
                final item = _items[index];
                return Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: AppColors.surface(context),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppColors.border(context)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      widget.itemBuilder(context, item),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton(
                              style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF2FBF87), side: const BorderSide(color: Color(0xFF2FBF87))),
                              onPressed: () => _review(item, true),
                              child: const Text('Approve'),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: OutlinedButton(
                              style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFFDC3545), side: const BorderSide(color: Color(0xFFDC3545))),
                              onPressed: () => _review(item, false),
                              child: const Text('Reject'),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
    );
  }
}
