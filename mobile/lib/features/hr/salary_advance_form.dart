import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/hr_api.dart';
import '../operations/widgets/form_styles.dart';
import 'salary_advance_viewmodel.dart';

const _personalAmounts = [1000.0, 2000.0, 3000.0, 4000.0, 5000.0];
const _emergencyAmounts = [6000.0, 7000.0, 8000.0, 9000.0, 10000.0];

/// Shows the new-salary-advance-request bottom sheet and returns true on
/// success. Personal use is only allowed after the 15th of the month
/// (server also enforces this); Emergency use has no date restriction --
/// see api/v1/hr/salary_advance_request_create.php.
Future<bool?> showSalaryAdvanceForm(BuildContext context) {
  return showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    builder: (context) => ChangeNotifierProvider(
      create: (context) => SalaryAdvanceViewModel(context.read<HrApi>()),
      child: const _SalaryAdvanceForm(),
    ),
  );
}

class _SalaryAdvanceForm extends StatefulWidget {
  const _SalaryAdvanceForm();

  @override
  State<_SalaryAdvanceForm> createState() => _SalaryAdvanceFormState();
}

class _SalaryAdvanceFormState extends State<_SalaryAdvanceForm> {
  final _formKey = GlobalKey<FormState>();
  final _reasonController = TextEditingController();
  String _requestType = 'Personal';
  double? _amount;

  bool get _personalAllowedToday => DateTime.now().day > 15;

  List<double> get _amountOptions => _requestType == 'Personal' ? _personalAmounts : _emergencyAmounts;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _amount == null) return;
    final vm = context.read<SalaryAdvanceViewModel>();
    final ok = await vm.submit(
      requestType: _requestType,
      amount: _amount!,
      reason: _reasonController.text.trim(),
    );
    if (ok && mounted) Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<SalaryAdvanceViewModel>();

    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('New Salary Advance Request', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 16),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'Personal', label: Text('Personal')),
                ButtonSegment(value: 'Emergency', label: Text('Emergency')),
              ],
              selected: {_requestType},
              onSelectionChanged: (s) => setState(() {
                _requestType = s.first;
                _amount = null;
              }),
            ),
            if (_requestType == 'Personal' && !_personalAllowedToday) ...[
              const SizedBox(height: 10),
              Text(
                'Personal advance can only be requested after the 15th of the month.',
                style: TextStyle(fontSize: 12.5, color: Colors.red.shade400),
              ),
            ],
            const SizedBox(height: 12),
            DropdownButtonFormField<double>(
              initialValue: _amount,
              decoration: appFieldDecoration('Amount *'),
              items: _amountOptions.map((a) => DropdownMenuItem(value: a, child: Text('₹${a.toStringAsFixed(0)}'))).toList(),
              onChanged: _requestType == 'Personal' && !_personalAllowedToday ? null : (v) => setState(() => _amount = v),
              validator: (v) => v == null ? 'Required' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _reasonController,
              decoration: appFieldDecoration('Reason (optional)'),
              maxLines: 2,
            ),
            if (vm.error != null) ...[
              const SizedBox(height: 12),
              Text(vm.error!, style: const TextStyle(color: Colors.red)),
            ],
            const SizedBox(height: 20),
            SubmitButton(
              loading: vm.submitting,
              label: 'SUBMIT REQUEST',
              onPressed: (_requestType == 'Personal' && !_personalAllowedToday) ? null : _submit,
            ),
          ],
        ),
      ),
    );
  }
}
