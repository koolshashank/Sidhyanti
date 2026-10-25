import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/reports_api.dart';
import '../../core/models/day_closing_report.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../operations/widgets/form_section_card.dart';
import '../operations/widgets/form_styles.dart';

/// Day Closing Report -- a per-branch cash reconciliation submitted once a
/// day by the Branch Manager/Cashier. The "auto" fields (loan disbursement,
/// opening balance, collection dues/collected, preclosure, arrear recovery)
/// are computed server-side and shown read-only; everything else is typed
/// in by hand. Totals update live as the form is filled in, then are
/// recomputed again server-side at submit time.
class DcrScreen extends StatefulWidget {
  const DcrScreen({super.key});

  @override
  State<DcrScreen> createState() => _DcrScreenState();
}

class _DcrScreenState extends State<DcrScreen> {
  final _date = DateTime.now();

  final _fundTransfer = TextEditingController();
  final _rentExpenses = TextEditingController();
  final _stationaryAssets = TextEditingController();
  final _salaryPayment = TextEditingController();
  final _otherExpenses = TextEditingController();
  final _pettyCashPayment = TextEditingController();
  final _cashDepositByHoBank = TextEditingController();
  final _cashDepositByBranchBank = TextEditingController();
  final _enrollmentFee = TextEditingController();
  final _riskFee = TextEditingController();
  final _processingFee = TextEditingController();
  final _otherIncome = TextEditingController();
  final _bankWithdrawal = TextEditingController();
  final _fundReceived = TextEditingController();
  final _fundReceivedByHo = TextEditingController();

