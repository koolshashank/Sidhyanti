import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/utils/formatters.dart';
import 'loan_detail_viewmodel.dart';
import 'widgets/change_scheme_sheet.dart';
import 'widgets/overdue_banner.dart';
import 'widgets/repayment_schedule_table.dart';

class LoanDetailScreen extends StatelessWidget {
  final int loanId;

  const LoanDetailScreen({super.key, required this.loanId});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => LoanDetailViewModel(context.read<LoansApi>(), loanId),
      child: const _LoanDetailView(),
    );
  }
}

class _LoanDetailView extends StatelessWidget {
  const _LoanDetailView();

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<LoanDetailViewModel>();

    return Scaffold(
      appBar: AppBar(title: Text(vm.loan?.loanNo ?? 'Loan')),
      body: _buildBody(context, vm),
    );
  }

  Future<void> _openChangeScheme(BuildContext context) async {
    final vm = context.read<LoanDetailViewModel>();
    await vm.loadSchemeOptions();
    if (!context.mounted) return;
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => ChangeToSchemeSheet(viewModel: vm, currentSchemeId: vm.loan?.schemeId),
    );
    if (result == true && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Scheme changed successfully')));
    }
  }

  Future<void> _requestPreclose(BuildContext context, int loanId) async {
    final controller = TextEditingController();
    final remarks = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Request Preclose'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(labelText: 'Remarks (optional)', border: OutlineInputBorder()),
          maxLines: 2,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()),
            child: const Text('Submit'),
          ),
        ],
      ),
    );
    if (remarks == null || !context.mounted) return;
    try {
      await context.read<LoansApi>().requestPreclose(loanId: loanId, remarks: remarks.isEmpty ? null : remarks);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Preclose request submitted for Branch Manager approval')));
        context.read<LoanDetailViewModel>().load();
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Widget _buildBody(BuildContext context, LoanDetailViewModel vm) {
    if (vm.loading && vm.loan == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.loan == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(vm.error!),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: vm.load, child: const Text('Retry')),
          ],
        ),
      );
    }
    final loan = vm.loan!;
    return RefreshIndicator(
      onRefresh: vm.load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          OverdueBanner(overdue: loan.overdue),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(loan.member.fullName, style: Theme.of(context).textTheme.titleMedium),
                  Text(loan.member.enrollmentNo ?? '', style: const TextStyle(color: Colors.grey)),
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _Stat(label: 'Loan Amount', value: money(loan.loanAmount)),
                      _Stat(label: 'Tenure', value: '${loan.tenureMonths} mo'),
                      _Stat(label: 'Interest', value: '${loan.interestRatePercent}%'),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _Stat(label: 'Installments Paid', value: '${loan.paidCount}/${loan.totalInstallments}'),
                      if (loan.stage != null) Chip(label: Text(loan.stage!)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text('Branch: ${loan.branchName ?? '-'}  ·  Center: ${loan.centerName ?? '-'}'),
                  Text('Officer: ${loan.officerName ?? '-'}'),
                  Text('Disbursed: ${loan.disbursementDate != null ? fdate(loan.disbursementDate) : 'Not yet'}'),
                  if (loan.canChangeScheme) ...[
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        icon: const Icon(Icons.swap_horiz_rounded, size: 18),
                        label: const Text('Change Scheme'),
                        onPressed: () => _openChangeScheme(context),
                      ),
                    ),
                  ],
                  if (loan.canConductGrt) ...[
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        icon: const Icon(Icons.groups_rounded, size: 18),
                        label: const Text('Conduct GRT'),
                        onPressed: () async {
                          final appDateIso = loan.applicationDate != null
                              ? '${loan.applicationDate!.year.toString().padLeft(4, '0')}-${loan.applicationDate!.month.toString().padLeft(2, '0')}-${loan.applicationDate!.day.toString().padLeft(2, '0')}'
                              : '';
                          final result = await context.push<bool>(
                            '/loans/${loan.loanId}/grt?loan_no=${Uri.encodeComponent(loan.loanNo)}&application_date=$appDateIso',
                          );
                          if (result == true && context.mounted) {
                            context.read<LoanDetailViewModel>().load();
                          }
                        },
                      ),
                    ),
                  ],
                  if (loan.canRequestPreclose) ...[
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        icon: const Icon(Icons.event_busy_rounded, size: 18),
                        label: const Text('Request Preclose'),
                        onPressed: () => _requestPreclose(context, loan.loanId),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text('Repayment Schedule', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: RepaymentScheduleTable(schedule: loan.repaymentSchedule),
            ),
          ),
        ],
      ),
    );
  }
}

class _Stat extends StatelessWidget {
  final String label;
  final String value;

  const _Stat({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        Text(label, style: const TextStyle(color: Colors.grey, fontSize: 12)),
      ],
    );
  }
}
