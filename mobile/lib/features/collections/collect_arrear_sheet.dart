import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/collections_api.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/collection_due_item.dart';
import '../../core/models/loan.dart';
import '../../core/utils/formatters.dart';

/// Result of a cascaded arrear collection -- how many installments were
/// fully cleared, and whether the last (short-covered) one needed Branch
/// Manager approval.
class CollectArrearResult {
  final int installmentsCleared;
  final bool hasPendingApproval;

  CollectArrearResult({required this.installmentsCleared, required this.hasPendingApproval});
}

/// Shows the "Collect Arrear" bottom sheet for an overdue borrower -- unlike
/// the single-installment record-payment sheet, this fetches every unpaid
/// past-due installment and cascades the entered amount across them
/// oldest-first (one collections/record.php call per installment under the
/// hood). An amount that doesn't fully cover the next installment in line
/// still correctly lands in Partial_Payment_Approval via the same
/// per-installment logic that already exists -- nothing new on the server
/// needed for that part.
Future<CollectArrearResult?> showCollectArrearSheet(BuildContext context, CollectionDueItem item) {
  return showModalBottomSheet<CollectArrearResult>(
    context: context,
    isScrollControlled: true,
    builder: (context) => _CollectArrearSheet(item: item),
  );
}

class _CollectArrearSheet extends StatefulWidget {
  final CollectionDueItem item;

  const _CollectArrearSheet({required this.item});

  @override
  State<_CollectArrearSheet> createState() => _CollectArrearSheetState();
}

class _CollectArrearSheetState extends State<_CollectArrearSheet> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _remarksController = TextEditingController();
  DateTime _collectionDate = DateTime.now();

  bool _loadingInstallments = true;
  String? _loadError;
  List<OverdueInstallment> _installments = [];
  double get _totalArrear => _installments.fold(0, (sum, i) => sum + i.emiAmount);

  bool _submitting = false;
  String? _submitError;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loadingInstallments = true;
      _loadError = null;
    });
    try {
      final installments = await context.read<LoansApi>().overdueInstallments(widget.item.loanId);
      _installments = installments;
      _amountController.text = _totalArrear.toStringAsFixed(2);
    } on ApiException catch (e) {
      _loadError = e.message;
    } finally {
      if (mounted) setState(() => _loadingInstallments = false);
    }
  }

  @override
  void dispose() {
    _amountController.dispose();
    _remarksController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _collectionDate,
      firstDate: DateTime.now().subtract(const Duration(days: 30)),
      lastDate: DateTime.now(),
    );
    if (picked != null) setState(() => _collectionDate = picked);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _installments.isEmpty) return;
    final collectionsApi = context.read<CollectionsApi>();
    final remarks = _remarksController.text.trim();
    var remaining = double.parse(_amountController.text);
    var cleared = 0;
    var pendingApproval = false;

    setState(() {
      _submitting = true;
      _submitError = null;
    });
    try {
      for (final inst in _installments) {
        if (remaining <= 0) break;
        final payAmount = remaining >= inst.emiAmount ? inst.emiAmount : remaining;
        final result = await collectionsApi.record(
          loanId: widget.item.loanId,
          installmentNo: inst.installmentNo,
          amount: payAmount,
          collectionDate: _collectionDate,
          remarks: remarks.isEmpty ? null : remarks,
        );
        remaining -= payAmount;
        if (result.status == 'PendingApproval') {
          pendingApproval = true;
        } else {
          cleared++;
        }
      }
      if (mounted) {
        Navigator.of(context).pop(CollectArrearResult(installmentsCleared: cleared, hasPendingApproval: pendingApproval));
      }
    } on ApiException catch (e) {
      setState(() {
        _submitError = cleared > 0
            ? '$cleared installment(s) cleared before this failed: ${e.message}'
            : e.message;
      });
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: _loadingInstallments
          ? const SizedBox(height: 160, child: Center(child: CircularProgressIndicator()))
          : _loadError != null
              ? SizedBox(
                  height: 160,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_loadError!, textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : Form(
                  key: _formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text('Collect Arrear', style: Theme.of(context).textTheme.titleLarge),
                      const SizedBox(height: 4),
                      Text(widget.item.borrowerName),
                      Text(
                        '${_installments.length} installment(s) overdue from #${_installments.first.installmentNo}',
                        style: const TextStyle(color: Colors.grey),
                      ),
                      Text('Total Arrear: ${money(_totalArrear)}', style: const TextStyle(color: Colors.grey, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 20),
                      TextFormField(
                        controller: _amountController,
                        decoration: const InputDecoration(labelText: 'Amount Collected', prefixText: '₹ ', border: OutlineInputBorder()),
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        validator: (v) {
                          final value = double.tryParse(v ?? '');
                          if (value == null || value <= 0) return 'Enter a valid amount';
                          if (value > _totalArrear + 0.01) return 'Cannot exceed the total arrear (${money(_totalArrear)})';
                          return null;
                        },
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'If this doesn\'t cover the next installment in full, it will be submitted for Branch Manager approval.',
                        style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                      ),
                      const SizedBox(height: 16),
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.calendar_today),
                        title: Text(fdate(_collectionDate)),
                        trailing: TextButton(onPressed: _pickDate, child: const Text('Change')),
                      ),
                      const SizedBox(height: 8),
                      TextFormField(
                        controller: _remarksController,
                        decoration: const InputDecoration(labelText: 'Remarks (optional)', border: OutlineInputBorder()),
                        maxLines: 2,
                      ),
                      if (_submitError != null) ...[
                        const SizedBox(height: 12),
                        Text(_submitError!, style: const TextStyle(color: Colors.red)),
                      ],
                      const SizedBox(height: 20),
                      FilledButton(
                        onPressed: _submitting ? null : _submit,
                        child: _submitting
                            ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Text('Submit'),
                      ),
                    ],
                  ),
                ),
    );
  }
}
