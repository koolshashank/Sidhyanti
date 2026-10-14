import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/api/loans_api.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/geo_photo_stamp.dart';
import '../../l10n/generated/app_localizations.dart';
import '../operations/widgets/form_section_card.dart';
import '../operations/widgets/form_styles.dart';
import '../operations/widgets/local_photo_preview.dart';
import 'grt_viewmodel.dart';

class GrtScreen extends StatelessWidget {
  final int loanId;
  final String loanNo;
  final DateTime? applicationDate;
  final double loanAmount;

  const GrtScreen({super.key, required this.loanId, required this.loanNo, required this.applicationDate, required this.loanAmount});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => GrtViewModel(context.read<LoansApi>(), loanId),
      child: _GrtView(loanNo: loanNo, applicationDate: applicationDate, loanAmount: loanAmount),
    );
  }
}

class _GrtView extends StatefulWidget {
  final String loanNo;
  final DateTime? applicationDate;
  final double loanAmount;

  const _GrtView({required this.loanNo, required this.applicationDate, required this.loanAmount});

  @override
  State<_GrtView> createState() => _GrtViewState();
}

class _GrtViewState extends State<_GrtView> {
  final _remarksController = TextEditingController();
  final _dateController = TextEditingController();

  DateTime? _grtDate;
  bool _passed = true;
  bool? _financeVerified;
  File? _documentFile;
  bool _processingDocument = false;

  @override
  void initState() {
    super.initState();
    _grtDate = DateTime.now();
    _dateController.text = _fmt(_grtDate!);
  }

  @override
  void dispose() {
    _remarksController.dispose();
    _dateController.dispose();
    super.dispose();
  }

  String _fmt(DateTime d) => '${d.day}/${d.month}/${d.year}';

  bool _isSameAsApplicationDate(DateTime d) {
    final app = widget.applicationDate;
    if (app == null) return false;
    return d.year == app.year && d.month == app.month && d.day == app.day;
  }

