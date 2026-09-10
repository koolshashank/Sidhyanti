import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/collections_api.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import 'arrear_center_detail_viewmodel.dart';
import 'widgets/collection_borrower_tile.dart';

class ArrearCenterDetailScreen extends StatelessWidget {
  final int centerId;
  final String centerName;

  const ArrearCenterDetailScreen({super.key, required this.centerId, required this.centerName});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => ArrearCenterDetailViewModel(context.read<CollectionsApi>(), centerId),
      child: _ArrearCenterDetailView(centerName: centerName),
    );
  }
}

class _ArrearCenterDetailView extends StatelessWidget {
  final String centerName;

  const _ArrearCenterDetailView({required this.centerName});

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<ArrearCenterDetailViewModel>();
    final totalArrear = vm.items.fold<double>(0, (sum, i) => sum + (i.arrearAmount ?? 0));

    return Scaffold(
      appBar: AppBar(title: Text(centerName, overflow: TextOverflow.ellipsis)),
      body: vm.loading && vm.items.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : vm.error != null && vm.items.isEmpty
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
                      _HeaderCard(clientCount: vm.items.length, totalArrear: totalArrear),
                      const SizedBox(height: 16),
                      if (vm.items.isEmpty)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 40),
                          child: Center(child: Text('No overdue installments here. 🎉', style: TextStyle(color: AppColors.textMuted(context)))),
                        )
                      else
                        ...vm.items.map(
                          (item) => CollectionBorrowerTile(
                            item: item,
                            onCollected: (scheduleId, amount) => vm.onCollected(),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}

class _HeaderCard extends StatelessWidget {
  final int clientCount;
  final double totalArrear;

  const _HeaderCard({required this.clientCount, required this.totalArrear});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Colors.red.shade400, Colors.red.shade700]),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [BoxShadow(color: Colors.red.withValues(alpha: 0.25), blurRadius: 16, offset: const Offset(0, 8))],
      ),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(16)),
            child: const Icon(Icons.warning_amber_rounded, color: Colors.white, size: 26),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$clientCount clients overdue', style: TextStyle(color: Colors.white.withValues(alpha: 0.9), fontSize: 12.5)),
                const SizedBox(height: 4),
                Text('Total Arrear', style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 11)),
                Text(money(totalArrear), style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
