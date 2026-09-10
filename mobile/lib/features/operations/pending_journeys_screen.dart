import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/models/member.dart';
import 'pending_journeys_viewmodel.dart';
import 'widgets/journey_resume_card.dart';

class PendingJourneysScreen extends StatelessWidget {
  const PendingJourneysScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => PendingJourneysViewModel(context.read<MembersApi>()),
      child: const _PendingJourneysView(),
    );
  }
}

class _PendingJourneysView extends StatelessWidget {
  const _PendingJourneysView();

  void _resumeJourney(BuildContext context, IncompleteJourney journey) {
    if (journey.resumeStage == 'coborrower') {
      context.push(
        '/client-sourcing/coborrower?member_id=${journey.memberId}&member_name=${Uri.encodeComponent(journey.memberName)}',
      );
    } else {
      context.push(
        '/client-sourcing/kyc'
        '?flow=member'
        '&member_id=${journey.memberId}'
        '&member_name=${Uri.encodeComponent(journey.memberName)}'
        '&coborrower_id=${journey.coBorrowerId}'
        '&coborrower_name=${Uri.encodeComponent(journey.coBorrowerName ?? '')}',
      );
    }
  }

  void _confirmDiscard(BuildContext context, int memberId) {
    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Discard this lead?'),
        content: const Text('This will remove the incomplete lead. This cannot be undone from the app.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          TextButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              context.read<PendingJourneysViewModel>().discard(memberId);
            },
            child: Text('Discard', style: TextStyle(color: Colors.red.shade600)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<PendingJourneysViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('Pending Journey')),
      body: RefreshIndicator(
        onRefresh: () => context.read<PendingJourneysViewModel>().load(),
        child: _buildBody(context, vm),
      ),
    );
  }

  Widget _buildBody(BuildContext context, PendingJourneysViewModel vm) {
    if (vm.loading && vm.journeys.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.journeys.isEmpty) {
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
    if (vm.journeys.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          Padding(
            padding: EdgeInsets.only(top: 80),
            child: Center(
              child: Text('No pending journeys. Every lead you sourced has a loan.', textAlign: TextAlign.center),
            ),
          ),
        ],
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      physics: const AlwaysScrollableScrollPhysics(),
      itemCount: vm.journeys.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final journey = vm.journeys[index];
        return JourneyResumeCard(
          journey: journey,
          discarding: vm.discardingMemberId == journey.memberId,
          onResume: () => _resumeJourney(context, journey),
          onDiscard: () => _confirmDiscard(context, journey.memberId),
        );
      },
    );
  }
}