  Future<void> _showSameDatePopup() {
    final l10n = AppLocalizations.of(context)!;
    return showDialog(
      context: context,
      builder: (context) => AlertDialog(
        icon: const Icon(Icons.error_outline_rounded, color: Colors.red, size: 32),
        title: Text(l10n.grtNotAllowedTitle),
        content: Text(l10n.grtNotAllowedBody),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: Text(l10n.grtOk)),
        ],
      ),
    );
  }

  /// GRT can never be backdated -- the calendar only ever offers today, every
  /// earlier date shows disabled/greyed out.
  DateTime get _today => DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _grtDate ?? _today,
      firstDate: _today,
      lastDate: _today,
    );
    if (picked == null) return;
    if (_isSameAsApplicationDate(picked)) {
      await _showSameDatePopup();
      return;
    }
    setState(() {
      _grtDate = picked;
      _dateController.text = _fmt(picked);
    });
  }

  Future<void> _pickDocument() async {
    final l10n = AppLocalizations.of(context)!;
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        margin: const EdgeInsets.all(12),
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(color: AppColors.surface(context), borderRadius: BorderRadius.circular(18)),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_rounded, color: Color(0xFFE65100)),
              title: Text(l10n.grtTakePhoto),
              onTap: () async {
                Navigator.pop(context);
                final picked = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 80);
                if (picked == null) return;
                final file = File(picked.path);
                if (mounted) setState(() => _processingDocument = true);
                try {
                  await stampLocationOnPhoto(file);
                } finally {
                  if (mounted) setState(() => _processingDocument = false);
                }
                if (mounted) setState(() => _documentFile = file);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded, color: Color(0xFFE65100)),
              title: Text(l10n.grtChooseFromGallery),
              onTap: () async {
                Navigator.pop(context);
                final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
                if (picked != null) setState(() => _documentFile = File(picked.path));
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    final l10n = AppLocalizations.of(context)!;
    if (_grtDate == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.grtSelectDateRequired)));
      return;
    }
    if (_isSameAsApplicationDate(_grtDate!)) {
      await _showSameDatePopup();
      return;
    }
    if (_financeVerified == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.grtFinanceVerifiedRequired)));
      return;
    }
    if (_documentFile == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.grtPhotoRequired)));
      return;
    }
    final vm = context.read<GrtViewModel>();
    final ok = await vm.submit(
      passed: _passed,
      grtDate: _grtDate!,
      financeAmountVerified: _financeVerified == true ? widget.loanAmount : null,
      remarks: _remarksController.text.trim(),
      documentFile: _documentFile,
    );
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.grtRecordedSuccess)));
      Navigator.of(context).pop(true);
    } else if (vm.submitError != null) {
      if (vm.submitError!.toLowerCase().contains('same date')) {
        await _showSameDatePopup();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.submitError!), backgroundColor: Colors.red.shade600));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<GrtViewModel>();
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.loanDetailConductGrt)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: [
          Container(
            margin: const EdgeInsets.only(bottom: 16),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(color: AppColors.accentTint(context), borderRadius: BorderRadius.circular(12)),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.receipt_long_rounded, size: 18, color: Color(0xFFE65100)),
                    const SizedBox(width: 8),
                    Text(l10n.grtLoanLabel, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        widget.loanNo,
                        style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFFBF360C)),
                        overflow: TextOverflow.ellipsis,
                        textAlign: TextAlign.right,
                      ),
                    ),
                  ],
                ),
                if (widget.applicationDate != null) ...[
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      const Icon(Icons.event_rounded, size: 18, color: Color(0xFFE65100)),
                      const SizedBox(width: 8),
                      Text(l10n.grtProposalEnteredOn, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          _fmt(widget.applicationDate!),
                          style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFFBF360C)),
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.right,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          if (vm.loadingDetail)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (vm.loadError != null)
            Container(
              margin: const EdgeInsets.only(bottom: 16),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(color: AppColors.warningTint(context), borderRadius: BorderRadius.circular(12)),
              child: Row(
                children: [
                  Icon(Icons.error_outline_rounded, size: 18, color: Colors.orange.shade800),
                  const SizedBox(width: 8),
                  Expanded(child: Text(vm.loadError!, style: TextStyle(fontSize: 12, color: Colors.orange.shade900))),
                ],
              ),
            )
          else if (vm.detail != null) ...[
            FormSectionCard(
              icon: Icons.badge_rounded,
              title: l10n.grtApplicantSummary,
              children: [
                _readonly(context, l10n.grtBranchName, vm.detail!.branchName ?? '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtEnrollmentId, vm.detail!.enrollmentNo ?? '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtBorrowerName, vm.detail!.borrowerName),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtCoBorrowerName, vm.detail!.coBorrowerName ?? '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtCenterName, vm.detail!.centerName ?? '-'),
                if (vm.detail!.loanCycle != null) ...[
                  const SizedBox(height: 12),
                  _readonly(context, l10n.grtLoanCycle, '${vm.detail!.loanCycle}'),
                ],
              ],
            ),
            FormSectionCard(
              icon: Icons.request_quote_rounded,
              title: l10n.grtLoanDetailsSection,
              children: [
                _readonly(context, l10n.grtProduct, vm.detail!.schemeName ?? '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtLoanAmount, '₹${vm.detail!.loanAmount.toStringAsFixed(0)}'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtEmiAmount, '₹${vm.detail!.emiAmount.toStringAsFixed(0)}'),
              ],
            ),
            FormSectionCard(
              icon: Icons.account_balance_wallet_rounded,
              title: l10n.grtIncomeAssessment,
              children: [
                _readonly(context, l10n.grtSelfIncome, vm.detail!.selfIncome != null ? '₹${vm.detail!.selfIncome!.toStringAsFixed(0)}' : '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtHusbandSonIncome, vm.detail!.husbandSonIncome != null ? '₹${vm.detail!.husbandSonIncome!.toStringAsFixed(0)}' : '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtOtherIncome, vm.detail!.otherIncome != null ? '₹${vm.detail!.otherIncome!.toStringAsFixed(0)}' : '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtRemittanceIncome, vm.detail!.residentIncome != null ? '₹${vm.detail!.residentIncome!.toStringAsFixed(0)}' : '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtTotalIncome, '₹${vm.detail!.totalIncome.toStringAsFixed(0)}'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtHouseExpenses, vm.detail!.houseExpenses != null ? '₹${vm.detail!.houseExpenses!.toStringAsFixed(0)}' : '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtMfiExpenses, vm.detail!.mfiExpenses != null ? '₹${vm.detail!.mfiExpenses!.toStringAsFixed(0)}' : '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtBusinessExpenses, vm.detail!.businessExpenses != null ? '₹${vm.detail!.businessExpenses!.toStringAsFixed(0)}' : '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtTotalExpenses, '₹${vm.detail!.totalExpenses.toStringAsFixed(0)}'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtDisposableIncome, '₹${vm.detail!.disposableIncome.toStringAsFixed(0)}'),
              ],
            ),
            FormSectionCard(
              icon: Icons.account_balance_rounded,
              title: l10n.grtBankDetails,
              children: [
                _readonly(context, l10n.grtIfscCode, vm.detail!.ifscCode ?? '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtBankName, vm.detail!.bankName ?? '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtBankBranch, vm.detail!.bankBranch ?? '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtAccountNumber, vm.detail!.accountNumber ?? '-'),
                const SizedBox(height: 12),
                _readonly(context, l10n.grtBeneficiaryName, vm.detail!.beneficiaryName ?? '-'),
              ],
            ),
          ],
          FormSectionCard(
            icon: Icons.groups_rounded,
            title: l10n.grtDetailsSection,
            children: [
              TextFormField(
                controller: _dateController,
                readOnly: true,
                onTap: _pickDate,
                decoration: appFieldDecoration(l10n.grtDateLabel),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _StatusChip(
                      label: l10n.grtPass,
                      selected: _passed,
                      color: const Color(0xFF2FBF87),
                      onTap: () => setState(() => _passed = true),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _StatusChip(
                      label: l10n.grtFail,
                      selected: !_passed,
                      color: Colors.red.shade400,
                      onTap: () => setState(() => _passed = false),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(l10n.grtFinanceVerifiedLabel, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: _StatusChip(
                      label: l10n.grtYes,
                      selected: _financeVerified == true,
                      color: const Color(0xFF2FBF87),
                      onTap: () => setState(() => _financeVerified = true),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _StatusChip(
                      label: l10n.grtNo,
                      selected: _financeVerified == false,
                      color: Colors.red.shade400,
                      onTap: () => setState(() => _financeVerified = false),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _remarksController,
                decoration: appFieldDecoration(l10n.loanDetailRemarksOptional),
                maxLines: 2,
              ),
              const SizedBox(height: 12),
              Text(l10n.grtPhotoLabel, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Material(
                color: Colors.transparent,
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: _processingDocument ? null : _pickDocument,
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: AppColors.surfaceAlt(context), borderRadius: BorderRadius.circular(12)),
                    child: Row(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(10),
                          child: _processingDocument
                              ? Container(
                                  width: 44,
                                  height: 44,
                                  color: AppColors.border(context),
                                  child: const Center(child: SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))),
                                )
                              : _documentFile != null
                                  ? GestureDetector(
                                      onTap: () => showLocalPhotoPreview(context, _documentFile!),
                                      child: Image.file(_documentFile!, width: 44, height: 44, fit: BoxFit.cover),
                                    )
                                  : Container(
                                      width: 44,
                                      height: 44,
                                      color: AppColors.border(context),
                                      child: const Icon(Icons.add_a_photo_rounded, color: Color(0xFFE65100), size: 20),
                                    ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            _processingDocument ? l10n.grtProcessingPhoto : (_documentFile != null ? l10n.grtDocumentPhoto : l10n.grtTapToCapture),
                            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5),
                          ),
                        ),
                        if (!_processingDocument)
                          Icon(
                            _documentFile != null ? Icons.check_circle_rounded : Icons.chevron_right_rounded,
                            color: _documentFile != null ? Colors.green.shade600 : AppColors.textFaint(context),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SubmitButton(
            loading: vm.submitting,
            label: _processingDocument ? l10n.grtProcessingPhoto : l10n.grtSubmitButton,
            onPressed: _processingDocument ? null : _submit,
          ),
        ],
      ),
    );
  }
}

Widget _readonly(BuildContext context, String label, String value) {
  return TextFormField(
    readOnly: true,
    controller: TextEditingController(text: value),
    style: TextStyle(color: AppColors.textPrimary(context), fontWeight: FontWeight.w600),
    decoration: appFieldDecoration(label),
  );
}

class _StatusChip extends StatelessWidget {
  final String label;
  final bool selected;
  final Color color;
  final VoidCallback onTap;

  const _StatusChip({required this.label, required this.selected, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: selected ? color.withValues(alpha: 0.12) : const Color(0xFFF4F4FA),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: selected ? color : Colors.transparent, width: 1.5),
          ),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, color: selected ? color : AppColors.textMuted(context)),
          ),
        ),
      ),
    );
  }
}
