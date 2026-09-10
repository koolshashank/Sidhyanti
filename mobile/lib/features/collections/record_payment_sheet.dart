import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/collections_api.dart';
import '../../core/models/collection_due_item.dart';
import '../../core/utils/formatters.dart';
import 'record_payment_viewmodel.dart';

/// Shows the record-payment bottom sheet and returns the [RecordPaymentResult]
/// on success, or null if the user cancelled or it failed.
Future<RecordPaymentResult?> showRecordPaymentSheet(BuildContext context, CollectionDueItem item) {
  return showModalBottomSheet<RecordPaymentResult>(
    context: context,
    isScrollControlled: true,
    builder: (context) => ChangeNotifierProvider(
      create: (context) => RecordPaymentViewModel(context.read<CollectionsApi>(), item),
      child: _RecordPaymentSheet(item: item),
    ),
  );
}

class _RecordPaymentSheet extends StatefulWidget {
  final CollectionDueItem item;

  const _RecordPaymentSheet({required this.item});

  @override
  State<_RecordPaymentSheet> createState() => _RecordPaymentSheetState();
}

class _RecordPaymentSheetState extends State<_RecordPaymentSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _amountController;
  final _remarksController = TextEditingController();
  DateTime _collectionDate = DateTime.now();

  @override
  void initState() {
    super.initState();
    _amountController = TextEditingController(text: widget.item.dueAmount.toStringAsFixed(2));
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
    if (!_formKey.currentState!.validate()) return;
    final vm = context.read<RecordPaymentViewModel>();
    final result = await vm.submit(
      amount: double.parse(_amountController.text),
      collectionDate: _collectionDate,
      remarks: _remarksController.text.trim(),
    );
    if (result != null && mounted) {
      Navigator.of(context).pop(result);
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<RecordPaymentViewModel>();
    final item = widget.item;

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Record Payment', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 4),
            Text('${item.borrowerName} · Installment #${item.installmentNo}'),
            Text('Due: ${money(item.dueAmount)}', style: const TextStyle(color: Colors.grey)),
            const SizedBox(height: 20),
            TextFormField(
              controller: _amountController,
              decoration: const InputDecoration(labelText: 'Amount Collected', prefixText: '₹ ', border: OutlineInputBorder()),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              validator: (v) {
                final value = double.tryParse(v ?? '');
                if (value == null || value <= 0) return 'Enter a valid amount';
                return null;
              },
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
            if (vm.error != null) ...[
              const SizedBox(height: 12),
              Text(vm.error!, style: const TextStyle(color: Colors.red)),
            ],
            const SizedBox(height: 20),
            FilledButton(
              onPressed: vm.submitting ? null : _submit,
              child: vm.submitting
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Submit'),
            ),
          ],
        ),
      ),
    );
  }
}
