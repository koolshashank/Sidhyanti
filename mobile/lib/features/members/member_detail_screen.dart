import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/models/member.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/contact_actions.dart';
import '../../core/utils/formatters.dart';
import '../../l10n/generated/app_localizations.dart';
import 'member_detail_viewmodel.dart';
import 'widgets/member_list_tile.dart';

class MemberDetailScreen extends StatelessWidget {
  final int memberId;

  const MemberDetailScreen({super.key, required this.memberId});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => MemberDetailViewModel(context.read<MembersApi>(), memberId),
      child: _MemberDetailView(memberId: memberId),
    );
  }
}

class _MemberDetailView extends StatelessWidget {
  final int memberId;

  const _MemberDetailView({required this.memberId});

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<MemberDetailViewModel>();
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      appBar: AppBar(title: Text(vm.member?.fullName ?? l10n.memberDetailTitleFallback)),
      body: _buildBody(context, vm),
    );
  }

  Widget _buildBody(BuildContext context, MemberDetailViewModel vm) {
    final l10n = AppLocalizations.of(context)!;
    if (vm.loading && vm.member == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (vm.error != null && vm.member == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(vm.error!),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: vm.load, child: Text(l10n.commonRetry)),
          ],
        ),
      );
    }
    final member = vm.member!;
    return RefreshIndicator(
      onRefresh: vm.load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          _HeaderCard(member: member),
          const SizedBox(height: 16),
          _ActionRow(memberId: memberId, memberName: member.fullName, enrollmentNo: member.enrollmentNo),
          const SizedBox(height: 16),
          _DetailsCard(member: member),
          const SizedBox(height: 16),
          _OutstandingCard(amount: member.outstandingPrincipal),
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(l10n.memberDetailLoansLabel, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context))),
              TextButton.icon(
                onPressed: () => _onNewLoanTap(context, member),
                icon: Icon(member.reloanEligibility != null ? Icons.autorenew_rounded : Icons.add_circle_outline_rounded, size: 17),
                label: Text(member.reloanEligibility != null ? l10n.memberDetailReloan : l10n.memberDetailNewLoan),
                style: TextButton.styleFrom(foregroundColor: const Color(0xFFE65100), padding: const EdgeInsets.symmetric(horizontal: 8)),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (member.loans.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(l10n.memberDetailNoLoans, style: TextStyle(color: AppColors.textMuted(context))),
            )
          else
            ...member.loans.map((loan) => _LoanTile(loan: loan)),
        ],
      ),
    );
  }

  void _onNewLoanTap(BuildContext context, MemberDetail member) {
    if (!member.canAddLoan) {
      final l10n = AppLocalizations.of(context)!;
      final openLoan = member.loans.firstWhere(
        (l) => !['Closed', 'Rejected', 'Preclosed'].contains(l.stage),
        orElse: () => member.loans.first,
      );
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(l10n.memberDetailOpenLoanExists(openLoan.loanNo)),
        backgroundColor: Colors.red.shade600,
      ));
      return;
    }
    context.push('/client-sourcing/loan-proposal?member_id=$memberId&coborrower_name=');
  }
}

class _HeaderCard extends StatelessWidget {
  final MemberDetail member;

  const _HeaderCard({required this.member});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFBF360C), Color(0xFFE65100)],
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(color: const Color(0xFFE65100).withValues(alpha: 0.3), blurRadius: 16, offset: const Offset(0, 8)),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(3),
            decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: Colors.white.withValues(alpha: 0.5), width: 2)),
            child: MemberAvatar(name: member.fullName, photoUrl: member.photoUrl, radius: 34),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  member.fullName,
                  style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 3),
                Text(member.enrollmentNo, style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12.5, fontWeight: FontWeight.w600)),
                const SizedBox(height: 10),
                if (member.currentStage != null)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(20)),
                    child: Text(member.currentStage!, style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w700)),
                  ),
                const SizedBox(height: 10),
                if (member.mobileNo != null) _ContactRow(phone: member.mobileNo!),
                if (member.centerName != null) _HeaderRow(icon: Icons.groups_rounded, text: member.centerName!),
                if (member.branchName != null) _HeaderRow(icon: Icons.store_rounded, text: member.branchName!),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ContactRow extends StatelessWidget {
  final String phone;

  const _ContactRow({required this.phone});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 3),
      child: Row(
        children: [
          const Icon(Icons.call_rounded, size: 13, color: Colors.white70),
          const SizedBox(width: 6),
          Expanded(
            child: Text(phone, style: const TextStyle(color: Colors.white, fontSize: 12.5), overflow: TextOverflow.ellipsis),
          ),
          _ContactIconButton(icon: Icons.call_rounded, onTap: () => callNumber(context, phone)),
          const SizedBox(width: 6),
          _ContactIconButton(icon: Icons.chat_bubble_rounded, onTap: () => whatsAppNumber(context, phone)),
        ],
      ),
    );
  }
}

class _ContactIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _ContactIconButton({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white.withValues(alpha: 0.18),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(6),
          child: Icon(icon, size: 14, color: Colors.white),
        ),
      ),
    );
  }
}

class _HeaderRow extends StatelessWidget {
  final IconData icon;
  final String text;

