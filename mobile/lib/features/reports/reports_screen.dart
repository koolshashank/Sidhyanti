import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/reports_api.dart';
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
                            gradient: const [Color(0xFF6A4FC7), Color(0xFF3A3D9F)],
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
                    ],
                  ),
                ),
    );
  }
}
