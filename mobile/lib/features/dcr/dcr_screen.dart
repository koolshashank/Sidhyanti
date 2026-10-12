import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/reports_api.dart';
import '../../core/models/approval.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../dashboard/widgets/dashboard_card.dart';
import '../dashboard/widgets/section_heading.dart';

/// Daily Collection Report -- a Branch Manager's per-date, per-center
/// breakdown of due/collected/pending/partial installments.
class DcrScreen extends StatefulWidget {
  const DcrScreen({super.key});

  @override
  State<DcrScreen> createState() => _DcrScreenState();
}

class _DcrScreenState extends State<DcrScreen> {
  DateTime _date = DateTime.now();
  DcrReport? _report;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final report = await context.read<ReportsApi>().dcr(date: _date);
      if (!mounted) return;
      setState(() => _report = report);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      setState(() => _date = picked);
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('DCR'),
        actions: [
          IconButton(icon: const Icon(Icons.calendar_today_rounded), onPressed: _pickDate),
        ],
      ),
      body: _loading && _report == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _report == null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      SectionHeading(fdate(_date)),
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
                            icon: Icons.event_note_rounded,
                            gradient: const [Color(0xFFE65100), Color(0xFFBF360C)],
                            title: 'Due',
                            value: '${_report?.dueCount ?? 0}',
                            subtitle: money(_report?.dueAmount ?? 0),
                          ),
                          DashboardCard(
                            icon: Icons.check_circle_rounded,
                            gradient: const [Color(0xFF2FBF87), Color(0xFF1E9D6B)],
                            title: 'Collected',
                            value: '${_report?.collectedCount ?? 0}',
                            subtitle: money(_report?.collectedAmount ?? 0),
                          ),
                          DashboardCard(
                            icon: Icons.pending_actions_rounded,
                            gradient: const [Color(0xFFFF9F4A), Color(0xFFFF6F4A)],
                            title: 'Pending',
                            value: '${_report?.pendingCount ?? 0}',
                          ),
                          DashboardCard(
                            icon: Icons.call_split_rounded,
                            gradient: [Colors.red.shade300, Colors.red.shade600],
                            title: 'Partial',
                            value: '${_report?.partialCount ?? 0}',
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      const SectionHeading('By Center'),
                      const SizedBox(height: 10),
                      if (_report == null || _report!.byCenter.isEmpty)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 20),
                          child: Center(child: Text('No collections due this day.', style: TextStyle(color: AppColors.textMuted(context)))),
                        )
                      else
                        Container(
                          decoration: BoxDecoration(
                            color: AppColors.surface(context),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: AppColors.border(context)),
                          ),
                          child: Column(
                            children: _report!.byCenter.asMap().entries.map((entry) {
                              final c = entry.value;
                              final isLast = entry.key == _report!.byCenter.length - 1;
                              return Container(
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                                decoration: BoxDecoration(
                                  border: isLast ? null : Border(bottom: BorderSide(color: AppColors.divider(context))),
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(c.centerName, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                                          const SizedBox(height: 2),
                                          Text(
                                            '${c.collectedCount}/${c.dueCount} collected · ${c.partialCount} partial',
                                            style: TextStyle(fontSize: 11.5, color: AppColors.textMuted(context)),
                                          ),
                                        ],
                                      ),
                                    ),
                                    Text(money(c.collectedAmount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: Color(0xFF2FBF87))),
                                  ],
                                ),
                              );
                            }).toList(),
                          ),
                        ),
                    ],
                  ),
                ),
    );
  }
}
