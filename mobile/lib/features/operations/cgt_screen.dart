import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/centers_api.dart';
import '../../core/theme/app_colors.dart';
import 'cgt_viewmodel.dart';
import 'widgets/form_section_card.dart';
import 'widgets/form_styles.dart';

class CgtScreen extends StatelessWidget {
  final int centerId;
  final String centerName;

  const CgtScreen({super.key, required this.centerId, required this.centerName});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => CgtViewModel(context.read<CentersApi>()),
      child: _CgtView(centerId: centerId, centerName: centerName),
    );
  }
}

class _CgtView extends StatefulWidget {
  final int centerId;
  final String centerName;

  const _CgtView({required this.centerId, required this.centerName});

  @override
  State<_CgtView> createState() => _CgtViewState();
}

class _CgtViewState extends State<_CgtView> {
  final _formKey = GlobalKey<FormState>();
  final _dateController = TextEditingController();
  final _remarks = TextEditingController();
  DateTime _cgtDate = DateTime.now();

  @override
  void initState() {
    super.initState();
    _dateController.text = '${_cgtDate.day}/${_cgtDate.month}/${_cgtDate.year}';
  }

  @override
  void dispose() {
    _dateController.dispose();
    _remarks.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _cgtDate,
      firstDate: DateTime.now().subtract(const Duration(days: 30)),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      setState(() {
        _cgtDate = picked;
        _dateController.text = '${picked.day}/${picked.month}/${picked.year}';
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final vm = context.read<CgtViewModel>();
    final ok = await vm.submit(centerId: widget.centerId, cgtDate: _cgtDate, remarks: _remarks.text.trim());
    if (ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('CGT recorded')));
      // Natural next step in the sourcing flow: start adding clients for this center.
      context.pushReplacement(
        '/client-sourcing/new-lead?center_id=${widget.centerId}&center_name=${Uri.encodeComponent(widget.centerName)}',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<CgtViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('CGT')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(color: AppColors.accentTint(context), borderRadius: BorderRadius.circular(12)),
              child: Row(
                children: [
                  const Icon(Icons.storefront_rounded, size: 18, color: Color(0xFF6A4FC7)),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      widget.centerName,
                      style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF3A3D9F)),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ),
            FormSectionCard(
              icon: Icons.groups_2_rounded,
              title: 'Center Group Training',
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(color: AppColors.warningTint(context), borderRadius: BorderRadius.circular(12)),
                  child: Row(
                    children: [
                      Icon(Icons.info_outline_rounded, size: 16, color: Colors.orange.shade700),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Photo capture for CGT isn\'t available in the app yet -- only the date and remarks are recorded.',
                          style: TextStyle(fontSize: 12, color: Colors.orange.shade900),
                        ),
                      ),
                    ],
                  ),
                ),
                TextFormField(
                  controller: _dateController,
                  readOnly: true,
                  onTap: _pickDate,
                  decoration: appFieldDecoration('CGT Date *'),
                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _remarks,
                  decoration: appFieldDecoration('Remarks (Optional)'),
                  maxLines: 3,
                ),
              ],
            ),
            if (vm.submitError != null) ...[
              Text(vm.submitError!, style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 10),
            ],
            SubmitButton(loading: vm.submitting, label: 'Complete CGT', onPressed: _submit),
          ],
        ),
      ),
    );
  }
}
