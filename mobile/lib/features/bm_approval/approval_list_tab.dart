import 'package:flutter/material.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../l10n/generated/app_localizations.dart';

/// Shared shape for every BM Approval tab (Proposals/Preclose/Leave/Partial
/// Payments): a pull-to-refresh list of pending items, each row rendered by
/// [itemBuilder] with an Approve/Reject action pair that opens a small
/// remarks dialog before calling [onReview].
class ApprovalListTab<T> extends StatefulWidget {
  final Future<List<T>> Function() fetch;
  final Widget Function(BuildContext context, T item) itemBuilder;
  final Future<void> Function(T item, bool approve, String? remarks) onReview;
  final String emptyLabel;

  /// Optional: makes the card body (not the Approve/Reject buttons) tappable
  /// -- e.g. Proposals opens a full "Review Loan Proposal" screen this way.
  /// Return true if the item was approved/rejected/changed there, so this
  /// list reloads; null/false leaves the list as-is.
  final Future<bool?> Function(BuildContext context, T item)? onItemTap;

  /// False hides the inline Approve/Reject row on the card -- used by the
  /// Proposal tab, where GRT must be conducted on the full review screen
  /// before a decision can be made, so a quick inline tap here would skip
  /// that requirement. Every other tab leaves this at its default (true).
  final bool showInlineActions;

  /// When set, the matching item's card is visually highlighted and the
  /// list auto-scrolls to it once -- used when this tab was opened from a
  /// notification tap, pointing at one specific loan among the pending list.
  final bool Function(T item)? isHighlighted;

  const ApprovalListTab({
    super.key,
    required this.fetch,
    required this.itemBuilder,
    required this.onReview,
    required this.emptyLabel,
    this.onItemTap,
    this.showInlineActions = true,
    this.isHighlighted,
  });

  @override
  State<ApprovalListTab<T>> createState() => _ApprovalListTabState<T>();
}

class _ApprovalListTabState<T> extends State<ApprovalListTab<T>> with AutomaticKeepAliveClientMixin {
  List<T> _items = [];
  bool _loading = true;
  String? _error;
  final Map<int, GlobalKey> _itemKeys = {};
  bool _scrolledToHighlight = false;

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
      setState(() {
        _items = items;
        _itemKeys.clear();
        _scrolledToHighlight = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _maybeScrollToHighlight(GlobalKey key) {
    if (_scrolledToHighlight) return;
    _scrolledToHighlight = true;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final ctx = key.currentContext;
      if (ctx != null) {
        Scrollable.ensureVisible(ctx, duration: const Duration(milliseconds: 400), alignment: 0.1);
      }
    });
  }

  Future<void> _review(T item, bool approve) async {
    final l10n = AppLocalizations.of(context)!;
    final remarks = await _showRemarksDialog(approve: approve);
    if (remarks == null) return; // cancelled
    try {
      await widget.onReview(item, approve, remarks.isEmpty ? null : remarks);
      if (!mounted) return;
      setState(() => _items = _items.where((i) => i != item).toList());
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(approve ? l10n.bmApprovalApprovedSnack : l10n.bmApprovalRejectedSnack)),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<String?> _showRemarksDialog({required bool approve}) {
    final l10n = AppLocalizations.of(context)!;
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(approve ? l10n.bmApprovalConfirmApproveTitle : l10n.bmApprovalConfirmRejectTitle),
        content: TextField(
          controller: controller,
          decoration: InputDecoration(labelText: l10n.bmApprovalRemarksOptional, border: const OutlineInputBorder()),
          maxLines: 2,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: Text(l10n.commonCancel)),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: approve ? const Color(0xFF2FBF87) : const Color(0xFFDC3545)),
            onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()),
            child: Text(approve ? l10n.bmApprovalApprove : l10n.bmApprovalReject),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final l10n = AppLocalizations.of(context)!;
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
            OutlinedButton(onPressed: _load, child: Text(l10n.commonRetry)),
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
                final highlighted = widget.isHighlighted?.call(item) ?? false;
                final key = _itemKeys.putIfAbsent(index, () => GlobalKey());
                if (highlighted) _maybeScrollToHighlight(key);
                return Container(
                  key: key,
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: highlighted ? const Color(0xFFFFF3E0) : AppColors.surface(context),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: highlighted ? const Color(0xFFE65100) : AppColors.border(context), width: highlighted ? 2 : 1),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      InkWell(
                        onTap: widget.onItemTap == null
                            ? null
                            : () async {
                                final changed = await widget.onItemTap!(context, item);
                                if (changed == true) _load();
                              },
                        child: widget.itemBuilder(context, item),
                      ),
                      if (widget.showInlineActions) ...[
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton(
                              style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF2FBF87), side: const BorderSide(color: Color(0xFF2FBF87))),
                              onPressed: () => _review(item, true),
                              child: Text(l10n.bmApprovalApprove),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: OutlinedButton(
                              style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFFDC3545), side: const BorderSide(color: Color(0xFFDC3545))),
                              onPressed: () => _review(item, false),
                              child: Text(l10n.bmApprovalReject),
                            ),
                          ),
                        ],
                      ),
                      ],
                    ],
                  ),
                );
              },
            ),
    );
  }
}
