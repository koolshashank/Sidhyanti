import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/models/proposal_review.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../operations/widgets/form_section_card.dart';
import 'proposal_review_viewmodel.dart';

/// The last step before a proposal is actually approved -- a single
/// read-only summary of everything that will be saved (the reviewer's
/// corrections, the GRT result, and the member/co-borrower/GRT photos), so
/// nothing gets submitted by accident. Tapping "Confirm & Approve" here is
/// what actually calls saveChanges + decide; the review screen itself never
/// submits directly any more.
class ProposalConfirmScreen extends StatefulWidget {
  final ProposalReviewDetail detail;
  final int schemeId;
  final String productLabel;
  final double amount;
  final double? emiAmount;
  final int? purposeId;
  final String? purposeName;
  final double? selfIncome;
  final double? husbandSonIncome;
  final double? otherIncome;
  final double? residentIncome;
  final double totalIncome;
  final double? houseExpenses;
  final double? mfiExpenses;
  final double? businessExpenses;
  final double totalExpenses;
  final double disposableIncome;
  final String? ifscCode;
  final String? bankName;
  final String? bankBranch;
  final String? bankAddress;
  final String? accountNumber;
  final String? beneficiaryName;
  final String? remarks;

  const ProposalConfirmScreen({
    super.key,
    required this.detail,
    required this.schemeId,
    required this.productLabel,
    required this.amount,
    required this.emiAmount,
    required this.purposeId,
    required this.purposeName,
    required this.selfIncome,
    required this.husbandSonIncome,
    required this.otherIncome,
    required this.residentIncome,
    required this.totalIncome,
    required this.houseExpenses,
    required this.mfiExpenses,
    required this.businessExpenses,
    required this.totalExpenses,
    required this.disposableIncome,
    required this.ifscCode,
    required this.bankName,
    required this.bankBranch,
    required this.bankAddress,
    required this.accountNumber,
    required this.beneficiaryName,
    required this.remarks,
  });

  @override
  State<ProposalConfirmScreen> createState() => _ProposalConfirmScreenState();
}

