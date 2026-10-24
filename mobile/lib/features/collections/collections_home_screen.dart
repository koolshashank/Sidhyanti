import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/collections_api.dart';
import '../../core/collections/collection_sync_service.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../l10n/generated/app_localizations.dart';
import '../../shell/account_menu_button.dart';
import 'arrear_centers_viewmodel.dart';
import 'collections_centers_viewmodel.dart';
import 'widgets/percent_ring.dart';

class CollectionsHomeScreen extends StatefulWidget {
  const CollectionsHomeScreen({super.key});

  @override
  State<CollectionsHomeScreen> createState() => _CollectionsHomeScreenState();
}

class _CollectionsHomeScreenState extends State<CollectionsHomeScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) context.read<CollectionSyncService>().refreshPendingCount();
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final syncService = context.watch<CollectionSyncService>();
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (context) => CollectionsCentersViewModel(context.read<CollectionsApi>())),
        ChangeNotifierProvider(create: (context) => ArrearCentersViewModel(context.read<CollectionsApi>())),
      ],
      child: Scaffold(
        appBar: AppBar(
          title: Text(l10n.collectionsAppBarTitle),
          actions: const [AccountMenuButton(), SizedBox(width: 8)],
          bottom: TabBar(
            controller: _tabController,
            labelColor: const Color(0xFFE65100),
            unselectedLabelColor: AppColors.textMuted(context),
            indicatorColor: const Color(0xFFE65100),
            tabs: [Tab(text: l10n.collectionsTabCollection), Tab(text: l10n.collectionsTabArrear)],
          ),
        ),
        body: Column(
          children: [
            if (syncService.pendingCount > 0) _PendingSyncBanner(syncService: syncService, l10n: l10n),
            Expanded(
              child: TabBarView(
                controller: _tabController,
                children: const [_CollectionTab(), _ArrearTab()],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PendingSyncBanner extends StatelessWidget {
  final CollectionSyncService syncService;
  final AppLocalizations l10n;

  const _PendingSyncBanner({required this.syncService, required this.l10n});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      color: const Color(0xFFFFF4E5),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(
        children: [
          const Icon(Icons.cloud_off_rounded, size: 18, color: Color(0xFFC8952C)),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              l10n.collectionSyncPendingBanner(syncService.pendingCount),
              style: const TextStyle(color: Color(0xFF8A5A10), fontWeight: FontWeight.w600, fontSize: 12.5),
            ),
          ),
          TextButton(
            onPressed: () => syncService.flushQueue(),
            child: Text(l10n.collectionSyncNow, style: const TextStyle(fontSize: 12.5)),
          ),
        ],
      ),
    );
  }
}

class _CollectionTab extends StatelessWidget {
  const _CollectionTab();

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<CollectionsCentersViewModel>();
    final l10n = AppLocalizations.of(context)!;

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: SegmentedButton<DueRangePreset>(
            segments: [
              ButtonSegment(value: DueRangePreset.today, label: Text(l10n.collectionsFilterToday)),
              ButtonSegment(value: DueRangePreset.yesterday, label: Text(l10n.collectionsFilterYesterday)),
              ButtonSegment(value: DueRangePreset.last7Days, label: Text(l10n.collectionsFilterLast7Days)),
            ],
            selected: {vm.preset},
            onSelectionChanged: (s) => context.read<CollectionsCentersViewModel>().setPreset(s.first),
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => context.read<CollectionsCentersViewModel>().load(),
            child: _buildBody(context, vm),
          ),
        ),
      ],
    );
  }

  Widget _buildBody(BuildContext context, CollectionsCentersViewModel vm) {
    final l10n = AppLocalizations.of(context)!;
    if (vm.loading && vm.centers.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.centers.isEmpty) {
      return _ErrorView(message: vm.error!, onRetry: vm.load);
    }
    if (vm.centers.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 80),
            child: Center(child: Text(l10n.collectionsNoneDueThisPeriod)),
          ),
        ],
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
      itemCount: vm.centers.length,
      itemBuilder: (context, index) {
        final c = vm.centers[index];
        return _CenterCard(
          centerName: c.centerName ?? '-',
          meetingTime: c.meetingTime,
          percent: c.percent,
          clientCount: c.clientCount,
          target: c.target,
          received: c.received,
          balance: c.balance,
          onTap: c.centerId == null
              ? null
              : () => context.push(
                    '/collections/center/${c.centerId}'
                    '?center_name=${Uri.encodeComponent(c.centerName ?? '')}'
                    '&date_from=${_isoDate(vm.dateFrom)}'
                    '&date_to=${_isoDate(vm.dateTo)}',
                  ),
        );
      },
    );
  }

  String _isoDate(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
}

class _ArrearTab extends StatelessWidget {
  const _ArrearTab();

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<ArrearCentersViewModel>();

