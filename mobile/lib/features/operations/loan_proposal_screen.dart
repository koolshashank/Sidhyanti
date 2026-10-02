import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/loans_api.dart';
import '../../core/api/members_api.dart';
import '../../core/auth/auth_state.dart';
import '../../core/models/member.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'loan_proposal_viewmodel.dart';
import 'widgets/form_section_card.dart';
import 'widgets/form_styles.dart';

class LoanProposalScreen extends StatelessWidget {
  final int memberId;
  final String coBorrowerName;

  const LoanProposalScreen({super.key, required this.memberId, required this.coBorrowerName});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => LoanProposalViewModel(context.read<MembersApi>(), context.read<LoansApi>(), memberId),
      child: _LoanProposalView(coBorrowerName: coBorrowerName),
    );
  }
}

class _LoanProposalView extends StatefulWidget {
  final String coBorrowerName;

  const _LoanProposalView({required this.coBorrowerName});

  @override
  State<_LoanProposalView> createState() => _LoanProposalViewState();
}

class _LoanProposalViewState extends State<_LoanProposalView> {
  final _amountController = TextEditingController();
  final _durationController = TextEditingController();
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
  final _accountNumberConfirmController = TextEditingController();
  final _beneficiaryNameController = TextEditingController();

  int? _schemeId;
  int? _purposeId;
  bool _amountInitialized = false;
  bool _ifscLookingUp = false;
  String? _ifscError;

