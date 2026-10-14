import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../shell/account_menu_button.dart';
import '../dashboard/widgets/dashboard_card.dart';

/// Hub of role-scoped operational reports -- each tile's underlying API
/// call is scoped server-side: Field Officer sees only their own loans,
/// Branch Manager their own branch, Area Manager every branch mapped to
/// them, Admin everything.
class OperationalReportsHubScreen extends StatelessWidget {
  const OperationalReportsHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Reports'), actions: const [AccountMenuButton(), SizedBox(width: 8)]),
      body: GridView.count(
        padding: const EdgeInsets.all(16),
        crossAxisCount: 2,
        mainAxisSpacing: 16,
        crossAxisSpacing: 16,
        childAspectRatio: 0.98,
        children: [
          DashboardCard(
            icon: Icons.location_city_rounded,
            gradient: const [Color(0xFF14A9AB), Color(0xFF0F8B8D)],
            title: 'Center-wise Collection',
            subtitle: 'Due vs collected per center',
            onTap: () => context.push('/reports/operational/center-collection'),
          ),
          DashboardCard(
            icon: Icons.pending_actions_rounded,
            gradient: const [Color(0xFFFF9F4A), Color(0xFFFF6F4A)],
            title: 'Today Pending Collection',
            subtitle: 'Not yet collected today',
            onTap: () => context.push('/reports/operational/today-pending'),
          ),
          DashboardCard(
            icon: Icons.account_balance_wallet_rounded,
            gradient: const [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
            title: 'Today Disbursed',
            subtitle: 'Loans disbursed today',
            onTap: () => context.push('/reports/operational/today-disbursed'),
          ),
          DashboardCard(
            icon: Icons.hourglass_bottom_rounded,
            gradient: const [Color(0xFFC8952C), Color(0xFFA9781E)],
            title: 'Due Disbursement',
            subtitle: 'Approved, awaiting disbursal',
            onTap: () => context.push('/reports/operational/due-disbursement'),
          ),
          DashboardCard(
            icon: Icons.warning_amber_rounded,
            gradient: const [Color(0xFFE57373), Color(0xFFD32F2F)],
            title: 'Arrears Clients',
            subtitle: 'Broken installments & amount',
            onTap: () => context.push('/reports/operational/arrears-clients'),
          ),
          DashboardCard(
            icon: Icons.report_problem_rounded,
            gradient: const [Color(0xFFE65100), Color(0xFFBF360C)],
            title: 'Default Loans',
            subtitle: 'Tenure ended, still outstanding',
            onTap: () => context.push('/reports/operational/defaults'),
          ),
        ],
      ),
    );
  }
}