    if (vm.loading && vm.centers.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.centers.isEmpty) {
      return _ErrorView(message: vm.error!, onRetry: vm.load);
    }
    if (vm.centers.isEmpty) {
      final l10n = AppLocalizations.of(context)!;
      return RefreshIndicator(
        onRefresh: vm.load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 80),
              child: Center(child: Text(l10n.collectionsNoOverdueInstallments)),
            ),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: vm.load,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
        itemCount: vm.centers.length,
        itemBuilder: (context, index) {
          final c = vm.centers[index];
          return _ArrearCenterCard(
            centerName: c.centerName ?? '-',
            meetingTime: c.meetingTime,
            arrearAmount: c.arrearAmount,
            clientCount: c.clientCount,
            maxDaysOverdue: c.maxDaysOverdue,
            onTap: c.centerId == null
                ? null
                : () => context.push(
                      '/collections/arrear-center/${c.centerId}'
                      '?center_name=${Uri.encodeComponent(c.centerName ?? '')}',
                    ),
          );
        },
      ),
    );
  }
}

class _CenterCard extends StatelessWidget {
  final String centerName;
  final String? meetingTime;
  final double percent;
  final int clientCount;
  final double target;
  final double received;
  final double balance;
  final VoidCallback? onTap;

  const _CenterCard({
    required this.centerName,
    required this.meetingTime,
    required this.percent,
    required this.clientCount,
    required this.target,
    required this.received,
    required this.balance,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final ringColor = PercentRing.colorFor(percent);
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border(context)),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 14, offset: const Offset(0, 6)),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            centerName,
                            style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context)),
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 6),
                          Row(
                            children: [
                              Icon(Icons.groups_rounded, size: 13, color: AppColors.textFaint(context)),
                              const SizedBox(width: 4),
                              Text(l10n.collectionsClientsCount(clientCount), style: TextStyle(fontSize: 12, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
                              if (meetingTime != null) ...[
                                const SizedBox(width: 10),
                                Icon(Icons.schedule_rounded, size: 13, color: AppColors.textFaint(context)),
                                const SizedBox(width: 4),
                                Text(meetingTime!, style: TextStyle(fontSize: 12, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
                              ],
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    PercentRing(percent: percent, size: 54),
                  ],
                ),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  decoration: BoxDecoration(color: AppColors.surfaceAlt(context), borderRadius: BorderRadius.circular(14)),
                  child: Row(
                    children: [
                      Expanded(child: _StatColumn(label: l10n.collectionsTarget, value: money(target), color: AppColors.textPrimary(context))),
                      Container(width: 1, height: 28, color: AppColors.divider(context)),
                      Expanded(child: _StatColumn(label: l10n.collectionsReceived, value: money(received), color: const Color(0xFF2FBF87))),
                      Container(width: 1, height: 28, color: AppColors.divider(context)),
                      Expanded(child: _StatColumn(label: l10n.collectionsBalance, value: money(balance), color: ringColor)),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ArrearCenterCard extends StatelessWidget {
  final String centerName;
  final String? meetingTime;
  final double arrearAmount;
  final int clientCount;
  final int maxDaysOverdue;
  final VoidCallback? onTap;

  const _ArrearCenterCard({
    required this.centerName,
    required this.meetingTime,
    required this.arrearAmount,
    required this.clientCount,
    required this.maxDaysOverdue,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border(context)),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 14, offset: const Offset(0, 6)),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Container(
                  width: 50,
                  height: 50,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(colors: [Colors.red.shade300, Colors.red.shade500]),
                    borderRadius: BorderRadius.circular(15),
                  ),
                  child: const Icon(Icons.warning_amber_rounded, color: Colors.white, size: 24),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(centerName, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context)), overflow: TextOverflow.ellipsis),
                      const SizedBox(height: 5),
                      Row(
                        children: [
                          Icon(Icons.groups_rounded, size: 13, color: AppColors.textFaint(context)),
                          const SizedBox(width: 4),
                          Text(l10n.collectionsClientsCount(clientCount), style: TextStyle(fontSize: 12, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
                          const SizedBox(width: 10),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                            decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(20)),
                            child: Text(l10n.collectionsUpToDaysLate(maxDaysOverdue), style: TextStyle(fontSize: 10.5, color: Colors.red.shade600, fontWeight: FontWeight.w700)),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(l10n.collectionsArrear, style: TextStyle(fontSize: 9.5, letterSpacing: 0.4, color: AppColors.textFaint(context), fontWeight: FontWeight.w700)),
                    const SizedBox(height: 2),
                    Text(money(arrearAmount), style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15.5, color: Colors.red.shade500)),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StatColumn extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _StatColumn({required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(label, style: TextStyle(fontSize: 9.5, letterSpacing: 0.3, color: AppColors.textFaint(context), fontWeight: FontWeight.w700)),
        const SizedBox(height: 4),
        Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: color), overflow: TextOverflow.ellipsis),
      ],
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          OutlinedButton(onPressed: onRetry, child: Text(l10n.commonRetry)),
        ],
      ),
    );
  }
}
