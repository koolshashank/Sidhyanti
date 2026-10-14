import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../shell/account_menu_button.dart';

/// Shared loading/error/empty/list scaffold for every operational report
/// screen -- each screen just supplies its own typed fetch + row builder.
class ReportListScaffold<T> extends StatefulWidget {
  final String title;
  final Future<List<T>> Function() fetch;
  final Widget Function(BuildContext, T) itemBuilder;
  final String emptyLabel;
  final Widget Function(BuildContext, List<T>)? summaryBuilder;

  const ReportListScaffold({
    super.key,
    required this.title,
    required this.fetch,
    required this.itemBuilder,
    required this.emptyLabel,
    this.summaryBuilder,
  });

  @override
  State<ReportListScaffold<T>> createState() => _ReportListScaffoldState<T>();
}

class _ReportListScaffoldState<T> extends State<ReportListScaffold<T>> {
  bool _loading = true;
  String? _error;
  List<T> _items = [];

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
      if (mounted) setState(() => _items = items);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title), actions: const [AccountMenuButton(), SizedBox(width: 8)]),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _items.isEmpty
                      ? ListView(
                          padding: const EdgeInsets.all(40),
                          children: [
                            Center(child: Text(widget.emptyLabel, style: TextStyle(color: AppColors.textMuted(context)))),
                          ],
                        )
                      : ListView(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                          children: [
                            if (widget.summaryBuilder != null) ...[
                              widget.summaryBuilder!(context, _items),
                              const SizedBox(height: 12),
                            ],
                            ..._items.map((item) => widget.itemBuilder(context, item)),
                          ],
                        ),
                ),
    );
  }
}
