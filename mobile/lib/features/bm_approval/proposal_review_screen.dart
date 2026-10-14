import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/api/loans_api.dart';
import '../../core/models/proposal_review.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/geo_photo_stamp.dart';
import '../operations/widgets/form_section_card.dart';
import '../operations/widgets/form_styles.dart';
import '../operations/widgets/local_photo_preview.dart';
import 'proposal_confirm_screen.dart';
import 'proposal_review_viewmodel.dart';

/// Opened by a Branch/Area Manager from their approval queue (tap a
/// proposal card) -- shows everything the CM captured (applicant summary,
/// loan details, income assessment, bank details), lets the reviewer
/// correct any of it (the Product can only be downgraded to a cheaper one,
/// never upgraded above what the CM originally proposed), conduct GRT right
/// here, then approve (only once GRT has passed) or reject.
class ProposalReviewScreen extends StatelessWidget {
  final int loanId;

  const ProposalReviewScreen({super.key, required this.loanId});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => ProposalReviewViewModel(context.read<LoansApi>(), loanId),
      child: const _ProposalReviewView(),
    );
  }
}

class _ProposalReviewView extends StatefulWidget {
  const _ProposalReviewView();

  @override
  State<_ProposalReviewView> createState() => _ProposalReviewViewState();
}

class _ProposalReviewViewState extends State<_ProposalReviewView> {
  final _amountController = TextEditingController();
  final _emiController = TextEditingController();
  final _selfIncome = TextEditingController();
  final _husbandSonIncome = TextEditingController();
  final _otherIncome = TextEditingController();
  final _residentIncome = TextEditingController();
  final _houseExpenses = TextEditingController();
  final _mfiExpenses = TextEditingController();
  final _businessExpenses = TextEditingController();
  final _ifscController = TextEditingController();
  final _bankNameController = TextEditingController();
  final _bankBranchController = TextEditingController();
  final _bankAddressController = TextEditingController();
  final _accountNumberController = TextEditingController();
  final _beneficiaryNameController = TextEditingController();
  final _grtDateController = TextEditingController();
  final _grtRemarksController = TextEditingController();

  int? _schemeId;
  int? _purposeId;
  double _originalAmount = 0;
  bool _initialized = false;
  bool _ifscLookingUp = false;
  String? _ifscError;

  DateTime? _grtDate;
  bool _grtPassedChoice = true;
  bool? _grtFinanceVerified;
  File? _grtDocument;
  bool _grtProcessingPhoto = false;

