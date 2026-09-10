import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../dashboard/widgets/dashboard_card.dart';

/// Branch Manager's "Operations" hub -- reached from a single card on their
/// dashboard home (see dashboard_screen.dart's `staff?.role == 'Branch
/// Manager'` branch). Fans out to the approval workflows, disbursement,
/// collection, and the daily collection report.
class OperationsScreen extends StatelessWidget {
  const OperationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Operations')),
      body: GridView.count(
        padding: const EdgeInsets.all(16),
        crossAxisCount: 2,
        mainAxisSpacing: 16,
        crossAxisSpacing: 16,
        childAspectRatio: 0.98,
        children: [
          DashboardCard(
            icon: Icons.fact_check_rounded,
            gradient: const [Color(0xFF6A4FC7), Color(0xFF3A3D9F)],
            title: 'BM Approval',
            subtitle: 'Proposals, preclose, leave, payments',
            onTap: () => context.push('/bm-approval'),
          ),
          DashboardCard(
            icon: Icons.groups_rounded,
            gradient: const [Color(0xFF4A9FFF), Color(0xFF3A6FD9)],
            title: 'Client Details',
            subtitle: 'View your members',
            onTap: () => context.push('/members'),
          ),
          DashboardCard(
            icon: Icons.account_balance_wallet_rounded,
            gradient: const [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
            title: 'Disbursement',
            subtitle: 'Approved loans awaiting disbursal',
            onTap: () => context.push('/disbursement'),
          ),
          DashboardCard(
            icon: Icons.payments_rounded,
            gradient: const [Color(0xFFFF9F4A), Color(0xFFFF6F4A)],
            title: 'Collection',
            subtitle: 'Record field collections',
            onTap: () => context.push('/collections'),
          ),
          DashboardCard(
            icon: Icons.assignment_rounded,
            gradient: const [Color(0xFF14A9AB), Color(0xFF0F8B8D)],
            title: 'DCR',
            subtitle: 'Daily collection report',
            onTap: () => context.push('/dcr'),
          ),
          DashboardCard(
            icon: Icons.location_city_rounded,
            gradient: [Colors.red.shade300, Colors.red.shade600],
            title: 'Center Approval',
            subtitle: 'Review new centers',
            onTap: () => context.push('/center-approval'),
          ),
        ],
      ),
    );
  }
}
