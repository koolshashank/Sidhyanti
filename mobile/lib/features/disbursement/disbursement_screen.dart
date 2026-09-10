import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/approval.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'disburse_sheet.dart';

class DisbursementScreen extends StatefulWidget {
  const DisbursementScreen({super.key});

  @override
  State<DisbursementScreen> createState() => _DisbursementScreenState();
}

class _DisbursementScreenState extends State<DisbursementScreen> {
  List<PendingDisbursementItem> _items = [];
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
      final items = await context.read<LoansApi>().pendingDisbursement();
      if (!mounted) return;
      setState(() => _items = items);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _disburse(PendingDisbursementItem item) async {
    final result = await showDisburseSheet(context, item);
    if (result == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Loan disbursed')));
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Disbursement')),
      body: _loading && _items.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _items.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!),
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
                            Center(child: Text('No loans awaiting disbursement.', style: TextStyle(color: AppColors.textMuted(context)))),
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
                                  Row(
                                    children: [
                                      Expanded(child: Text(item.memberName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
                                      Text(money(item.loanAmount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF2FBF87))),
                                    ],
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    '${item.loanNo} · ${item.centerName ?? '-'}',
                                    style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
                                  ),
                                  if (item.officerName != null) ...[
                                    const SizedBox(height: 2),
                                    Text('Officer: ${item.officerName}', style: TextStyle(fontSize: 12, color: AppColors.textFaint(context))),
                                  ],
                                  const SizedBox(height: 12),
                                  SizedBox(
                                    width: double.infinity,
                                    child: FilledButton.icon(
                                      icon: const Icon(Icons.account_balance_wallet_rounded, size: 18),
                                      label: const Text('Disburse'),
                                      onPressed: () => _disburse(item),
                                    ),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                ),
    );
  }
}