  const _HeaderRow({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 3),
      child: Row(
        children: [
          Icon(icon, size: 13, color: Colors.white.withValues(alpha: 0.85)),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              text,
              style: TextStyle(color: Colors.white.withValues(alpha: 0.9), fontSize: 12.5),
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionRow extends StatelessWidget {
  final int memberId;
  final String memberName;
  final String? enrollmentNo;

  const _ActionRow({required this.memberId, required this.memberName, this.enrollmentNo});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _ActionCard(
                icon: Icons.folder_copy_rounded,
                label: l10n.memberDetailActionDocuments,
                color: const Color(0xFFE65100),
                onTap: () => context.push('/members/$memberId/documents?name=${Uri.encodeComponent(memberName)}'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _ActionCard(
                icon: Icons.receipt_long_rounded,
                label: l10n.memberDetailActionRepayments,
                color: const Color(0xFF2FBF87),
                onTap: () => context.push('/members/$memberId/repayment-history'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _ActionCard(
                icon: Icons.timeline_rounded,
                label: l10n.memberDetailActionCustomerJourney,
                color: const Color(0xFFFF9F4A),
                onTap: () => context.push('/members/$memberId/stage-history'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _ActionCard(
                icon: Icons.report_rounded,
                label: l10n.memberDetailActionDeathEntry,
                color: Colors.red.shade400,
                onTap: () => context.push('/members/$memberId/death-entry?name=${Uri.encodeComponent(memberName)}'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _ActionCard(
                icon: Icons.qr_code_2_rounded,
                label: l10n.memberDetailActionShowQr,
                color: const Color(0xFF4A9FFF),
                onTap: () => context.push(
                  '/members/$memberId/qr?name=${Uri.encodeComponent(memberName)}&enrollment_no=${Uri.encodeComponent(enrollmentNo ?? '')}',
                ),
              ),
            ),
            const SizedBox(width: 12),
            const Expanded(child: SizedBox()),
          ],
        ),
      ],
    );
  }
}

class _ActionCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _ActionCard({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface(context),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.border(context))),
          child: Column(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
                child: Icon(icon, color: color, size: 19),
              ),
              const SizedBox(height: 8),
              Text(label, style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700, color: AppColors.textPrimary(context)), textAlign: TextAlign.center),
            ],
          ),
        ),
      ),
    );
  }
}

class _DetailsCard extends StatelessWidget {
  final MemberDetail member;

  const _DetailsCard({required this.member});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(l10n.memberDetailDetailsLabel, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context))),
          const SizedBox(height: 10),
          _DetailRow(l10n.memberDetailFieldFatherHusband, member.fatherHusbandName),
          _DetailRow(l10n.memberDetailFieldGender, member.gender),
          _DetailRow(l10n.memberDetailFieldDob, member.dob != null ? fdate(member.dob) : null),
          _DetailRow(l10n.memberDetailFieldMaritalStatus, member.maritalStatus),
          _DetailRow(l10n.memberDetailFieldOccupation, member.occupationName),
          _DetailRow(l10n.memberDetailFieldAddress, member.address),
          _DetailRow(l10n.memberDetailFieldVillageCity, member.villageCity),
          _DetailRow(l10n.memberDetailFieldDistrict, member.districtName),
          _DetailRow(l10n.memberDetailFieldState, member.stateName),
          _DetailRow(l10n.memberDetailFieldPincode, member.pincode),
          _DetailRow(l10n.memberDetailFieldAadharNo, member.aadharNo),
          _DetailRow(l10n.memberDetailFieldPanNo, member.panNo),
          _DetailRow(l10n.memberDetailFieldLeadSource, member.leadSource),
          _DetailRow(l10n.memberDetailFieldAssignedOfficer, member.assignedOfficer),
          _DetailRow(l10n.memberDetailFieldMemberSince, member.enrollmentDate != null ? fdate(member.enrollmentDate) : null),
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String? value;

  const _DetailRow(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    if (value == null || value!.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(width: 128, child: Text(label, style: TextStyle(color: AppColors.textMuted(context), fontSize: 12.5))),
          Expanded(child: Text(value!, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600))),
        ],
      ),
    );
  }
}

class _OutstandingCard extends StatelessWidget {
  final double amount;

  const _OutstandingCard({required this.amount});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(color: const Color(0xFFFF9F4A).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
                child: const Icon(Icons.account_balance_wallet_rounded, color: Color(0xFFFF9F4A), size: 19),
              ),
              const SizedBox(width: 12),
              Text(AppLocalizations.of(context)!.memberDetailOutstandingPrincipal, style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700)),
            ],
          ),
          Text(money(amount), style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context))),
        ],
      ),
    );
  }
}

class _LoanTile extends StatelessWidget {
  final MemberLoanSummary loan;

  const _LoanTile({required this.loan});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () => context.push('/loans/${loan.loanId}'),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(loan.loanNo, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                      const SizedBox(height: 3),
                      Text(
                        AppLocalizations.of(context)!.memberDetailLoanAppliedLine(loan.stage ?? '-', fdate(loan.applicationDate)),
                        style: TextStyle(fontSize: 12, color: AppColors.textMuted(context)),
                      ),
                    ],
                  ),
                ),
                Text(money(loan.amount), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
                Icon(Icons.chevron_right_rounded, color: AppColors.textFaint(context)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
