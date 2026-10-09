import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/centers_api.dart';
import '../../core/models/center_summary.dart';
import '../../core/theme/app_colors.dart';
import 'my_centers_viewmodel.dart';
import 'widgets/meeting_day.dart';

/// First step of "Existing Client Journey" -- the field officer's own
/// centers, searchable. Tapping one goes to [ExistingClientsScreen] (that
/// center's clients who already have a disbursed loan) rather than CGT/new
/// lead, so it deliberately has no CGT-approval gating.
class ExistingClientCentersScreen extends StatelessWidget {
  const ExistingClientCentersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => MyCentersViewModel(context.read<CentersApi>()),
      child: const _ExistingClientCentersView(),
    );
  }
}

class _ExistingClientCentersView extends StatefulWidget {
  const _ExistingClientCentersView();

  @override
  State<_ExistingClientCentersView> createState() => _ExistingClientCentersViewState();
}

class _ExistingClientCentersViewState extends State<_ExistingClientCentersView> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _onTapCenter(BuildContext context, CenterSummary center) {
    context.push('/client-sourcing/existing-centers/${center.id}/clients?center_name=${Uri.encodeComponent(center.name)}');
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<MyCentersViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('Select Center')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: TextField(
              controller: _searchController,
              onChanged: (v) => context.read<MyCentersViewModel>().setSearchQuery(v),
              decoration: InputDecoration(
                hintText: 'Search center by name or ID...',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: vm.searchQuery.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close_rounded),
                        onPressed: () {
                          _searchController.clear();
                          context.read<MyCentersViewModel>().setSearchQuery('');
                        },
                      ),
                filled: true,
                fillColor: AppColors.surface(context),
                contentPadding: const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: AppColors.border(context))),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: AppColors.border(context))),
              ),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => context.read<MyCentersViewModel>().load(),
              child: _buildBody(context, vm),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(BuildContext context, MyCentersViewModel vm) {
    if (vm.loading && vm.centers.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.centers.isEmpty) {
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
    final filtered = vm.filteredCenters;
    if (filtered.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 80),
            child: Center(
              child: Text(
                vm.centers.isEmpty ? 'No centers yet.' : 'No centers match "${vm.searchQuery}"',
                textAlign: TextAlign.center,
              ),
            ),
          ),
        ],
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: filtered.length,
      itemBuilder: (context, index) {
        final center = filtered[index];
        return Container(
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(
            color: AppColors.surface(context),
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: AppColors.border(context)),
            boxShadow: [
              BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 12, offset: const Offset(0, 5)),
            ],
          ),
          child: InkWell(
            borderRadius: BorderRadius.circular(18),
            onTap: () => _onTapCenter(context, center),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          center.name,
                          style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w700, color: AppColors.textPrimary(context)),
                          overflow: TextOverflow.ellipsis,
                        ),
                        if (center.code != null && center.code!.isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Text(
                            'Center ID: ${center.code}',
                            style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context), fontWeight: FontWeight.w600),
                          ),
                        ],
                        if (center.meetingDay != null) ...[
                          const SizedBox(height: 6),
                          Text(
                            'Meets ${formatMeetingDay(center.meetingDay)}${center.meetingTime != null ? ' · ${center.meetingTime}' : ''}',
                            style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
                          ),
                        ],
                      ],
                    ),
                  ),
                  Icon(Icons.chevron_right_rounded, color: AppColors.textFaint(context)),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
