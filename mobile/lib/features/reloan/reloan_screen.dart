import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/reloan_candidate.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';

/// Proactive worklist of clients eligible for a reloan (their previous loan
/// is Closed/Preclosed and a Reloan_Eligibility_Rule matches) -- tapping one
/// jumps straight into the same loan-proposal screen the "New Loan" button
/// on Member Detail uses, which auto-shows the pre-approved amount banner.
class ReloanScreen extends StatefulWidget {
  const ReloanScreen({super.key});

  @override
  State<ReloanScreen> createState() => _ReloanScreenState();
}

class _ReloanScreenState extends State<ReloanScreen> {
  List<ReloanCandidate> _items = [];
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
      final items = await context.read<LoansApi>().reloanEligible();
      if (!mounted) return;
      setState(() => _items = items);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _openProposal(ReloanCandidate item) {
    context.push('/client-sourcing/loan-proposal?member_id=${item.memberId}&coborrower_name=');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Reloan')),
      body: _loading && _items.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _items.isEmpty
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
                  child: _items.isEmpty
                      ? ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.all(40),
                          children: [
                            Center(child: Text('No clients are currently eligible for a reloan.', style: TextStyle(color: AppColors.textMuted(context)))),
                          ],
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                          itemCount: _items.length,
                          itemBuilder: (context, index) {
                            final item = _items[index];
                            return Container(
                              margin: const EdgeInsets.only(bottom: 12),
                              decoration: BoxDecoration(
                                color: AppColors.surface(context),
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(color: AppColors.border(context)),
                              ),
                              child: Material(
                                color: Colors.transparent,
                                child: InkWell(
                                  borderRadius: BorderRadius.circular(16),
                                  onTap: () => _openProposal(item),
                                  child: Padding(
                                    padding: const EdgeInsets.all(14),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Text(item.fullName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5)),
                                            ),
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                                              decoration: BoxDecoration(color: const Color(0xFF2FBF87).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                                              child: const Text('Pre-Approved', style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: Color(0xFF2FBF87))),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          '${item.enrollmentNo ?? '-'} · ${item.mobileNo ?? '-'}',
                                          style: TextStyle(fontSize: 12, color: AppColors.textFaint(context)),
                                        ),
                                        const SizedBox(height: 8),
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Text(
                                                'Previous: ${item.previousLoanNo} (${money(item.previousLoanAmount)})',
                                                style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
                                              ),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          'Cycle ${item.cycleNumber} · ${item.latePayments} late payment(s) · +${item.increasePercent.toStringAsFixed(0)}% (${item.ruleName})',
                                          style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context)),
                                        ),
                                        const SizedBox(height: 8),
                                        Text(
                                          'Eligible Amount: ${money(item.eligibleAmount)}',
                                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF1E9D6B)),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                ),
    );
  }
}
