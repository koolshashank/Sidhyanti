import 'package:flutter/material.dart';

import '../../../core/utils/formatters.dart';
import '../../../l10n/generated/app_localizations.dart';
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
    final l10n = AppLocalizations.of(context)!;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.changeSchemeConfirmTitle),
        content: Text(l10n.changeSchemeConfirmBody),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(l10n.commonCancel)),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(l10n.changeSchemeConfirm)),
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
    final l10n = AppLocalizations.of(context)!;
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
          Text(l10n.loanDetailChangeScheme, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text(l10n.changeSchemeSubtitle, style: const TextStyle(color: Colors.grey)),
          const SizedBox(height: 20),
          if (vm.schemeOptions.isEmpty)
            const Center(child: Padding(padding: EdgeInsets.symmetric(vertical: 12), child: CircularProgressIndicator()))
          else
            DropdownButtonFormField<int>(
              initialValue: _selectedSchemeId,
              decoration: InputDecoration(labelText: l10n.changeSchemeNewSchemeLabel, border: const OutlineInputBorder()),
              items: vm.schemeOptions
                  .map((s) => DropdownMenuItem(
                        value: s.id,
                        child: Text(l10n.changeSchemeOptionLabel(s.name, money(s.defaultAmount), s.tenureMonths)),
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
                : Text(l10n.loanDetailChangeScheme),
          ),
        ],
      ),
    );
  }
}
