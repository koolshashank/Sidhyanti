import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/approval.dart';
import '../../core/utils/formatters.dart';

/// Shows the disburse-loan bottom sheet, returns true on success.
Future<bool?> showDisburseSheet(BuildContext context, PendingDisbursementItem item) {
  return showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    builder: (context) => _DisburseSheet(item: item),
  );
}

class _DisburseSheet extends StatefulWidget {
  final PendingDisbursementItem item;

  const _DisburseSheet({required this.item});

  @override
  State<_DisburseSheet> createState() => _DisburseSheetState();
}

class _DisburseSheetState extends State<_DisburseSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _amountController;
  final _refNoController = TextEditingController();
  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _amountController = TextEditingController(text: widget.item.loanAmount.toStringAsFixed(2));
  }

  @override
  void dispose() {
    _amountController.dispose();
    _refNoController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await context.read<LoansApi>().disburse(
            loanId: widget.item.loanId,
            disbursedAmount: double.parse(_amountController.text),
            disbursementRefNo: _refNoController.text.trim().isEmpty ? null : _refNoController.text.trim(),
          );
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;
    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Disburse Loan', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 4),
            Text('${item.memberName} · ${item.loanNo}'),
            Text('Sanctioned: ${money(item.loanAmount)}', style: const TextStyle(color: Colors.grey)),
            const SizedBox(height: 20),
            TextFormField(
              controller: _amountController,
              decoration: const InputDecoration(labelText: 'Disbursed Amount', prefixText: '₹ ', border: OutlineInputBorder()),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              validator: (v) {
                final value = double.tryParse(v ?? '');
                if (value == null || value <= 0) return 'Enter a valid amount';
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _refNoController,
              decoration: const InputDecoration(labelText: 'Reference / UTR No. (optional)', border: OutlineInputBorder()),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _submitting ? null : _submit,
              child: _submitting
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Disburse'),
            ),
          ],
        ),
      ),
    );
  }
}
