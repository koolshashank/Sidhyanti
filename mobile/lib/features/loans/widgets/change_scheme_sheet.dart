import 'package:flutter/material.dart';

import '../../../core/utils/formatters.dart';
import '../loan_detail_viewmodel.dart';

/// Bottom sheet for picking a new scheme and submitting the change. Takes
/// the screen's already-loaded [LoanDetailViewModel] directly (rather than
/// via Provider lookup) since showModalBottomSheet's route sits outside the
/// screen's local provider scope.
class ChangeToSchemeSheet extends StatefulWidget {
  final LoanDetailViewModel viewModel;
  final int? currentSchemeId;

  const ChangeToSchemeSheet({super.key, required this.viewModel, required this.currentSchemeId});

  @override
  State<ChangeToSchemeSheet> createState() => _ChangeToSchemeSheetState();
}

class _ChangeToSchemeSheetState extends State<ChangeToSchemeSheet> {
  int? _selectedSchemeId;

  @override
  void initState() {
    super.initState();
    widget.viewModel.addListener(_onChanged);
    _selectedSchemeId = widget.currentSchemeId;
  }

  @override
  void dispose() {
    widget.viewModel.removeListener(_onChanged);
    super.dispose();
  }

  void _onChanged() => setState(() {});

  Future<void> _submit() async {
    if (_selectedSchemeId == null) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Change Scheme?'),
        content: const Text('This will update the loan\'s amount, tenure and interest rate to match the new scheme.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Confirm')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    final ok = await widget.viewModel.changeScheme(_selectedSchemeId!);
    if (ok && mounted) {
      Navigator.of(context).pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = widget.viewModel;
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Change Scheme', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          const Text('Amount and tenure will update to match the selected scheme.', style: TextStyle(color: Colors.grey)),
          const SizedBox(height: 20),
          if (vm.schemeOptions.isEmpty)
            const Center(child: Padding(padding: EdgeInsets.symmetric(vertical: 12), child: CircularProgressIndicator()))
          else
            DropdownButtonFormField<int>(
              initialValue: _selectedSchemeId,
              decoration: const InputDecoration(labelText: 'New Scheme', border: OutlineInputBorder()),
              items: vm.schemeOptions
                  .map((s) => DropdownMenuItem(
                        value: s.id,
                        child: Text('${s.name} — ${money(s.defaultAmount)} / ${s.tenureMonths} mo'),
                      ))
                  .toList(),
              onChanged: (v) => setState(() => _selectedSchemeId = v),
            ),
          if (vm.changeSchemeError != null) ...[
            const SizedBox(height: 12),
            Text(vm.changeSchemeError!, style: const TextStyle(color: Colors.red)),
          ],
          const SizedBox(height: 20),
          FilledButton(
            onPressed: (vm.changingScheme || _selectedSchemeId == null) ? null : _submit,
            child: vm.changingScheme
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Change Scheme'),
          ),
        ],
      ),
    );
  }
}
