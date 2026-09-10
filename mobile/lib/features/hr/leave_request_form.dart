import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/hr_api.dart';
import '../../core/utils/formatters.dart';
import '../operations/widgets/form_styles.dart';
import 'leave_request_viewmodel.dart';

/// Shows the new-leave-request bottom sheet and returns true on success.
Future<bool?> showLeaveRequestForm(BuildContext context) {
  return showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    builder: (context) => ChangeNotifierProvider(
      create: (context) => LeaveRequestViewModel(context.read<HrApi>()),
      child: const _LeaveRequestForm(),
    ),
  );
}

class _LeaveRequestForm extends StatefulWidget {
  const _LeaveRequestForm();

  @override
  State<_LeaveRequestForm> createState() => _LeaveRequestFormState();
}

class _LeaveRequestFormState extends State<_LeaveRequestForm> {
  final _formKey = GlobalKey<FormState>();
  final _reasonController = TextEditingController();
  String? _leaveType;
  DateTime? _fromDate;
  DateTime? _toDate;

  static const _leaveTypes = ['Casual', 'Sick', 'Earned', 'Unpaid'];

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _pickFromDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now().subtract(const Duration(days: 7)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) {
      setState(() {
        _fromDate = picked;
        if (_toDate != null && _toDate!.isBefore(picked)) _toDate = picked;
      });
    }
  }

  Future<void> _pickToDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _fromDate ?? DateTime.now(),
      firstDate: _fromDate ?? DateTime.now().subtract(const Duration(days: 7)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) setState(() => _toDate = picked);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _fromDate == null || _toDate == null) {
      if (_fromDate == null || _toDate == null) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please select both dates')));
      }
      return;
    }
    final vm = context.read<LeaveRequestViewModel>();
    final ok = await vm.submit(
      leaveType: _leaveType!,
      fromDate: _fromDate!,
      toDate: _toDate!,
      reason: _reasonController.text.trim(),
    );
    if (ok && mounted) Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<LeaveRequestViewModel>();

    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text('New Leave Request', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _leaveType,
              decoration: appFieldDecoration('Leave Type *'),
              items: _leaveTypes.map((t) => DropdownMenuItem(value: t, child: Text(t))).toList(),
              onChanged: (v) => setState(() => _leaveType = v),
              validator: (v) => v == null ? 'Required' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              readOnly: true,
              onTap: _pickFromDate,
              decoration: appFieldDecoration('From Date *'),
              controller: TextEditingController(text: _fromDate != null ? fdate(_fromDate) : ''),
            ),
            const SizedBox(height: 12),
            TextFormField(
              readOnly: true,
              onTap: _pickToDate,
              decoration: appFieldDecoration('To Date *'),
              controller: TextEditingController(text: _toDate != null ? fdate(_toDate) : ''),
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
            SubmitButton(loading: vm.submitting, label: 'SUBMIT REQUEST', onPressed: _submit),
          ],
        ),
      ),
    );
  }
}
