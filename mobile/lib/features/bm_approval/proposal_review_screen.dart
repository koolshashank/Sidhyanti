import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/loans_api.dart';
import '../../core/models/proposal_review.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../operations/widgets/form_section_card.dart';
import '../operations/widgets/form_styles.dart';
import 'proposal_review_viewmodel.dart';

/// Opened by a Branch/Area Manager from their approval queue (tap a
/// proposal card) -- shows everything the CM captured (applicant summary,
/// loan details, income assessment, bank details), lets the reviewer
/// correct any of it, then approve or reject right here.
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

  int? _schemeId;
  int? _purposeId;
  bool _initialized = false;
  bool _ifscLookingUp = false;
  String? _ifscError;

  @override
  void dispose() {
    for (final c in [
      _amountController, _emiController, _selfIncome, _husbandSonIncome, _otherIncome, _residentIncome,
      _houseExpenses, _mfiExpenses, _businessExpenses, _ifscController,
      _bankNameController, _bankBranchController, _bankAddressController,
      _accountNumberController, _beneficiaryNameController,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  void _initFrom(ProposalReviewDetail d) {
    if (_initialized) return;
    _initialized = true;
    _schemeId = d.schemeId;
    _purposeId = d.purposeId;
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

  Future<void> _onApprove(ProposalReviewDetail d) async {
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
    final saved = await vm.saveChanges(
      schemeId: _schemeId!,
      amount: amount,
      purposeId: _purposeId,
      emiAmount: double.tryParse(_emiController.text.trim()),
      selfIncome: double.tryParse(_selfIncome.text.trim()),
      husbandSonIncome: double.tryParse(_husbandSonIncome.text.trim()),
      otherIncome: double.tryParse(_otherIncome.text.trim()),
      residentIncome: double.tryParse(_residentIncome.text.trim()),
      houseExpenses: double.tryParse(_houseExpenses.text.trim()),
      mfiExpenses: double.tryParse(_mfiExpenses.text.trim()),
      businessExpenses: double.tryParse(_businessExpenses.text.trim()),
      ifscCode: _ifscController.text.trim(),
      bankName: _bankNameController.text.trim(),
      bankBranch: _bankBranchController.text.trim(),
      bankAddress: _bankAddressController.text.trim(),
      accountNumber: _accountNumberController.text.trim(),
      beneficiaryName: _beneficiaryNameController.text.trim(),
    );
    if (!mounted) return;
    if (!saved) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.saveError ?? 'Failed to save changes'), backgroundColor: Colors.red.shade600));
      return;
    }
    final decided = await vm.decide(loanApprovalId: d.loanApprovalId!, approve: true, remarks: remarks.isEmpty ? null : remarks);
    if (!mounted) return;
    if (decided) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Loan proposal approved')));
      Navigator.of(context).pop(true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.decideError ?? 'Failed to approve'), backgroundColor: Colors.red.shade600));
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
                          ],
                        ),
                        FormSectionCard(
                          icon: Icons.request_quote_rounded,
                          title: 'Loan Details',
                          children: [
                            DropdownButtonFormField<int>(
                              initialValue: _schemeId,
                              decoration: appFieldDecoration('Product *'),
                              isExpanded: true,
                              items: vm.schemes
                                  .map((s) => DropdownMenuItem(value: s.id, child: Text('${s.name} — ${money(s.defaultAmount)}')))
                                  .toList(),
                              onChanged: (v) => setState(() => _schemeId = v),
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
                              decoration: appFieldDecoration('Resident Income'),
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
                                  backgroundColor: const Color(0xFF2FBF87),
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