  @override
  void dispose() {
    for (final c in [
      _amountController, _emiController, _selfIncome, _husbandSonIncome, _otherIncome, _residentIncome,
      _houseExpenses, _mfiExpenses, _businessExpenses, _ifscController,
      _bankNameController, _bankBranchController, _bankAddressController,
      _accountNumberController, _beneficiaryNameController,
      _grtDateController, _grtRemarksController,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  String _fmtDate(DateTime d) => '${d.day}/${d.month}/${d.year}';

  void _initFrom(ProposalReviewDetail d) {
    if (_initialized) return;
    _initialized = true;
    _schemeId = d.schemeId;
    _purposeId = d.purposeId;
    _originalAmount = d.loanAmount;
    _amountController.text = d.loanAmount.toStringAsFixed(0);
    _emiController.text = d.emiAmount > 0 ? d.emiAmount.toStringAsFixed(0) : '';
    _selfIncome.text = d.selfIncome?.toStringAsFixed(0) ?? '';
    _husbandSonIncome.text = d.husbandSonIncome?.toStringAsFixed(0) ?? '';
    _otherIncome.text = d.otherIncome?.toStringAsFixed(0) ?? '';
    _residentIncome.text = d.residentIncome?.toStringAsFixed(0) ?? '';
    _houseExpenses.text = d.houseExpenses?.toStringAsFixed(0) ?? '';
    _mfiExpenses.text = d.mfiExpenses?.toStringAsFixed(0) ?? '';
    _businessExpenses.text = d.businessExpenses?.toStringAsFixed(0) ?? '';
    _ifscController.text = d.ifscCode ?? '';
    _bankNameController.text = d.bankName ?? '';
    _bankBranchController.text = d.bankBranch ?? '';
    _bankAddressController.text = d.bankAddress ?? '';
    _accountNumberController.text = d.accountNumber ?? '';
    _beneficiaryNameController.text = d.beneficiaryName ?? '';
  }

  double _num(TextEditingController c) => double.tryParse(c.text.trim()) ?? 0;
  double get _totalIncome => _num(_selfIncome) + _num(_husbandSonIncome) + _num(_otherIncome) + _num(_residentIncome);
  double get _totalExpenses => _num(_houseExpenses) + _num(_mfiExpenses) + _num(_businessExpenses);
  double get _disposableIncome => _totalIncome - _totalExpenses;

  /// The reviewer may only move to a cheaper product than what the CM
  /// originally proposed, never a more expensive one -- the dropdown simply
  /// never offers anything above [_originalAmount]. The currently selected
  /// scheme is always kept in the list even if it doesn't satisfy that (so
  /// the dropdown never crashes on a value missing from its own items).
  List<LoanScheme> _selectableSchemes(List<LoanScheme> schemes) {
    return schemes.where((s) => s.defaultAmount <= _originalAmount || s.id == _schemeId).toList();
  }

  /// Deliberately never touches the income/expense controllers -- reducing
  /// the amount or product only changes Amount Applied/EMI, the household
  /// Income Assessment the CM captured stays exactly as entered.
  void _onSchemeChanged(int? schemeId, List<LoanScheme> schemes) {
    setState(() {
      _schemeId = schemeId;
      LoanScheme? scheme;
      for (final s in schemes) {
        if (s.id == schemeId) {
          scheme = s;
          break;
        }
      }
      if (scheme != null) {
        _amountController.text = scheme.defaultAmount.toStringAsFixed(0);
        if (scheme.emiAmount != null) {
          _emiController.text = scheme.emiAmount!.toStringAsFixed(0);
        }
      }
    });
  }

  /// Same free public IFSC lookup the web CRM's member_view.php bank-details
  /// form (and the Loan Proposal screen) already use.
  Future<void> _lookupIfsc() async {
    final code = _ifscController.text.trim().toUpperCase();
    _ifscController.text = code;
    if (!RegExp(r'^[A-Z]{4}0[A-Z0-9]{6}$').hasMatch(code)) {
      setState(() => _ifscError = code.isEmpty ? null : 'Enter a valid 11-character IFSC code');
      return;
    }
    setState(() {
      _ifscLookingUp = true;
      _ifscError = null;
    });
    try {
      final response = await Dio().get('https://ifsc.razorpay.com/$code');
      final data = response.data as Map<String, dynamic>;
      setState(() {
        _bankNameController.text = data['BANK'] as String? ?? '';
        _bankBranchController.text = data['BRANCH'] as String? ?? '';
        _bankAddressController.text = data['ADDRESS'] as String? ?? '';
      });
    } catch (_) {
      setState(() {
        _ifscError = 'IFSC not found -- please check the code';
        _bankNameController.clear();
        _bankBranchController.clear();
        _bankAddressController.clear();
      });
    } finally {
      if (mounted) setState(() => _ifscLookingUp = false);
    }
  }

  /// GRT can never be backdated -- the calendar only ever offers today,
  /// every earlier date shows disabled/greyed out.
  DateTime get _today => DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);

  Future<void> _pickGrtDate(DateTime? applicationDate) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _grtDate ?? _today,
      firstDate: _today,
      lastDate: _today,
    );
    if (picked == null) return;
    setState(() {
      _grtDate = picked;
      _grtDateController.text = _fmtDate(picked);
    });
  }

