import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/loan.dart';
import '../../core/utils/formatters.dart';
import '../../l10n/generated/app_localizations.dart';
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
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      appBar: AppBar(title: Text(vm.loan?.loanNo ?? l10n.loanDetailTitleFallback)),
      body: _buildBody(context, vm),
    );
  }

  Future<void> _openChangeScheme(BuildContext context) async {
    final vm = context.read<LoanDetailViewModel>();
    final l10n = AppLocalizations.of(context)!;
    await vm.loadSchemeOptions();
    if (!context.mounted) return;
    final result = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => ChangeToSchemeSheet(viewModel: vm, currentSchemeId: vm.loan?.schemeId),
    );
    if (result == true && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.loanDetailSchemeChanged)));
    }
  }

  Future<void> _requestPreclose(BuildContext context, LoanDetail loan) async {
    final l10n = AppLocalizations.of(context)!;
    final controller = TextEditingController();
    final quote = loan.precloseQuote;
    final remarks = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l10n.loanDetailRequestPrecloseTitle),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (quote != null) ...[
                _PrecloseStatRow(label: l10n.loanDetailInstallmentsPaid, value: '${quote.installmentsPaid} / ${quote.totalInstallments}'),
                _PrecloseStatRow(label: l10n.loanDetailPrincipalOutstanding, value: money(quote.principalOutstanding)),
                const Divider(height: 20),
                _PrecloseStatRow(label: l10n.loanDetailAmountToPayNow, value: money(quote.precloseAmount), bold: true),
                _PrecloseStatRow(
                  label: l10n.loanDetailYouSaveInterest,
                  value: money(quote.interestSavings),
                  bold: true,
                  color: const Color(0xFF2FBF87),
                ),
                const SizedBox(height: 16),
              ],
              TextField(
                controller: controller,
                decoration: InputDecoration(labelText: l10n.loanDetailRemarksOptional, border: const OutlineInputBorder()),
                maxLines: 2,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: Text(l10n.commonCancel)),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()),
            child: Text(l10n.commonSubmit),
          ),
        ],
      ),
    );
    if (remarks == null || !context.mounted) return;
    try {
      await context.read<LoansApi>().requestPreclose(loanId: loan.loanId, remarks: remarks.isEmpty ? null : remarks);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.loanDetailPrecloseSubmitted)));
        context.read<LoanDetailViewModel>().load();
      }
    } on ApiException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    }
  }

  Widget _buildBody(BuildContext context, LoanDetailViewModel vm) {
    final l10n = AppLocalizations.of(context)!;
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
            OutlinedButton(onPressed: vm.load, child: Text(l10n.commonRetry)),
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
                      _Stat(label: l10n.loanDetailLoanAmount, value: money(loan.loanAmount)),
                      _Stat(label: l10n.loanDetailTenure, value: l10n.loanDetailTenureMonths(loan.tenureMonths)),
                      _Stat(label: l10n.loanDetailInterest, value: l10n.loanDetailInterestPercent('${loan.interestRatePercent}')),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      _Stat(label: l10n.loanDetailInstallmentsPaid, value: '${loan.paidCount}/${loan.totalInstallments}'),
                      if (loan.stage != null) Chip(label: Text(loan.stage!)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(l10n.loanDetailBranchCenter(loan.branchName ?? '-', loan.centerName ?? '-')),
                  Text(l10n.loanDetailOfficer(loan.officerName ?? '-')),
                  if (loan.loanCycle != null) Text(l10n.loanDetailLoanCycle('${loan.loanCycle}')),
                  Text(l10n.loanDetailDisbursed(loan.disbursementDate != null ? fdate(loan.disbursementDate) : l10n.loanDetailNotYetDisbursed)),
                  if (loan.canChangeScheme) ...[
                    const SizedBox(height: 12),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton.icon(
                        icon: const Icon(Icons.swap_horiz_rounded, size: 18),
                        label: Text(l10n.loanDetailChangeScheme),
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
                        label: Text(l10n.loanDetailConductGrt),
                        onPressed: () async {
                          final appDateIso = loan.applicationDate != null
                              ? '${loan.applicationDate!.year.toString().padLeft(4, '0')}-${loan.applicationDate!.month.toString().padLeft(2, '0')}-${loan.applicationDate!.day.toString().padLeft(2, '0')}'
                              : '';
                          final result = await context.push<bool>(
                            '/loans/${loan.loanId}/grt?loan_no=${Uri.encodeComponent(loan.loanNo)}&application_date=$appDateIso&loan_amount=${loan.loanAmount}',
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
                        label: Text(l10n.loanDetailRequestPreclose),
                        onPressed: () => _requestPreclose(context, loan),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          Text(l10n.loanDetailRepaymentSchedule, style: Theme.of(context).textTheme.titleMedium),
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

class _PrecloseStatRow extends StatelessWidget {
  final String label;
  final String value;
  final bool bold;
  final Color? color;

  const _PrecloseStatRow({required this.label, required this.value, this.bold = false, this.color});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(
            child: Text(label, style: TextStyle(fontSize: 13, fontWeight: bold ? FontWeight.w700 : FontWeight.w500, color: color ?? Colors.grey.shade700)),
          ),
          Text(value, style: TextStyle(fontSize: 13, fontWeight: bold ? FontWeight.w800 : FontWeight.w600, color: color)),
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
