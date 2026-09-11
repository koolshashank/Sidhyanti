import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/approval.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/geo_photo_stamp.dart';
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
                            return _DisbursementCard(
                              item: _items[index],
                              onDisburse: _disburse,
                              onPhotoCaptured: _load,
                            );
                          },
                        ),
                ),
    );
  }
}

class _DisbursementCard extends StatefulWidget {
  final PendingDisbursementItem item;
  final ValueChanged<PendingDisbursementItem> onDisburse;
  final VoidCallback onPhotoCaptured;

  const _DisbursementCard({required this.item, required this.onDisburse, required this.onPhotoCaptured});

  @override
  State<_DisbursementCard> createState() => _DisbursementCardState();
}

class _DisbursementCardState extends State<_DisbursementCard> {
  bool _expanded = false;
  bool _capturingPhoto = false;
  String? _photoError;

  Future<void> _capturePhoto() async {
    final loansApi = context.read<LoansApi>();
    final picker = ImagePicker();
    final picked = await picker.pickImage(source: ImageSource.camera, imageQuality: 80);
    if (picked == null || !mounted) return;

    setState(() {
      _capturingPhoto = true;
      _photoError = null;
    });
    try {
      final file = File(picked.path);
      await stampLocationOnPhoto(file);
      await loansApi.uploadDisbursementPhoto(loanId: widget.item.loanId, photo: file);
      widget.onPhotoCaptured();
    } on ApiException catch (e) {
      if (mounted) setState(() => _photoError = e.message);
    } finally {
      if (mounted) setState(() => _capturingPhoto = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;
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
              IconButton(
                icon: Icon(_expanded ? Icons.expand_less_rounded : Icons.expand_more_rounded),
                tooltip: 'Fee & charges breakdown',
                visualDensity: VisualDensity.compact,
                onPressed: () => setState(() => _expanded = !_expanded),
              ),
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
          if (_expanded) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: AppColors.surfaceAlt(context), borderRadius: BorderRadius.circular(12)),
              child: Column(
                children: [
                  _DeductionRow(label: 'Processing Fee', amount: item.processingFee),
                  _DeductionRow(label: 'Insurance Amount', amount: item.insuranceAmount),
                  _DeductionRow(label: 'Other Charges', amount: item.otherCharges),
                  const Divider(height: 16),
                  _DeductionRow(label: 'Total Deductions', amount: item.totalDeductions, bold: true),
                  _DeductionRow(label: 'Net Disbursement', amount: item.netDisbursementAmount, bold: true, color: const Color(0xFF2FBF87)),
                ],
              ),
            ),
          ],
          const SizedBox(height: 12),
          if (_photoError != null) ...[
            Text(_photoError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
            const SizedBox(height: 6),
          ],
          if (!item.photoCaptured)
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                icon: _capturingPhoto
                    ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.camera_alt_rounded, size: 18),
                label: Text(_capturingPhoto ? 'Processing photo...' : 'Capture Customer Photo to Continue'),
                onPressed: _capturingPhoto ? null : _capturePhoto,
              ),
            )
          else
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                icon: const Icon(Icons.account_balance_wallet_rounded, size: 18),
                label: const Text('Disburse'),
                onPressed: () => widget.onDisburse(item),
              ),
            ),
        ],
      ),
    );
  }
}

class _DeductionRow extends StatelessWidget {
  final String label;
  final double amount;
  final bool bold;
  final Color? color;

  const _DeductionRow({required this.label, required this.amount, this.bold = false, this.color});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: bold ? FontWeight.w700 : FontWeight.w500,
                color: color ?? AppColors.textMuted(context),
              ),
            ),
          ),
          Text(
            money(amount),
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
              color: color ?? AppColors.textPrimary(context),
            ),
          ),
        ],
      ),
    );
  }
}
