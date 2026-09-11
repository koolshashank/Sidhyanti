import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/centers_api.dart';
import '../../core/models/center_transfer.dart';
import '../../core/theme/app_colors.dart';
import 'center_transfer_viewmodel.dart';

class CenterTransferScreen extends StatelessWidget {
  const CenterTransferScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => CenterTransferViewModel(context.read<CentersApi>()),
      child: const _CenterTransferView(),
    );
  }
}

class _CenterTransferView extends StatelessWidget {
  const _CenterTransferView();

  Future<void> _openTransferSheet(BuildContext context, CenterForTransfer center) async {
    final vm = context.read<CenterTransferViewModel>();
    final otherStaff = vm.staff.where((s) => s.staffId != center.managedById).toList();

    if (otherStaff.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No other active employee in this branch to transfer to.')),
      );
      return;
    }

    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => ChangeNotifierProvider.value(
        value: vm,
        child: _TransferSheet(center: center, otherStaff: otherStaff),
      ),
    );

    if (result == true && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Center transferred')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<CenterTransferViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('Center Transfer')),
      body: RefreshIndicator(
        onRefresh: vm.load,
        child: _buildBody(context, vm),
      ),
    );
  }

  Widget _buildBody(BuildContext context, CenterTransferViewModel vm) {
    if (vm.loading && vm.centers.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.centers.isEmpty) {
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
    if (vm.centers.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          Padding(
            padding: EdgeInsets.only(top: 80),
            child: Center(child: Text('No centers found in your branch.', textAlign: TextAlign.center)),
          ),
        ],
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: vm.centers.length,
      itemBuilder: (context, index) {
        final center = vm.centers[index];
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.surface(context),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border(context)),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(center.centerName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5), overflow: TextOverflow.ellipsis),
                    if (center.centerCode != null) ...[
                      const SizedBox(height: 2),
                      Text('ID: ${center.centerCode}', style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context))),
                    ],
                    const SizedBox(height: 4),
                    Text(
                      'Managed by: ${center.managedByName ?? 'Unassigned'}',
                      style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              OutlinedButton.icon(
                icon: const Icon(Icons.swap_horiz_rounded, size: 18),
                label: const Text('Transfer'),
                onPressed: () => _openTransferSheet(context, center),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _TransferSheet extends StatefulWidget {
  final CenterForTransfer center;
  final List<BranchStaffOption> otherStaff;

  const _TransferSheet({required this.center, required this.otherStaff});

  @override
  State<_TransferSheet> createState() => _TransferSheetState();
}

class _TransferSheetState extends State<_TransferSheet> {
  int? _newStaffId;
  final _remarkController = TextEditingController();

  @override
  void dispose() {
    _remarkController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_newStaffId == null) return;
    final vm = context.read<CenterTransferViewModel>();
    final ok = await vm.transfer(
      centerId: widget.center.centerId,
      newStaffId: _newStaffId!,
      remark: _remarkController.text.trim().isEmpty ? null : _remarkController.text.trim(),
    );
    if (!mounted) return;
    if (ok) {
      Navigator.of(context).pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<CenterTransferViewModel>();
    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Transfer ${widget.center.centerName}', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 4),
          Text('Currently: ${widget.center.managedByName ?? 'Unassigned'}', style: const TextStyle(color: Colors.grey)),
          const SizedBox(height: 20),
          DropdownButtonFormField<int>(
            initialValue: _newStaffId,
            decoration: const InputDecoration(labelText: 'New Employee', border: OutlineInputBorder()),
            items: widget.otherStaff.map((s) => DropdownMenuItem(value: s.staffId, child: Text(s.fullName))).toList(),
            onChanged: (v) => setState(() => _newStaffId = v),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _remarkController,
            decoration: const InputDecoration(labelText: 'Remark (optional)', border: OutlineInputBorder()),
            maxLines: 2,
          ),
          if (vm.submitError != null) ...[
            const SizedBox(height: 12),
            Text(vm.submitError!, style: const TextStyle(color: Colors.red)),
          ],
          const SizedBox(height: 20),
          FilledButton(
            onPressed: (_newStaffId == null || vm.submitting) ? null : _submit,
            child: vm.submitting
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Transfer'),
          ),
        ],
      ),
    );
  }
}
