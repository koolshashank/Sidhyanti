import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/reports_api.dart';
import '../../core/models/leaderboard.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../l10n/generated/app_localizations.dart';

/// Top 10 collectors this month + top 10 by attendance this month, among
/// Loan/Field Officers in the caller's own branch (Admin sees company-wide)
/// -- see api/v1/reports/leaderboard.php.
class LeaderboardScreen extends StatefulWidget {
  const LeaderboardScreen({super.key});

  @override
  State<LeaderboardScreen> createState() => _LeaderboardScreenState();
}

class _LeaderboardScreenState extends State<LeaderboardScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabController;
  Leaderboard? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _load();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await context.read<ReportsApi>().leaderboard();
      if (!mounted) return;
      setState(() => _data = data);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.leaderboardTitle),
        bottom: TabBar(
          controller: _tabController,
          labelColor: const Color(0xFFE65100),
          unselectedLabelColor: AppColors.textMuted(context),
          indicatorColor: const Color(0xFFE65100),
          tabs: [Tab(text: l10n.leaderboardTabCollectors), Tab(text: l10n.leaderboardTabAttendance)],
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: _load, child: Text(l10n.commonRetry)),
                    ],
                  ),
                )
              : TabBarView(
                  controller: _tabController,
                  children: [
                    _CollectorsTab(rows: _data?.topCollectors ?? [], onRefresh: _load),
                    _AttendanceTab(rows: _data?.topAttendance ?? [], onRefresh: _load),
                  ],
                ),
    );
  }
}

class _CollectorsTab extends StatelessWidget {
  final List<LeaderboardCollectorRow> rows;
  final Future<void> Function() onRefresh;

  const _CollectorsTab({required this.rows, required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    if (rows.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [Padding(padding: const EdgeInsets.only(top: 80), child: Center(child: Text(l10n.leaderboardEmpty)))],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
        itemCount: rows.length,
        itemBuilder: (context, index) {
          final row = rows[index];
          return _LeaderboardTile(
            rank: index + 1,
            name: row.fullName,
            subtitle: row.branchName,
            value: money(row.collectedAmount),
            valueLabel: l10n.leaderboardCollectedThisMonth,
            isMe: row.isMe,
          );
        },
      ),
    );
  }
}

class _AttendanceTab extends StatelessWidget {
  final List<LeaderboardAttendanceRow> rows;
  final Future<void> Function() onRefresh;

  const _AttendanceTab({required this.rows, required this.onRefresh});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    if (rows.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [Padding(padding: const EdgeInsets.only(top: 80), child: Center(child: Text(l10n.leaderboardEmpty)))],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
        itemCount: rows.length,
        itemBuilder: (context, index) {
          final row = rows[index];
          return _LeaderboardTile(
            rank: index + 1,
            name: row.fullName,
            subtitle: row.branchName,
            value: l10n.leaderboardDaysPresent(row.daysPresent),
            valueLabel: null,
            isMe: row.isMe,
          );
        },
      ),
    );
  }
}

class _LeaderboardTile extends StatelessWidget {
  final int rank;
  final String name;
  final String? subtitle;
  final String value;
  final String? valueLabel;
  final bool isMe;

  const _LeaderboardTile({
    required this.rank,
    required this.name,
    required this.subtitle,
    required this.value,
    required this.valueLabel,
    required this.isMe,
  });

  Color get _rankColor {
    switch (rank) {
      case 1:
        return const Color(0xFFC8952C);
      case 2:
        return const Color(0xFF9AA4B2);
      case 3:
        return const Color(0xFFBF6B3A);
      default:
        return const Color(0xFF4A9FFF);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isMe ? (AppColors.isDark(context) ? const Color(0xFF1A2233) : const Color(0xFFF4F8FF)) : AppColors.surface(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: isMe ? const Color(0xFFE65100) : AppColors.border(context), width: isMe ? 1.4 : 1),
      ),
      child: Row(
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(color: _rankColor.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(10)),
            alignment: Alignment.center,
            child: Text('$rank', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: _rankColor)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Flexible(child: Text(name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14), overflow: TextOverflow.ellipsis)),
                    if (isMe) ...[
                      const SizedBox(width: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                        decoration: BoxDecoration(color: const Color(0xFFE65100).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
                        child: Text(l10n.leaderboardYou, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Color(0xFFE65100))),
                      ),
                    ],
                  ],
                ),
                if (subtitle != null) Text(subtitle!, style: TextStyle(fontSize: 11.5, color: AppColors.textMuted(context))),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(value, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
              if (valueLabel != null) Text(valueLabel!, style: TextStyle(fontSize: 10, color: AppColors.textFaint(context))),
            ],
          ),
        ],
      ),
    );
  }
}
