import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/loans_api.dart';
import '../../core/models/tvr.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'tvr_viewmodel.dart';

class TvrScreen extends StatelessWidget {
  const TvrScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => TvrViewModel(context.read<LoansApi>()),
      child: const _TvrView(),
    );
  }
}

class _TvrView extends StatelessWidget {
  const _TvrView();

  Future<void> _openTvrSheet(BuildContext context, TvrPendingItem item) async {
    final vm = context.read<TvrViewModel>();
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => ChangeNotifierProvider.value(
        value: vm,
        child: _TvrSheet(item: item),
      ),
    );
    if (result == true && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('TVR recorded')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<TvrViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('TVR')),
      body: RefreshIndicator(
        onRefresh: vm.load,
        child: _buildBody(context, vm),
      ),
    );
  }

  Widget _buildBody(BuildContext context, TvrViewModel vm) {
    if (vm.loading && vm.items.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.items.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 80),
          Center(child: Text(vm.error!)),
          const SizedBox(height: 12),
          Center(child: OutlinedButton(onPressed: vm.load, child: const Text('Retry'))),
        ],
      );
    }
    if (vm.items.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          Padding(
            padding: EdgeInsets.only(top: 80),
            child: Center(child: Text('No loans pending TVR.', textAlign: TextAlign.center)),
          ),
        ],
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: vm.items.length,
      itemBuilder: (context, index) {
        final item = vm.items[index];
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.surface(context),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border(context)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(child: Text(item.memberName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5))),
                  Text(money(item.loanAmount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF2FBF87))),
                ],
              ),
              const SizedBox(height: 2),
              Text(
                '${item.loanNo} · ${item.centerName ?? '-'}',
                style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
              ),
              if (item.stage != null) ...[
                const SizedBox(height: 2),
                Text('Stage: ${item.stage}', style: TextStyle(fontSize: 12, color: AppColors.textFaint(context))),
              ],
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  icon: const Icon(Icons.phone_in_talk_rounded, size: 18),
                  label: const Text('Conduct TVR'),
                  onPressed: () => _openTvrSheet(context, item),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _TvrSheet extends StatefulWidget {
  final TvrPendingItem item;

  const _TvrSheet({required this.item});

  @override
  State<_TvrSheet> createState() => _TvrSheetState();
}

class _TvrSheetState extends State<_TvrSheet> {
  final _formKey = GlobalKey<FormState>();
  final _contactPersonController = TextEditingController();
  final _notesController = TextEditingController();
  DateTime _tvrDate = DateTime.now();
  int? _statusId;

  @override
  void dispose() {
    _contactPersonController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _tvrDate,
      firstDate: DateTime.now().subtract(const Duration(days: 30)),
      lastDate: DateTime.now(),
    );
    if (picked != null) setState(() => _tvrDate = picked);
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate() || _statusId == null) return;
    final vm = context.read<TvrViewModel>();
    final ok = await vm.submit(
      loanId: widget.item.loanId,
      contactPersonName: _contactPersonController.text.trim(),
      tvrDate: _tvrDate,
      tvrStatusId: _statusId!,
      verificationNotes: _notesController.text.trim().isEmpty ? null : _notesController.text.trim(),
    );
    if (!mounted) return;
    if (ok) Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<TvrViewModel>();
    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Conduct TVR', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 4),
            Text('${widget.item.memberName} · ${widget.item.loanNo}'),
            const SizedBox(height: 20),
            TextFormField(
              controller: _contactPersonController,
              decoration: const InputDecoration(labelText: 'Contact Person Name', border: OutlineInputBorder()),
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
            ),
            const SizedBox(height: 12),
            InkWell(
              onTap: _pickDate,
              child: InputDecorator(
                decoration: const InputDecoration(labelText: 'TVR Date', border: OutlineInputBorder()),
                child: Text('${_tvrDate.day}/${_tvrDate.month}/${_tvrDate.year}'),
              ),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              initialValue: _statusId,
              decoration: const InputDecoration(labelText: 'Status', border: OutlineInputBorder()),
              items: vm.statuses.map((s) => DropdownMenuItem(value: s.id, child: Text(s.name))).toList(),
              onChanged: (v) => setState(() => _statusId = v),
              validator: (v) => v == null ? 'Required' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _notesController,
              decoration: const InputDecoration(labelText: 'Verification Notes (optional)', border: OutlineInputBorder()),
              maxLines: 3,
            ),
            if (vm.submitError != null) ...[
              const SizedBox(height: 12),
              Text(vm.submitError!, style: const TextStyle(color: Colors.red)),
            ],
            const SizedBox(height: 20),
            FilledButton(
              onPressed: vm.submitting ? null : _submit,
              child: vm.submitting
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Save'),
            ),
          ],
        ),
      ),
    );
  }
}