  void _pickGrtDocument() {
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
              title: const Text('Take Photo'),
              onTap: () async {
                Navigator.pop(context);
                final picked = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 80);
                if (picked == null) return;
                final file = File(picked.path);
                if (mounted) setState(() => _grtProcessingPhoto = true);
                try {
                  await stampLocationOnPhoto(file);
                } finally {
                  if (mounted) setState(() => _grtProcessingPhoto = false);
                }
                if (mounted) setState(() => _grtDocument = file);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded, color: Color(0xFFE65100)),
              title: const Text('Choose from Gallery'),
              onTap: () async {
                Navigator.pop(context);
                final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
                if (picked != null) setState(() => _grtDocument = File(picked.path));
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submitGrt(double loanAmount) async {
    if (_grtDate == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select the GRT date')));
      return;
    }
    if (_grtFinanceVerified == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select whether the finance amount was verified')));
      return;
    }
    if (_grtDocument == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('GRT photo is required')));
      return;
    }
    final vm = context.read<ProposalReviewViewModel>();
    final ok = await vm.submitGrt(
      passed: _grtPassedChoice,
      grtDate: _grtDate!,
      financeAmountVerified: _grtFinanceVerified == true ? loanAmount : null,
      remarks: _grtRemarksController.text.trim(),
      documentFile: _grtDocument,
    );
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('GRT recorded')));
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.grtError ?? 'Failed to record GRT'), backgroundColor: Colors.red.shade600));
    }
  }

  Future<String?> _showRemarksDialog({required bool approve}) {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(approve ? 'Approve this proposal?' : 'Reject this proposal?'),
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

  Future<void> _showGrtRequiredDialog() {
    return showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('GRT Required'),
        content: const Text('This proposal cannot be approved until GRT has been conducted here and the result is Pass.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('OK')),
        ],
      ),
    );
  }

  Future<void> _onApprove(ProposalReviewDetail d) async {
    if (!d.grtPassed) {
      await _showGrtRequiredDialog();
      return;
    }
    if (_schemeId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select a Product')));
      return;
    }
    final amount = double.tryParse(_amountController.text.trim());
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please enter a valid amount')));
      return;
    }
    final remarks = await _showRemarksDialog(approve: true);
    if (remarks == null || !mounted) return;

    final vm = context.read<ProposalReviewViewModel>();
    String? schemeLabel;
    for (final s in vm.schemes) {
      if (s.id == _schemeId) {
        schemeLabel = '${schemeBaseName(s.name)} (${s.defaultAmount.toStringAsFixed(0)})';
        break;
      }
    }
    String? purposeName;
    for (final p in vm.purposes) {
      if (p.id == _purposeId) {
        purposeName = p.name;
        break;
      }
    }

    final confirmed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ChangeNotifierProvider.value(
          value: vm,
          child: ProposalConfirmScreen(
            detail: d,
            schemeId: _schemeId!,
            productLabel: schemeLabel ?? '-',
            amount: amount,
            emiAmount: double.tryParse(_emiController.text.trim()),
            purposeId: _purposeId,
            purposeName: purposeName,
            selfIncome: double.tryParse(_selfIncome.text.trim()),
            husbandSonIncome: double.tryParse(_husbandSonIncome.text.trim()),
            otherIncome: double.tryParse(_otherIncome.text.trim()),
            residentIncome: double.tryParse(_residentIncome.text.trim()),
            totalIncome: _totalIncome,
            houseExpenses: double.tryParse(_houseExpenses.text.trim()),
            mfiExpenses: double.tryParse(_mfiExpenses.text.trim()),
            businessExpenses: double.tryParse(_businessExpenses.text.trim()),
            totalExpenses: _totalExpenses,
            disposableIncome: _disposableIncome,
            ifscCode: _ifscController.text.trim(),
            bankName: _bankNameController.text.trim(),
            bankBranch: _bankBranchController.text.trim(),
            bankAddress: _bankAddressController.text.trim(),
            accountNumber: _accountNumberController.text.trim(),
            beneficiaryName: _beneficiaryNameController.text.trim(),
            remarks: remarks.isEmpty ? null : remarks,
          ),
        ),
      ),
    );
    if (confirmed == true && mounted) {
      Navigator.of(context).pop(true);
    }
  }

  Future<void> _onReject(ProposalReviewDetail d) async {
    final remarks = await _showRemarksDialog(approve: false);
    if (remarks == null || !mounted) return;
    final vm = context.read<ProposalReviewViewModel>();
    final decided = await vm.decide(loanApprovalId: d.loanApprovalId!, approve: false, remarks: remarks.isEmpty ? null : remarks);
    if (!mounted) return;
    if (decided) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Loan proposal rejected')));
      Navigator.of(context).pop(true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.decideError ?? 'Failed to reject'), backgroundColor: Colors.red.shade600));
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<ProposalReviewViewModel>();
    final d = vm.detail;
    if (d != null) _initFrom(d);
    final busy = vm.saving || vm.deciding;

    return Scaffold(
      appBar: AppBar(title: const Text('Review Loan Proposal')),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.loadError != null || d == null
              ? Center(child: Text(vm.loadError ?? 'Loan not found'))
              : d.loanApprovalId == null
                  ? const Center(child: Text('This loan has no pending proposal request.'))
                  : ListView(
                      padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
                      children: [
                        if (d.approvalStatus == 'BM Approved')
                          Container(
                            margin: const EdgeInsets.only(bottom: 16),
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                            decoration: BoxDecoration(color: AppColors.warningTint(context), borderRadius: BorderRadius.circular(12)),
                            child: Row(
                              children: [
                                Icon(Icons.info_outline_rounded, size: 18, color: Colors.orange.shade800),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    'Already approved by Branch Manager -- awaiting your final sign-off.',
                                    style: TextStyle(fontSize: 12, color: Colors.orange.shade900),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        FormSectionCard(
                          icon: Icons.badge_rounded,
                          title: 'Applicant Summary',
                          children: [
                            _readonly('Branch Name', d.branchName ?? '-'),
                            const SizedBox(height: 12),
                            _readonly('Enrollment ID', d.enrollmentNo ?? '-'),
                            const SizedBox(height: 12),
                            _readonly('Borrower Name', d.borrowerName),
                            const SizedBox(height: 12),
                            _readonly('Co-Borrower Name', d.coBorrowerName ?? '-'),
                            const SizedBox(height: 12),
                            _readonly('Center Name', d.centerName ?? '-'),
                            const SizedBox(height: 12),
                            _readonly('Proposal Entered On', fdate(d.applicationDate)),
                            if (d.loanCycle != null) ...[
                              const SizedBox(height: 12),
                              _readonly('Loan Cycle', '${d.loanCycle}'),
                            ],
                          ],
                        ),
                        FormSectionCard(
                          icon: Icons.request_quote_rounded,
                          title: 'Loan Details',
                          children: [
                            DropdownButtonFormField<int>(
                              initialValue: _schemeId,
                              decoration: appFieldDecoration('Product * (can only be reduced)'),
                              isExpanded: true,
                              items: _selectableSchemes(vm.schemes)
                                  .map((s) => DropdownMenuItem(value: s.id, child: Text('${schemeBaseName(s.name)} (${s.defaultAmount.toStringAsFixed(0)})')))
                                  .toList(),
                              onChanged: (v) => _onSchemeChanged(v, vm.schemes),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _amountController,
                              decoration: appFieldDecoration('Amount Applied *'),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _emiController,
                              decoration: appFieldDecoration('EMI Amount'),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            ),
                            const SizedBox(height: 12),
                            DropdownButtonFormField<int>(
                              initialValue: _purposeId,
                              decoration: appFieldDecoration('Nature of Business'),
                              isExpanded: true,
                              items: vm.purposes.map((p) => DropdownMenuItem(value: p.id, child: Text(p.name))).toList(),
                              onChanged: (v) => setState(() => _purposeId = v),
                            ),
                          ],
                        ),
                        FormSectionCard(
                          icon: Icons.account_balance_wallet_rounded,
                          title: 'Income Assessment',
                          children: [
                            TextFormField(
                              controller: _selfIncome,
                              decoration: appFieldDecoration('Self Income'),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _husbandSonIncome,
                              decoration: appFieldDecoration('Husband/Son Income'),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _otherIncome,
                              decoration: appFieldDecoration('Other Income'),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _residentIncome,
                              decoration: appFieldDecoration('Remittance Income'),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            _readonly('Total Income', '₹${_totalIncome.toStringAsFixed(0)}'),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _houseExpenses,
                              decoration: appFieldDecoration('House Expenses'),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _mfiExpenses,
                              decoration: appFieldDecoration('Other MFI Loan Repayment (Monthly)'),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _businessExpenses,
                              decoration: appFieldDecoration('Business Expenses'),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            _readonly('Total Expenses', '₹${_totalExpenses.toStringAsFixed(0)}'),
                            const SizedBox(height: 12),
                            _readonly('Disposable Income', '₹${_disposableIncome.toStringAsFixed(0)}'),
                          ],
                        ),
                        FormSectionCard(
                          icon: Icons.account_balance_rounded,
                          title: 'Bank Details',
                          children: [
                            TextFormField(
                              controller: _ifscController,
                              decoration: appFieldDecoration('IFSC Code').copyWith(
                                errorText: _ifscError,
                                suffixIcon: _ifscLookingUp
                                    ? const Padding(
                                        padding: EdgeInsets.all(12),
                                        child: SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                                      )
                                    : IconButton(icon: const Icon(Icons.search_rounded), onPressed: _lookupIfsc),
                              ),
                              textCapitalization: TextCapitalization.characters,
                              onFieldSubmitted: (_) => _lookupIfsc(),
                            ),
                            const SizedBox(height: 12),
                            _readonly('Bank Name (auto-filled from IFSC)', _bankNameController.text.isEmpty ? '-' : _bankNameController.text),
                            const SizedBox(height: 12),
                            _readonly('Bank Branch (auto-filled from IFSC)', _bankBranchController.text.isEmpty ? '-' : _bankBranchController.text),
                            const SizedBox(height: 12),
                            _readonly('Bank Address (auto-filled from IFSC)', _bankAddressController.text.isEmpty ? '-' : _bankAddressController.text),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _accountNumberController,
                              decoration: appFieldDecoration('Account Number'),
                              keyboardType: TextInputType.number,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _beneficiaryNameController,
                              decoration: appFieldDecoration('Beneficiary Name'),
                            ),
                          ],
                        ),
                        FormSectionCard(
                          icon: Icons.groups_rounded,
                          title: 'GRT (Group Recognition Test)',
                          children: [
                            if (d.grtStatus != null) ...[
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                                margin: const EdgeInsets.only(bottom: 12),
                                decoration: BoxDecoration(
                                  color: d.grtPassed ? Colors.green.shade50 : Colors.red.shade50,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Row(
                                  children: [
                                    Icon(
                                      d.grtPassed ? Icons.check_circle_rounded : Icons.cancel_rounded,
                                      size: 18,
                                      color: d.grtPassed ? Colors.green.shade700 : Colors.red.shade700,
                                    ),
                                    const SizedBox(width: 8),
                                    Text(
                                      'Last GRT: ${d.grtStatus} ${d.grtDate != null ? 'on ${_fmtDate(d.grtDate!)}' : ''}',
                                      style: TextStyle(
                                        fontSize: 12.5,
                                        fontWeight: FontWeight.w600,
                                        color: d.grtPassed ? Colors.green.shade800 : Colors.red.shade800,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                            TextFormField(
                              controller: _grtDateController,
                              readOnly: true,
                              onTap: () => _pickGrtDate(d.applicationDate),
                              decoration: appFieldDecoration('GRT Date *'),
                            ),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                Expanded(
                                  child: _GrtStatusChip(
                                    label: 'Pass',
                                    selected: _grtPassedChoice,
                                    color: const Color(0xFF2FBF87),
                                    onTap: () => setState(() => _grtPassedChoice = true),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: _GrtStatusChip(
                                    label: 'Fail',
                                    selected: !_grtPassedChoice,
                                    color: Colors.red.shade400,
                                    onTap: () => setState(() => _grtPassedChoice = false),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            Text('Finance Amount Verified *', style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                Expanded(
                                  child: _GrtStatusChip(
                                    label: 'Yes',
                                    selected: _grtFinanceVerified == true,
                                    color: const Color(0xFF2FBF87),
                                    onTap: () => setState(() => _grtFinanceVerified = true),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: _GrtStatusChip(
                                    label: 'No',
                                    selected: _grtFinanceVerified == false,
                                    color: Colors.red.shade400,
                                    onTap: () => setState(() => _grtFinanceVerified = false),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _grtRemarksController,
                              decoration: appFieldDecoration('Remarks (optional)'),
                              maxLines: 2,
                            ),
                            const SizedBox(height: 12),
                            Text('GRT Photo *', style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
                            const SizedBox(height: 8),
                            Material(
                              color: Colors.transparent,
                              child: InkWell(
                                borderRadius: BorderRadius.circular(12),
                                onTap: _grtProcessingPhoto ? null : _pickGrtDocument,
                                child: Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(color: AppColors.surfaceAlt(context), borderRadius: BorderRadius.circular(12)),
                                  child: Row(
                                    children: [
                                      ClipRRect(
                                        borderRadius: BorderRadius.circular(10),
                                        child: _grtProcessingPhoto
                                            ? Container(
                                                width: 44,
                                                height: 44,
                                                color: AppColors.border(context),
                                                child: const Center(child: SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))),
                                              )
                                            : _grtDocument != null
                                                ? GestureDetector(
                                                    onTap: () => showLocalPhotoPreview(context, _grtDocument!),
                                                    child: Image.file(_grtDocument!, width: 44, height: 44, fit: BoxFit.cover),
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
                                          _grtProcessingPhoto ? 'Processing photo...' : (_grtDocument != null ? 'GRT Document / Photo' : 'Tap to capture GRT photo'),
                                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5),
                                        ),
                                      ),
                                      if (!_grtProcessingPhoto)
                                        Icon(
                                          _grtDocument != null ? Icons.check_circle_rounded : Icons.chevron_right_rounded,
                                          color: _grtDocument != null ? Colors.green.shade600 : AppColors.textFaint(context),
                                        ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(height: 12),
                            SizedBox(
                              width: double.infinity,
                              child: OutlinedButton(
                                onPressed: (vm.grtSubmitting || _grtProcessingPhoto) ? null : () => _submitGrt(_num(_amountController)),
                                child: vm.grtSubmitting
                                    ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                    : const Text('Submit GRT'),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: const Color(0xFFDC3545),
                                  side: const BorderSide(color: Color(0xFFDC3545)),
                                  padding: const EdgeInsets.symmetric(vertical: 14),
                                ),
                                onPressed: busy ? null : () => _onReject(d),
                                child: const Text('Reject'),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: FilledButton(
                                style: FilledButton.styleFrom(
                                  backgroundColor: d.grtPassed ? const Color(0xFF2FBF87) : Colors.grey,
                                  padding: const EdgeInsets.symmetric(vertical: 14),
                                ),
                                onPressed: busy ? null : () => _onApprove(d),
                                child: busy
                                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                    : const Text('Save & Approve'),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
    );
  }

  Widget _readonly(String label, String value) {
    return TextFormField(
      readOnly: true,
      controller: TextEditingController(text: value),
      style: TextStyle(color: AppColors.textPrimary(context), fontWeight: FontWeight.w600),
      decoration: appFieldDecoration(label),
    );
  }
}

class _GrtStatusChip extends StatelessWidget {
  final String label;
  final bool selected;
  final Color color;
  final VoidCallback onTap;

  const _GrtStatusChip({required this.label, required this.selected, required this.color, required this.onTap});

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
            color: selected ? color.withValues(alpha: 0.12) : AppColors.surfaceAlt(context),
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