  DayClosingPrefill? _prefill;
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  DayClosingResult? _result;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final c in [
      _fundTransfer, _rentExpenses, _stationaryAssets, _salaryPayment, _otherExpenses, _pettyCashPayment,
      _cashDepositByHoBank, _cashDepositByBranchBank, _enrollmentFee, _riskFee, _processingFee,
      _otherIncome, _bankWithdrawal, _fundReceived, _fundReceivedByHo,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final prefill = await context.read<ReportsApi>().dcrClosingPrefill(date: _date);
      if (!mounted) return;
      setState(() => _prefill = prefill);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  double _num(TextEditingController c) => double.tryParse(c.text) ?? 0;

  double get _totalOutflow =>
      (_prefill?.loanDisbursement ?? 0) +
      _num(_fundTransfer) +
      _num(_rentExpenses) +
      _num(_stationaryAssets) +
      _num(_salaryPayment) +
      _num(_otherExpenses) +
      _num(_pettyCashPayment) +
      _num(_cashDepositByHoBank) +
      _num(_cashDepositByBranchBank);

  // CollectionDues is reference-only (what was due, not what was banked) --
  // deliberately excluded here, matching the server's own computation.
  double get _totalInflow =>
      (_prefill?.openingBalance ?? 0) +
      (_prefill?.collectedAmount ?? 0) +
      _num(_enrollmentFee) +
      _num(_riskFee) +
      _num(_processingFee) +
      (_prefill?.preclosureAmount ?? 0) +
      (_prefill?.arrearRecoveryAmount ?? 0) +
      _num(_otherIncome) +
      _num(_bankWithdrawal) +
      _num(_fundReceived) +
      _num(_fundReceivedByHo);

  double get _grandTotal => _totalInflow - _totalOutflow;

  Future<void> _submit() async {
    setState(() => _submitting = true);
    try {
      final result = await context.read<ReportsApi>().dcrClosingSubmit(
            date: _date,
            fundTransferFromAnotherBranch: _num(_fundTransfer),
            rentExpenses: _num(_rentExpenses),
            stationaryAssets: _num(_stationaryAssets),
            salaryPayment: _num(_salaryPayment),
            otherExpenses: _num(_otherExpenses),
            pettyCashPayment: _num(_pettyCashPayment),
            cashDepositByHoBank: _num(_cashDepositByHoBank),
            cashDepositByBranchBank: _num(_cashDepositByBranchBank),
            enrollmentFee: _num(_enrollmentFee),
            riskFee: _num(_riskFee),
            processingFee: _num(_processingFee),
            otherIncome: _num(_otherIncome),
            bankWithdrawal: _num(_bankWithdrawal),
            fundReceived: _num(_fundReceived),
            fundReceivedByHo: _num(_fundReceivedByHo),
          );
      if (!mounted) return;
      setState(() => _result = result);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Widget _readonly(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        readOnly: true,
        controller: TextEditingController(text: value),
        style: TextStyle(color: AppColors.textPrimary(context), fontWeight: FontWeight.w600),
        decoration: appFieldDecoration(label),
      ),
    );
  }

  Widget _editable(String label, TextEditingController controller) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        decoration: appFieldDecoration(label),
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        onChanged: (_) => setState(() {}),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Day Closing Report')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
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
              : _result != null
                  ? _buildSuccess(_result!)
                  : (_prefill?.alreadySubmitted ?? false)
                      ? _buildAlreadySubmitted()
                      : _buildForm(),
    );
  }

  Widget _buildAlreadySubmitted() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.check_circle_rounded, size: 56, color: AppColors.textMuted(context)),
            const SizedBox(height: 16),
            Text(
              "Today's Day Closing Report has already been submitted.",
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.textMuted(context)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSuccess(DayClosingResult result) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.check_circle_rounded, size: 56, color: Color(0xFF2FBF87)),
            const SizedBox(height: 16),
            const Text('Day Closing Report submitted', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
            const SizedBox(height: 16),
            _summaryRow('Total Outflow', money(result.totalOutflow)),
            _summaryRow('Total Inflow', money(result.totalInflow)),
            _summaryRow('Grand Total', money(result.grandTotal), emphasize: true),
          ],
        ),
      ),
    );
  }

  Widget _summaryRow(String label, String value, {bool emphasize = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label, style: TextStyle(color: AppColors.textMuted(context))),
          const SizedBox(width: 12),
          Text(value, style: TextStyle(fontWeight: emphasize ? FontWeight.w800 : FontWeight.w600, fontSize: emphasize ? 16 : 14)),
        ],
      ),
    );
  }

  Widget _buildForm() {
    final prefill = _prefill!;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
      children: [
        FormSectionCard(
          icon: Icons.arrow_downward_rounded,
          title: 'InFlow',
          children: [
            _readonly('Opening Balance', money(prefill.openingBalance)),
            _readonly('Collection Dues', money(prefill.collectionDues)),
            _readonly('Collected Amount', money(prefill.collectedAmount)),
            _editable('Enrollment Fee', _enrollmentFee),
            _editable('Risk Fee', _riskFee),
            _editable('Processing Fee', _processingFee),
            _readonly('Preclosure Amount', money(prefill.preclosureAmount)),
            _readonly('Arrear Recovery Amount', money(prefill.arrearRecoveryAmount)),
            const SectionLabel('Default Recovery'),
            _editable('Other Income', _otherIncome),
            _editable('Bank Withdrawal', _bankWithdrawal),
            _editable('Fund Received', _fundReceived),
            _editable('Fund Receive by HO', _fundReceivedByHo),
            _readonly('Total Inflow', money(_totalInflow)),
          ],
        ),
        FormSectionCard(
          icon: Icons.arrow_upward_rounded,
          title: 'OutFlow',
          children: [
            _readonly('Loan Disbursement', money(prefill.loanDisbursement)),
            _editable('Fund Transfer From Another Branch', _fundTransfer),
            _editable('Rent Expenses', _rentExpenses),
            _editable('Stationary Assets', _stationaryAssets),
            _editable('Salary Payment', _salaryPayment),
            _editable('Other Expenses', _otherExpenses),
            _editable('Petty Cash Payment', _pettyCashPayment),
            _editable('Cash Deposit By HO Bank', _cashDepositByHoBank),
            _editable('Cash Deposit By Branch Bank', _cashDepositByBranchBank),
            _readonly('Total Outflow', money(_totalOutflow)),
          ],
        ),
        FormSectionCard(
          icon: Icons.summarize_rounded,
          title: 'Summary',
          children: [
            _readonly('Grand Total', money(_grandTotal)),
            _readonly('DCR Closing Date', fdate(_date)),
          ],
        ),
        const SizedBox(height: 8),
        SubmitButton(loading: _submitting, label: 'Submit', onPressed: _submit),
      ],
    );
  }
}
