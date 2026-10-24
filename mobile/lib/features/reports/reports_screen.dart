import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/reports_api.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../shell/account_menu_button.dart';
import '../dashboard/widgets/dashboard_card.dart';
import '../dashboard/widgets/section_heading.dart';
import 'reports_viewmodel.dart';

class ReportsScreen extends StatelessWidget {
  const ReportsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => ReportsViewModel(context.read<ReportsApi>()),
      child: const _ReportsView(),
    );
  }
}

class _ReportsView extends StatelessWidget {
  const _ReportsView();

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<ReportsViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('My Performance'), actions: const [AccountMenuButton(), SizedBox(width: 8)]),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(vm.error!),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: vm.load, child: const Text('Retry')),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: vm.load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      const SectionHeading('Target vs Achievement'),
                      const SizedBox(height: 14),
                      _TargetProgressCard(
                        icon: Icons.request_quote_rounded,
                        color: const Color(0xFFE65100),
                        title: 'Disbursement',
                        achieved: vm.performance?.amountDisbursedThisMonth ?? 0,
                        target: vm.performance?.disbursementTarget ?? 0,
                        noTargetLabel: 'No disbursement target set for this month',
                      ),
                      const SizedBox(height: 12),
                      _TargetProgressCard(
                        icon: Icons.payments_rounded,
                        color: const Color(0xFF2FBF87),
                        title: 'Collection',
                        achieved: vm.performance?.collectedThisMonth ?? 0,
                        target: vm.performance?.collectionDueThisMonth ?? 0,
                        noTargetLabel: 'No collection due this month',
                      ),
                      const SizedBox(height: 24),
                      const SectionHeading('This Month'),
                      const SizedBox(height: 14),
                      GridView.count(
                        crossAxisCount: 2,
                        mainAxisSpacing: 16,
                        crossAxisSpacing: 16,
                        childAspectRatio: 0.98,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        children: [
                          DashboardCard(
                            icon: Icons.person_add_alt_1_rounded,
                            gradient: const [Color(0xFFFF9F4A), Color(0xFFFF6F4A)],
                            title: 'Leads Sourced',
                            value: '${vm.performance?.leadsSourcedThisMonth ?? 0}',
                          ),
                          DashboardCard(
                            icon: Icons.request_quote_rounded,
                            gradient: const [Color(0xFFE65100), Color(0xFFBF360C)],
                            title: 'Loans Disbursed',
                            value: '${vm.performance?.loansDisbursedThisMonth ?? 0}',
                            subtitle: vm.performance != null ? money(vm.performance!.amountDisbursedThisMonth) : null,
                          ),
                          DashboardCard(
                            icon: Icons.payments_rounded,
                            gradient: const [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
                            title: 'Collected',
                            value: vm.performance != null ? money(vm.performance!.collectedThisMonth) : '-',
                          ),
                          DashboardCard(
                            icon: Icons.warning_amber_rounded,
                            gradient: [Colors.red.shade300, Colors.red.shade600],
                            title: 'Arrear Loans',
                            value: '${vm.performance?.arrearLoanCount ?? 0}',
                            subtitle: vm.performance != null ? money(vm.performance!.arrearAmount) : null,
                          ),
                        ],
                      ),
                      const SizedBox(height: 24),
                      const SectionHeading('Portfolio (Till Date)'),
                      const SizedBox(height: 14),
                      GridView.count(
                        crossAxisCount: 2,
                        mainAxisSpacing: 16,
                        crossAxisSpacing: 16,
                        childAspectRatio: 0.98,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        children: [
                          DashboardCard(
                            icon: Icons.groups_rounded,
                            gradient: const [Color(0xFF4A9FFF), Color(0xFF3A6FD9)],
                            title: 'ALC',
                            value: '${vm.performance?.activeLoanClients ?? 0}',
                            subtitle: 'Active Loan Clients',
                          ),
                          DashboardCard(
                            icon: Icons.account_balance_rounded,
                            gradient: const [Color(0xFF3A3D9F), Color(0xFF20223B)],
                            title: 'POS Till Date',
                            value: vm.performance != null ? money(vm.performance!.posTillDate) : '-',
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
    );
  }
}

class _TargetProgressCard extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final double achieved;
  final double target;
  final String noTargetLabel;

  const _TargetProgressCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.achieved,
    required this.target,
    required this.noTargetLabel,
  });

  @override
  Widget build(BuildContext context) {
    final hasTarget = target > 0;
    final percent = hasTarget ? (achieved / target).clamp(0.0, 1.0) : 0.0;

    return Container(
      padding: const EdgeInsets.all(16),
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
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
                child: Icon(icon, color: color, size: 18),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(title, style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textPrimary(context))),
              ),
              if (hasTarget)
                Text(
                  '${(percent * 100).toStringAsFixed(0)}%',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: color),
                ),
            ],
          ),
          const SizedBox(height: 12),
          if (!hasTarget)
            Text(noTargetLabel, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)))
          else ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: percent,
                minHeight: 8,
                backgroundColor: AppColors.surfaceAlt(context),
                valueColor: AlwaysStoppedAnimation(color),
              ),
            ),
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Achieved: ${money(achieved)}', style: TextStyle(fontSize: 12, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
                Text('Target: ${money(target)}', style: TextStyle(fontSize: 12, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
