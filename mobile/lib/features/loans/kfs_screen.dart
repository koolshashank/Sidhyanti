import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/endpoints.dart';
import '../../core/api/loans_api.dart';
import '../../core/models/kfs.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../operations/widgets/form_section_card.dart';

/// Read-only view of a loan's Key Fact Sheet (RBI's pre-disbursement
/// disclosure document) -- see api/v1/loans/kfs.php / includes/kfs_helper.php.
/// Printing itself happens from the web CRM (kfs_view.php, a full A4 print
/// layout); this screen lets the field/branch team review the figures and
/// record that the printed copy was signed and collected.
class KfsScreen extends StatefulWidget {
  final int loanId;

  const KfsScreen({super.key, required this.loanId});

  @override
  State<KfsScreen> createState() => _KfsScreenState();
}

class _KfsScreenState extends State<KfsScreen> {
  KfsData? _data;
  bool _loading = true;
  bool _marking = false;
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
      final data = await context.read<LoansApi>().kfs(widget.loanId);
      if (!mounted) return;
      setState(() => _data = data);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _markSigned() async {
    setState(() => _marking = true);
    try {
      await context.read<LoansApi>().markKfsSigned(widget.loanId);
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _marking = false);
    }
  }

  Future<void> _openPrintView() async {
    final uri = Uri.parse('$webBaseUrl/kfs_view.php?loan_id=${widget.loanId}');
    final ok = await launchUrl(uri, mode: LaunchMode.externalApplication).catchError((_) => false);
    if (!ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Open the web CRM to print this Key Fact Sheet')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Key Fact Sheet')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
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
              : _buildBody(context, _data!),
    );
  }

  Widget _buildBody(BuildContext context, KfsData kfs) {
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [Color(0xFF14A9AB), Color(0xFF0F8B8D)]),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(kfs.loanNo, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
                      Text(kfs.memberName, style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.white70)),
                    ],
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: _openPrintView,
                  style: OutlinedButton.styleFrom(foregroundColor: Colors.white, side: const BorderSide(color: Colors.white54)),
                  icon: const Icon(Icons.print_rounded, size: 18),
                  label: const Text('Print'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          FormSectionCard(
            icon: Icons.description_rounded,
            title: 'Loan Details',
            children: [
              _row('Sanctioned Amount', money(kfs.loanAmountSanctioned)),
              _row('Tenure', '${kfs.tenureMonths} months'),
              _row('Rate of Interest', '${kfs.interestRatePercent}% p.a.'),
              _row('Repayment Frequency', kfs.repaymentFrequency),
              _row('EMI Amount', money(kfs.emiAmount)),
            ],
          ),
          const SizedBox(height: 12),
          FormSectionCard(
            icon: Icons.receipt_long_rounded,
            title: 'Fees & Charges',
            children: [
              _row('Processing Fee', money(kfs.processingFee)),
              _row('Insurance Charge', money(kfs.insuranceAmount)),
              _row('Other Charges', money(kfs.otherCharges)),
              _row('Total Deductions', money(kfs.totalDeductions)),
              _row('Net Amount Disbursed', money(kfs.netDisbursedAmount), bold: true),
            ],
          ),
          const SizedBox(height: 12),
          FormSectionCard(
            icon: Icons.percent_rounded,
            title: 'Cost of Loan',
            children: [
              _row('Total Interest Payable', money(kfs.totalInterestPayable)),
              _row('Total Amount Payable', money(kfs.totalAmountPayable)),
              _row('Annual Percentage Rate (APR)', kfs.aprPercent != null ? '${kfs.aprPercent}%' : 'N/A', bold: true),
            ],
          ),
          const SizedBox(height: 12),
          FormSectionCard(
            icon: Icons.info_outline_rounded,
            title: 'Other Disclosures',
            children: [
              _row('Cooling-off Period', '${kfs.coolingOffPeriodDays} days from disbursement'),
              _row('Grievance Officer', '${kfs.company.grievanceOfficerName}, ${kfs.company.grievanceOfficerPhone}'),
            ],
          ),
          const SizedBox(height: 16),
          if (kfs.kfsSignedCollected)
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: const Color(0xFFE8F7F0), borderRadius: BorderRadius.circular(14)),
              child: Row(
                children: [
                  const Icon(Icons.check_circle_rounded, color: Color(0xFF2FBF87)),
                  const SizedBox(width: 10),
                  Text(
                    'Signed & collected${kfs.kfsSignedAt != null ? ' on ${fdate(kfs.kfsSignedAt)}' : ''}',
                    style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF2FBF87)),
                  ),
                ],
              ),
            )
          else
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: _marking ? null : _markSigned,
                icon: _marking
                    ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.check_circle_outline_rounded, size: 18),
                label: const Text('Mark Signed & Collected'),
                style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF2FBF87), padding: const EdgeInsets.symmetric(vertical: 14)),
              ),
            ),
        ],
      ),
    );
  }

  Widget _row(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(child: Text(label, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)))),
          Text(value, style: TextStyle(fontSize: 13, fontWeight: bold ? FontWeight.w800 : FontWeight.w600)),
        ],
      ),
    );
  }
}