class _ProposalConfirmScreenState extends State<ProposalConfirmScreen> {
  Future<void> _confirm() async {
    final vm = context.read<ProposalReviewViewModel>();
    final saved = await vm.saveChanges(
      schemeId: widget.schemeId,
      amount: widget.amount,
      purposeId: widget.purposeId,
      emiAmount: widget.emiAmount,
      selfIncome: widget.selfIncome,
      husbandSonIncome: widget.husbandSonIncome,
      otherIncome: widget.otherIncome,
      residentIncome: widget.residentIncome,
      houseExpenses: widget.houseExpenses,
      mfiExpenses: widget.mfiExpenses,
      businessExpenses: widget.businessExpenses,
      ifscCode: widget.ifscCode,
      bankName: widget.bankName,
      bankBranch: widget.bankBranch,
      bankAddress: widget.bankAddress,
      accountNumber: widget.accountNumber,
      beneficiaryName: widget.beneficiaryName,
    );
    if (!mounted) return;
    if (!saved) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.saveError ?? 'Failed to save changes'), backgroundColor: Colors.red.shade600));
      return;
    }
    final decided = await vm.decide(loanApprovalId: widget.detail.loanApprovalId!, approve: true, remarks: widget.remarks);
    if (!mounted) return;
    if (decided) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Loan proposal approved')));
      Navigator.of(context).pop(true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.decideError ?? 'Failed to approve'), backgroundColor: Colors.red.shade600));
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<ProposalReviewViewModel>();
    final d = widget.detail;
    final busy = vm.saving || vm.deciding;

    return Scaffold(
      appBar: AppBar(title: const Text('Confirm & Approve')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: AppColors.accentTint(context), borderRadius: BorderRadius.circular(12)),
            child: Row(
              children: [
                const Icon(Icons.fact_check_rounded, size: 18, color: Color(0xFFE65100)),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Please review everything below before approving -- this is the final step.',
                    style: const TextStyle(fontSize: 12.5, color: Color(0xFFBF360C), fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
          ),
          FormSectionCard(
            icon: Icons.badge_rounded,
            title: 'Applicant Summary',
            children: [
              _row('Branch', d.branchName ?? '-'),
              _row('Enrollment ID', d.enrollmentNo ?? '-'),
              _row('Borrower Name', d.borrowerName),
              _row('Co-Borrower Name', d.coBorrowerName ?? '-'),
              _row('Center Name', d.centerName ?? '-', isLast: true),
            ],
          ),
          FormSectionCard(
            icon: Icons.request_quote_rounded,
            title: 'Loan Details',
            children: [
              _row('Product', widget.productLabel),
              _row('Amount Applied', money(widget.amount)),
              _row('EMI Amount', widget.emiAmount != null ? money(widget.emiAmount) : '-'),
              _row('Nature of Business', widget.purposeName ?? '-', isLast: true),
            ],
          ),
          FormSectionCard(
            icon: Icons.account_balance_wallet_rounded,
            title: 'Income Assessment',
            children: [
              _row('Self Income', widget.selfIncome != null ? money(widget.selfIncome) : '-'),
              _row('Husband/Son Income', widget.husbandSonIncome != null ? money(widget.husbandSonIncome) : '-'),
              _row('Other Income', widget.otherIncome != null ? money(widget.otherIncome) : '-'),
              _row('Remittance Income', widget.residentIncome != null ? money(widget.residentIncome) : '-'),
              _row('Total Income', money(widget.totalIncome)),
              _row('House Expenses', widget.houseExpenses != null ? money(widget.houseExpenses) : '-'),
              _row('Other MFI Loan Repayment (Monthly)', widget.mfiExpenses != null ? money(widget.mfiExpenses) : '-'),
              _row('Business Expenses', widget.businessExpenses != null ? money(widget.businessExpenses) : '-'),
              _row('Total Expenses', money(widget.totalExpenses)),
              _row('Disposable Income', money(widget.disposableIncome), isLast: true),
            ],
          ),
          FormSectionCard(
            icon: Icons.account_balance_rounded,
            title: 'Bank Details',
            children: [
              _row('IFSC Code', widget.ifscCode?.isNotEmpty == true ? widget.ifscCode! : '-'),
              _row('Bank Name', widget.bankName?.isNotEmpty == true ? widget.bankName! : '-'),
              _row('Bank Branch', widget.bankBranch?.isNotEmpty == true ? widget.bankBranch! : '-'),
              _row('Account Number', _maskAccount(widget.accountNumber)),
              _row('Beneficiary Name', widget.beneficiaryName?.isNotEmpty == true ? widget.beneficiaryName! : '-', isLast: true),
            ],
          ),
          FormSectionCard(
            icon: Icons.groups_rounded,
            title: 'GRT Result',
            children: [
              _row('Status', d.grtStatus ?? '-'),
              _row('GRT Date', d.grtDate != null ? fdate(d.grtDate) : '-', isLast: true),
            ],
          ),
          FormSectionCard(
            icon: Icons.photo_library_rounded,
            title: 'Photos',
            children: [
              Row(
                children: [
                  Expanded(child: _PhotoThumb(label: 'Borrower', url: d.memberPhotoUrl)),
                  const SizedBox(width: 10),
                  Expanded(child: _PhotoThumb(label: 'Co-Borrower', url: d.coBorrowerPhotoUrl)),
                  const SizedBox(width: 10),
                  Expanded(child: _PhotoThumb(label: 'GRT', url: d.grtPhotoUrl)),
                ],
              ),
            ],
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              style: FilledButton.styleFrom(backgroundColor: const Color(0xFF2FBF87), padding: const EdgeInsets.symmetric(vertical: 14)),
              onPressed: busy ? null : _confirm,
              child: busy
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Confirm & Approve'),
            ),
          ),
        ],
      ),
    );
  }

  String _maskAccount(String? accountNumber) {
    if (accountNumber == null || accountNumber.isEmpty) return '-';
    if (accountNumber.length <= 4) return accountNumber;
    return '${'x' * (accountNumber.length - 4)}${accountNumber.substring(accountNumber.length - 4)}';
  }

  Widget _row(String label, String value, {bool isLast = false}) {
    return Padding(
      padding: EdgeInsets.only(bottom: isLast ? 0 : 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(flex: 4, child: Text(label, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)))),
          const SizedBox(width: 8),
          Expanded(
            flex: 5,
            child: Text(value, textAlign: TextAlign.right, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textPrimary(context))),
          ),
        ],
      ),
    );
  }
}

class _PhotoThumb extends StatelessWidget {
  final String label;
  final String? url;

  const _PhotoThumb({required this.label, required this.url});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        GestureDetector(
          onTap: url == null ? null : () => _openFullScreen(context),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: Container(
              height: 90,
              width: double.infinity,
              color: AppColors.surfaceAlt(context),
              child: url == null
                  ? Icon(Icons.image_not_supported_outlined, color: AppColors.textFaint(context))
                  : Image.network(
                      url!,
                      fit: BoxFit.cover,
                      loadingBuilder: (context, child, progress) =>
                          progress == null ? child : const Center(child: CircularProgressIndicator(strokeWidth: 2)),
                      errorBuilder: (context, error, stack) => Icon(Icons.broken_image_outlined, color: AppColors.textFaint(context)),
                    ),
            ),
          ),
        ),
        const SizedBox(height: 4),
        Text(label, style: TextStyle(fontSize: 11, color: AppColors.textMuted(context))),
      ],
    );
  }

  void _openFullScreen(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white, title: Text(label)),
        body: Center(
          child: InteractiveViewer(
            minScale: 0.8,
            maxScale: 4,
            child: Stack(
              alignment: Alignment.center,
              children: [
                const CircularProgressIndicator(color: Colors.white),
                Image.network(
                  url!,
                  fit: BoxFit.contain,
                  loadingBuilder: (context, child, progress) => progress == null ? child : const SizedBox.shrink(),
                  errorBuilder: (context, error, stack) => const Padding(
                    padding: EdgeInsets.all(24),
                    child: Text(
                      "Couldn't load this photo -- the file server may be unreachable.",
                      style: TextStyle(color: Colors.white),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ));
  }
}
