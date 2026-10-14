import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/collections_api.dart';
import '../../core/models/collection_due_item.dart';
import '../../core/utils/contact_actions.dart';
import '../../core/utils/draft_store.dart';
import '../../core/utils/formatters.dart';
import '../../l10n/generated/app_localizations.dart';
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

  String get _draftKey => 'collection:${widget.item.scheduleId}';
  Timer? _draftDebounce;

  @override
  void initState() {
    super.initState();
    _amountController = TextEditingController(text: widget.item.dueAmount.toStringAsFixed(2));
    _amountController.addListener(_scheduleDraftSave);
    _remarksController.addListener(_scheduleDraftSave);
    WidgetsBinding.instance.addPostFrameCallback((_) => _checkForDraft());
  }

  @override
  void dispose() {
    _draftDebounce?.cancel();
    _amountController.dispose();
    _remarksController.dispose();
    super.dispose();
  }

  void _scheduleDraftSave() {
    _draftDebounce?.cancel();
    _draftDebounce = Timer(const Duration(milliseconds: 600), _saveDraft);
  }

  void _saveDraft() {
    DraftStore.save(_draftKey, {
      'amount': _amountController.text,
      'remarks': _remarksController.text,
      'collection_date': _collectionDate.toIso8601String(),
    });
  }

  Future<void> _checkForDraft() async {
    final draft = await DraftStore.load(_draftKey);
    if (draft == null || !mounted) return;
    final (data, savedAt) = draft;
    final l10n = AppLocalizations.of(context)!;
    final resume = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l10n.recordPaymentResumeDraftTitle),
        content: Text(l10n.recordPaymentResumeDraftBody(fdate(savedAt))),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: Text(l10n.recordPaymentStartFresh)),
          FilledButton(onPressed: () => Navigator.of(dialogContext).pop(true), child: Text(l10n.recordPaymentResume)),
        ],
      ),
    );
    if (resume == true) {
      setState(() {
        _amountController.text = data['amount'] as String? ?? _amountController.text;
        _remarksController.text = data['remarks'] as String? ?? '';
        final savedDate = data['collection_date'] as String?;
        if (savedDate != null) _collectionDate = DateTime.tryParse(savedDate) ?? _collectionDate;
      });
    } else {
      await DraftStore.clear(_draftKey);
    }
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _collectionDate,
      firstDate: DateTime.now().subtract(const Duration(days: 30)),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      setState(() => _collectionDate = picked);
      _scheduleDraftSave();
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final vm = context.read<RecordPaymentViewModel>();
    final amount = double.parse(_amountController.text);
    final result = await vm.submit(
      amount: amount,
      collectionDate: _collectionDate,
      remarks: _remarksController.text.trim(),
    );
    if (result != null && mounted) {
      await DraftStore.clear(_draftKey);
      if (!mounted) return;
      if ((widget.item.mobileNo ?? '').isNotEmpty) {
        await _offerWhatsAppReceipt(amount);
      }
      if (!mounted) return;
      Navigator.of(context).pop(result);
    }
  }

  /// Offers to share a plain-text receipt with the client over WhatsApp --
  /// optional and skippable, never blocks completing the collection.
  Future<void> _offerWhatsAppReceipt(double amount) async {
    final l10n = AppLocalizations.of(context)!;
    final share = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l10n.recordPaymentRecordedTitle),
        content: Text(l10n.recordPaymentShareReceiptQuestion),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: Text(l10n.recordPaymentSkip)),
          FilledButton(onPressed: () => Navigator.of(dialogContext).pop(true), child: Text(l10n.recordPaymentShareReceipt)),
        ],
      ),
    );
    if (share != true || !mounted) return;
    final item = widget.item;
    final message = l10n.recordPaymentReceiptMessage(
      item.loanNo,
      item.installmentNo,
      money(amount),
      fdate(_collectionDate),
    );
    await whatsAppNumber(context, item.mobileNo!, message: message);
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<RecordPaymentViewModel>();
    final item = widget.item;
    final l10n = AppLocalizations.of(context)!;

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
            Text(l10n.recordPaymentTitle, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 4),
            Text(l10n.recordPaymentInstallmentLine(item.borrowerName, item.installmentNo)),
            Text(l10n.recordPaymentDueLine(money(item.dueAmount)), style: const TextStyle(color: Colors.grey)),
            const SizedBox(height: 20),
            TextFormField(
              controller: _amountController,
              decoration: InputDecoration(labelText: l10n.recordPaymentAmountLabel, prefixText: '₹ ', border: const OutlineInputBorder()),
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              validator: (v) {
                final value = double.tryParse(v ?? '');
                if (value == null || value <= 0) return l10n.recordPaymentAmountInvalid;
                return null;
              },
            ),
            const SizedBox(height: 16),
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.calendar_today),
              title: Text(fdate(_collectionDate)),
              trailing: TextButton(onPressed: _pickDate, child: Text(l10n.recordPaymentChange)),
            ),
            const SizedBox(height: 8),
            TextFormField(
              controller: _remarksController,
              decoration: InputDecoration(labelText: l10n.recordPaymentRemarksLabel, border: const OutlineInputBorder()),
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
                  : Text(l10n.recordPaymentSubmit),
            ),
          ],
        ),
      ),
    );
  }
}