  @override
  void dispose() {
    for (final c in [
      _amountController, _durationController, _emiController,
      _selfIncome, _husbandSonIncome, _otherIncome, _residentIncome,
      _houseExpenses, _mfiExpenses, _businessExpenses,
      _ifscController, _bankNameController, _bankBranchController,
      _bankAddressController, _accountNumberController, _accountNumberConfirmController, _beneficiaryNameController,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  /// Same free public IFSC lookup the web CRM's member_view.php bank-details
  /// form already uses -- no API key required. Bank Name/Branch/Address are
  /// deliberately never made editable: they're only ever what this lookup
  /// returned, never free text.
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

  double _num(TextEditingController c) => double.tryParse(c.text.trim()) ?? 0;

  double get _totalIncome => _num(_selfIncome) + _num(_husbandSonIncome) + _num(_otherIncome) + _num(_residentIncome);
  double get _totalExpenses => _num(_houseExpenses) + _num(_mfiExpenses) + _num(_businessExpenses);
  double get _disposableIncome => _totalIncome - _totalExpenses;

  void _onSchemeChanged(int? schemeId, List<LoanScheme> schemes, {required bool isReloan}) {
    setState(() {
      _schemeId = schemeId;
      LoanScheme? scheme;
      for (final s in schemes) {
        if (s.id == schemeId) {
          scheme = s;
          break;
        }
      }
      // For a reloan the amount is driven by pre-approved eligibility, not the
      // scheme's default -- only tenure follows the selected scheme either way.
      if (!isReloan) {
        _amountController.text = scheme != null ? scheme.defaultAmount.toStringAsFixed(0) : '';
      }
      _durationController.text = scheme != null ? scheme.tenureMonths.toString() : '';
      // Only overwrite EMI when this scheme actually has one configured
      // (Loan_Scheme.InstallmentAmountSlot1) -- otherwise leave whatever the
      // field officer already typed alone.
      if (scheme?.emiAmount != null) {
        _emiController.text = scheme!.emiAmount!.toStringAsFixed(0);
      }
    });
  }

  /// FOIR-style affordability check: the EMI being proposed must not exceed
  /// 70% of the household's disposable income, or the member is treated as
  /// not eligible for this loan as structured.
  bool _checkEligibility() {
    final emi = double.tryParse(_emiController.text.trim()) ?? 0;
    if (emi <= 0) return true;
    if (emi <= _disposableIncome * 0.7) return true;
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Not Eligible'),
        content: Text(
          'The EMI (${money(emi)}) exceeds 70% of the disposable income (${money(_disposableIncome)}). '
          'This member is not eligible for this loan as structured -- reduce the EMI/amount or increase income before proceeding.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('OK')),
        ],
      ),
    );
    return false;
  }

  Future<void> _submit() async {
    if (_schemeId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select a Product')));
      return;
    }
    if (_accountNumberController.text.trim().isNotEmpty &&
        _accountNumberController.text.trim() != _accountNumberConfirmController.text.trim()) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Account numbers do not match')));
      return;
    }
    if (!_checkEligibility()) return;
    final vm = context.read<LoanProposalViewModel>();
    final reloan = vm.member?.reloanEligibility;
    double? amountOverride;
    if (reloan != null) {
      final entered = double.tryParse(_amountController.text.trim());
      if (entered == null || entered <= 0) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please enter a valid amount')));
        return;
      }
      if (entered > reloan.eligibleAmount) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text('Amount cannot exceed the pre-approved eligibility of ${money(reloan.eligibleAmount)}'),
          backgroundColor: Colors.red.shade600,
        ));
        return;
      }
      amountOverride = entered;
    }
    final result = await vm.submit(
      schemeId: _schemeId!,
      amount: amountOverride,
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
    if (result != null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Loan proposal saved')));
      context.go('/members/${vm.memberId}');
    } else if (vm.submitError != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(vm.submitError!), backgroundColor: Colors.red.shade600),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<LoanProposalViewModel>();
    final staff = context.watch<AuthState>().currentStaff;
    final reloan = vm.member?.reloanEligibility;

    if (reloan != null && !_amountInitialized) {
      _amountInitialized = true;
      _amountController.text = reloan.eligibleAmount.toStringAsFixed(0);
    }

    return Scaffold(
      appBar: AppBar(title: Text(reloan != null ? 'Reloan Proposal' : 'Loan Proposal')),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.loadError != null
              ? Center(child: Text(vm.loadError!))
              : ListView(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
                  children: [
                    if (reloan != null) ...[
                      _ReloanEligibilityCard(reloan: reloan),
                      const SizedBox(height: 16),
                    ],
                    FormSectionCard(
                      icon: Icons.badge_rounded,
                      title: 'Applicant Summary',
                      children: [
                        _readonly('Branch ID', staff?.branchId?.toString() ?? '-'),
                        const SizedBox(height: 12),
                        _readonly('Branch Name', vm.member?.branchName ?? '-'),
                        const SizedBox(height: 12),
                        _readonly('Enrollment ID', vm.member?.enrollmentNo ?? '-'),
                        const SizedBox(height: 12),
                        _readonly('Borrower Name', vm.member?.fullName ?? '-'),
                        const SizedBox(height: 12),
                        _readonly('Co-Borrower Name', widget.coBorrowerName),
                        const SizedBox(height: 12),
                        _readonly('Center Name', vm.member?.centerName ?? '-'),
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
                          onChanged: (v) => _onSchemeChanged(v, vm.schemes, isReloan: reloan != null),
                        ),
                        const SizedBox(height: 12),
                        reloan != null
                            ? TextFormField(
                                controller: _amountController,
                                decoration: appFieldDecoration('Amount Applied (up to ${money(reloan.eligibleAmount)})'),
                                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              )
                            : _readonly('Amount Applied', _amountController.text.isEmpty ? '-' : '₹${_amountController.text}'),
                        const SizedBox(height: 12),
                        _readonly('Duration of Loan', _durationController.text.isEmpty ? '-' : '${_durationController.text} months'),
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
                          obscureText: true,
                          onChanged: (_) => setState(() {}),
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _accountNumberConfirmController,
                          decoration: appFieldDecoration('Re-enter Account Number').copyWith(
                            suffixIcon: _accountNumberConfirmController.text.isEmpty
                                ? null
                                : Icon(
                                    _accountNumberController.text.trim() == _accountNumberConfirmController.text.trim()
                                        ? Icons.check_circle_rounded
                                        : Icons.error_rounded,
                                    color: _accountNumberController.text.trim() == _accountNumberConfirmController.text.trim()
                                        ? Colors.green.shade600
                                        : Colors.red.shade400,
                                  ),
                          ),
                          keyboardType: TextInputType.number,
                          obscureText: true,
                          onChanged: (_) => setState(() {}),
                        ),
                        const SizedBox(height: 12),
                        TextFormField(
                          controller: _beneficiaryNameController,
                          decoration: appFieldDecoration('Beneficiary Name'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    SubmitButton(loading: vm.submitting, label: 'FINISH', onPressed: _submit),
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

class _ReloanEligibilityCard extends StatelessWidget {
  final ReloanEligibility reloan;

  const _ReloanEligibilityCard({required this.reloan});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [BoxShadow(color: const Color(0xFF2FBF87).withValues(alpha: 0.3), blurRadius: 14, offset: const Offset(0, 6))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.verified_rounded, color: Colors.white, size: 19),
              const SizedBox(width: 8),
              const Text('Pre-Approved for Reloan', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
            ],
          ),
          const SizedBox(height: 8),
          Text(money(reloan.eligibleAmount), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 26)),
          const SizedBox(height: 4),
          Text(
            '${reloan.ruleName} · Loan #${reloan.cycleNumber} · Previous ${money(reloan.previousLoanAmount)} +${reloan.increasePercent.toStringAsFixed(0)}%',
            style: TextStyle(color: Colors.white.withValues(alpha: 0.9), fontSize: 11.5),
          ),
          const SizedBox(height: 2),
          Text(
            'CM can enter this amount or less — not more.',
            style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 10.5, fontStyle: FontStyle.italic),
          ),
        ],
      ),
    );
  }
}
