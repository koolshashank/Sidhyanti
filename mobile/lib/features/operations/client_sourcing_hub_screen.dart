import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../dashboard/widgets/dashboard_card.dart';

/// Landing screen for the "Client Sourcing" menu item: lets the field
/// officer choose between starting a brand-new lead and resuming one of
/// their already-started ones, instead of always dropping straight into the
/// center list.
class ClientSourcingHubScreen extends StatefulWidget {
  const ClientSourcingHubScreen({super.key});

  @override
  State<ClientSourcingHubScreen> createState() => _ClientSourcingHubScreenState();
}

class _ClientSourcingHubScreenState extends State<ClientSourcingHubScreen> {
  int? _pendingCount;

  @override
  void initState() {
    super.initState();
    _loadPendingCount();
  }

  Future<void> _loadPendingCount() async {
    try {
      final journeys = await context.read<MembersApi>().pendingJourneys();
      if (mounted) setState(() => _pendingCount = journeys.length);
    } catch (_) {
      // Leave the count blank -- the Pending Journey screen itself will
      // surface any error when the user opens it.
    }
  }

  @override
  Widget build(BuildContext context) {
    final pendingSubtitle = _pendingCount == null
        ? 'Resume a started lead'
        : _pendingCount == 0
            ? 'Nothing pending right now'
            : '$_pendingCount client${_pendingCount == 1 ? '' : 's'} waiting';

    return Scaffold(
      appBar: AppBar(title: const Text('Client Sourcing')),
      body: GridView.count(
        padding: const EdgeInsets.all(16),
        crossAxisCount: 2,
        mainAxisSpacing: 14,
        crossAxisSpacing: 14,
        childAspectRatio: 0.95,
        children: [
          DashboardCard(
            icon: Icons.person_add_alt_1_rounded,
            gradient: const [Color(0xFFFF9F4A), Color(0xFFFF6F4A)],
            title: 'New Journey',
            subtitle: 'Start a fresh lead',
            onTap: () => context.push('/client-sourcing/centers'),
          ),
          DashboardCard(
            icon: Icons.history_rounded,
            gradient: const [Color(0xFF6A4FC7), Color(0xFF3A3D9F)],
            title: 'Pending Journey',
            subtitle: pendingSubtitle,
            onTap: () => context.push('/client-sourcing/pending'),
          ),
          DashboardCard(
            icon: Icons.repeat_rounded,
            gradient: const [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
            title: 'Existing Client Journey',
            subtitle: 'New proposal for a past client',
            onTap: () => context.push('/client-sourcing/existing-centers'),
          ),
        ],
      ),
    );
  }
}
