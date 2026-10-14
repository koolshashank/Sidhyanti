import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/loans_api.dart';
import '../../core/api/members_api.dart';
import '../../core/models/proposal_review.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/geo_photo_stamp.dart';
import '../../l10n/generated/app_localizations.dart';
import '../operations/widgets/form_section_card.dart';
import '../operations/widgets/form_styles.dart';
import '../operations/widgets/local_photo_preview.dart';
import 'proposal_confirm_screen.dart';
import 'proposal_review_viewmodel.dart';

/// Opened by a Branch/Area Manager from their approval queue (tap a
/// proposal card) -- shows everything the CM captured (applicant summary,
/// loan details, income assessment, bank details), lets the reviewer
/// correct any of it (the Product can only be downgraded to a cheaper one,
/// never upgraded above what the CM originally proposed), conduct GRT right
/// here, then approve (only once GRT has passed) or reject.
class ProposalReviewScreen extends StatelessWidget {
  final int loanId;

  const ProposalReviewScreen({super.key, required this.loanId});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => ProposalReviewViewModel(context.read<LoansApi>(), loanId),
      child: const _ProposalReviewView(),
    );
  }
}

class _ProposalReviewView extends StatefulWidget {
  const _ProposalReviewView();

  @override
  State<_ProposalReviewView> createState() => _ProposalReviewViewState();
}

class _ProposalReviewViewState extends State<_ProposalReviewView> {
  final _amountController = TextEditingController();
  final _emiController = TextEditingController();
  final _selfIncome = TextEditingController();
  final _husbandSonIncome = TextEditingController();
  final _otherIncome = TextEditingController();
  final _residentIncome = TextEditingController();
  final _houseExpenses = TextEditingController();
  final _mfiExpenses = TextEditingController();
  final _businessExpenses = TextEditingController();
  final _ifscController = TextEditingController();
  final _bankNameController = TextEditingController();
  final _bankBranchController = TextEditingController();
  final _bankAddressController = TextEditingController();
  final _accountNumberController = TextEditingController();
  final _beneficiaryNameController = TextEditingController();
  final _grtDateController = TextEditingController();
  final _grtRemarksController = TextEditingController();
  final _memberFirstNameController = TextEditingController();
  final _memberLastNameController = TextEditingController();
  final _fatherHusbandNameController = TextEditingController();
  final _memberMobileController = TextEditingController();
  final _memberAddressController = TextEditingController();
  final _memberVillageCityController = TextEditingController();
  final _coBorrowerFirstNameController = TextEditingController();
  final _coBorrowerLastNameController = TextEditingController();
  final _coBorrowerAddressController = TextEditingController();
  final _coBorrowerVillageCityController = TextEditingController();

  int? _schemeId;
  int? _purposeId;
  double _originalAmount = 0;
  bool _initialized = false;
  bool _ifscLookingUp = false;
  String? _ifscError;

  DateTime? _grtDate;
  bool _grtPassedChoice = true;
  bool? _grtFinanceVerified;
  File? _grtDocument;
  bool _grtProcessingPhoto = false;

  File? _memberPhotoFile;
  File? _coBorrowerPhotoFile;
  bool _memberPhotoUploading = false;
  bool _coBorrowerPhotoUploading = false;

  @override
  void dispose() {
    for (final c in [
      _amountController, _emiController, _selfIncome, _husbandSonIncome, _otherIncome, _residentIncome,
      _houseExpenses, _mfiExpenses, _businessExpenses, _ifscController,
      _bankNameController, _bankBranchController, _bankAddressController,
      _accountNumberController, _beneficiaryNameController,
      _grtDateController, _grtRemarksController,
      _memberFirstNameController, _memberLastNameController, _fatherHusbandNameController, _memberMobileController,
      _memberAddressController, _memberVillageCityController,
      _coBorrowerFirstNameController, _coBorrowerLastNameController, _coBorrowerAddressController, _coBorrowerVillageCityController,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  String _fmtDate(DateTime d) => '${d.day}/${d.month}/${d.year}';

  void _initFrom(ProposalReviewDetail d) {
    if (_initialized) return;
    _initialized = true;
    _schemeId = d.schemeId;
    _purposeId = d.purposeId;
    _originalAmount = d.loanAmount;
    _amountController.text = d.loanAmount.toStringAsFixed(0);
    _emiController.text = d.emiAmount > 0 ? d.emiAmount.toStringAsFixed(0) : '';
    _selfIncome.text = d.selfIncome?.toStringAsFixed(0) ?? '';
    _husbandSonIncome.text = d.husbandSonIncome?.toStringAsFixed(0) ?? '';
    _otherIncome.text = d.otherIncome?.toStringAsFixed(0) ?? '';
    _residentIncome.text = d.residentIncome?.toStringAsFixed(0) ?? '';
    _houseExpenses.text = d.houseExpenses?.toStringAsFixed(0) ?? '';
    _mfiExpenses.text = d.mfiExpenses?.toStringAsFixed(0) ?? '';
    _businessExpenses.text = d.businessExpenses?.toStringAsFixed(0) ?? '';
    _ifscController.text = d.ifscCode ?? '';
    _bankNameController.text = d.bankName ?? '';
    _bankBranchController.text = d.bankBranch ?? '';
    _bankAddressController.text = d.bankAddress ?? '';
    _accountNumberController.text = d.accountNumber ?? '';
    _beneficiaryNameController.text = d.beneficiaryName ?? '';
    _memberFirstNameController.text = d.memberFirstName ?? '';
    _memberLastNameController.text = d.memberLastName ?? '';
    _fatherHusbandNameController.text = d.fatherHusbandName ?? '';
    _memberMobileController.text = d.memberMobileNo ?? '';
    _memberAddressController.text = d.memberAddress ?? '';
    _memberVillageCityController.text = d.memberVillageCity ?? '';
    _coBorrowerFirstNameController.text = d.coBorrowerFirstName ?? '';
    _coBorrowerLastNameController.text = d.coBorrowerLastName ?? '';
    _coBorrowerAddressController.text = d.coBorrowerAddress ?? '';
    _coBorrowerVillageCityController.text = d.coBorrowerVillageCity ?? '';
  }

  double _num(TextEditingController c) => double.tryParse(c.text.trim()) ?? 0;
  double get _totalIncome => _num(_selfIncome) + _num(_husbandSonIncome) + _num(_otherIncome) + _num(_residentIncome);
  double get _totalExpenses => _num(_houseExpenses) + _num(_mfiExpenses) + _num(_businessExpenses);
  double get _disposableIncome => _totalIncome - _totalExpenses;

  /// The reviewer may only move to a cheaper product than what the CM
  /// originally proposed, never a more expensive one -- the dropdown simply
  /// never offers anything above [_originalAmount]. The currently selected
  /// scheme is always kept in the list even if it doesn't satisfy that (so
  /// the dropdown never crashes on a value missing from its own items).
  List<LoanScheme> _selectableSchemes(List<LoanScheme> schemes) {
    return schemes.where((s) => s.defaultAmount <= _originalAmount || s.id == _schemeId).toList();
  }

  /// Deliberately never touches the income/expense controllers -- reducing
  /// the amount or product only changes Amount Applied/EMI, the household
  /// Income Assessment the CM captured stays exactly as entered.
  void _onSchemeChanged(int? schemeId, List<LoanScheme> schemes) {
    setState(() {
      _schemeId = schemeId;
      LoanScheme? scheme;
      for (final s in schemes) {
        if (s.id == schemeId) {
          scheme = s;
          break;
        }
      }
      if (scheme != null) {
        _amountController.text = scheme.defaultAmount.toStringAsFixed(0);
        if (scheme.emiAmount != null) {
          _emiController.text = scheme.emiAmount!.toStringAsFixed(0);
        }
      }
    });
  }

  /// Same free public IFSC lookup the web CRM's member_view.php bank-details
  /// form (and the Loan Proposal screen) already use.
  Future<void> _lookupIfsc() async {
    final l10n = AppLocalizations.of(context)!;
    final code = _ifscController.text.trim().toUpperCase();
    _ifscController.text = code;
    if (!RegExp(r'^[A-Z]{4}0[A-Z0-9]{6}$').hasMatch(code)) {
      setState(() => _ifscError = code.isEmpty ? null : l10n.bmApprovalInvalidIfsc);
      return;
    }
    setState(() {
      _ifscLookingUp = true;
      _ifscError = null;
    });
    try {
      final response = await Dio().get('https://ifsc.razorpay.com/$code');
      final data = response.data as Map<String, dynamic>;
      setState(() {
        _bankNameController.text = data['BANK'] as String? ?? '';
        _bankBranchController.text = data['BRANCH'] as String? ?? '';
        _bankAddressController.text = data['ADDRESS'] as String? ?? '';
      });
    } catch (_) {
      setState(() {
        _ifscError = l10n.bmApprovalIfscNotFound;
        _bankNameController.clear();
        _bankBranchController.clear();
        _bankAddressController.clear();
      });
    } finally {
      if (mounted) setState(() => _ifscLookingUp = false);
    }
  }

  /// GRT can never be backdated -- the calendar only ever offers today,
  /// every earlier date shows disabled/greyed out.
  DateTime get _today => DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);

  Future<void> _pickGrtDate(DateTime? applicationDate) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _grtDate ?? _today,
      firstDate: _today,
      lastDate: _today,
    );
    if (picked == null) return;
    setState(() {
      _grtDate = picked;
      _grtDateController.text = _fmtDate(picked);
    });
  }

  void _pickGrtDocument() {
    final l10n = AppLocalizations.of(context)!;
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        margin: const EdgeInsets.all(12),
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(color: AppColors.surface(context), borderRadius: BorderRadius.circular(18)),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_rounded, color: Color(0xFFE65100)),
              title: Text(l10n.bmApprovalTakePhoto),
              onTap: () async {
                Navigator.pop(context);
                final picked = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 80);
                if (picked == null) return;
                final file = File(picked.path);
                if (mounted) setState(() => _grtProcessingPhoto = true);
                try {
                  await stampLocationOnPhoto(file);
                } finally {
                  if (mounted) setState(() => _grtProcessingPhoto = false);
                }
                if (mounted) setState(() => _grtDocument = file);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded, color: Color(0xFFE65100)),
              title: Text(l10n.bmApprovalChooseFromGallery),
              onTap: () async {
                Navigator.pop(context);
                final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
                if (picked != null) setState(() => _grtDocument = File(picked.path));
              },
            ),
          ],
        ),
      ),
    );
  }

  /// Picks and immediately uploads a replacement profile photo for the
  /// member (isMember=true) or the co-borrower -- unlike the GRT photo,
  /// this saves straight away (same Member_KYC_Document "Client Photograph"
  /// slot kyc_capture_screen.dart uses) rather than waiting for the
  /// reviewer to hit Save & Approve.
  void _pickProfilePhoto({required bool isMember, required ProposalReviewDetail d}) {
    final l10n = AppLocalizations.of(context)!;
    if (!isMember && d.coBorrowerId == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.bmApprovalNoCoBorrowerOnFile)));
      return;
    }
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        margin: const EdgeInsets.all(12),
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(color: AppColors.surface(context), borderRadius: BorderRadius.circular(18)),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_rounded, color: Color(0xFFE65100)),
              title: Text(l10n.bmApprovalTakePhoto),
              onTap: () async {
                Navigator.pop(context);
                final picked = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 80);
                if (picked != null) await _uploadProfilePhoto(File(picked.path), isMember: isMember, d: d);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded, color: Color(0xFFE65100)),
              title: Text(l10n.bmApprovalChooseFromGallery),
              onTap: () async {
                Navigator.pop(context);
                final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
                if (picked != null) await _uploadProfilePhoto(File(picked.path), isMember: isMember, d: d);
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _uploadProfilePhoto(File file, {required bool isMember, required ProposalReviewDetail d}) async {
    final l10n = AppLocalizations.of(context)!;
    final membersApi = context.read<MembersApi>();
    setState(() {
      if (isMember) {
        _memberPhotoUploading = true;
      } else {
        _coBorrowerPhotoUploading = true;
      }
    });
    try {
      await stampLocationOnPhoto(file);
      await membersApi.uploadKycDocument(
            memberId: d.memberId,
            docTypeId: 7,
            file: file,
            coBorrowerId: isMember ? null : d.coBorrowerId,
          );
      if (!mounted) return;
      setState(() {
        if (isMember) {
          _memberPhotoFile = file;
        } else {
          _coBorrowerPhotoFile = file;
        }
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(isMember ? l10n.bmApprovalBorrowerPhotoUpdated : l10n.bmApprovalCoBorrowerPhotoUpdated)));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message), backgroundColor: Colors.red.shade600));
    } finally {
      if (mounted) {
        setState(() {
          if (isMember) {
            _memberPhotoUploading = false;
          } else {
            _coBorrowerPhotoUploading = false;
          }
        });
      }
    }
  }

  Widget _profilePhotoTile({
    required BuildContext context,
    required String label,
    required String? networkUrl,
    required File? localFile,
    required bool uploading,
    required VoidCallback? onTap,
  }) {
    return Expanded(
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: uploading ? null : onTap,
          child: Column(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Container(
                  height: 150,
                  width: double.infinity,
                  color: AppColors.surfaceAlt(context),
                  alignment: Alignment.center,
                  child: uploading
                      ? const Center(child: SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2)))
                      : localFile != null
                          ? Image.file(localFile, fit: BoxFit.cover, alignment: Alignment.topCenter, width: double.infinity, height: double.infinity)
                          : networkUrl != null
                              ? Image.network(
                                  networkUrl,
                                  fit: BoxFit.cover,
                                  alignment: Alignment.topCenter,
                                  width: double.infinity,
                                  height: double.infinity,
                                  errorBuilder: (context, error, stack) => Icon(Icons.broken_image_outlined, color: AppColors.textFaint(context)),
                                )
                              : Icon(Icons.add_a_photo_rounded, color: AppColors.textFaint(context)),
                ),
              ),
              const SizedBox(height: 4),
              Text(label, style: TextStyle(fontSize: 11, color: AppColors.textMuted(context))),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _submitGrt(double loanAmount) async {
    final l10n = AppLocalizations.of(context)!;
    if (_grtDate == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.bmApprovalSelectGrtDate)));
      return;
    }
    if (_grtFinanceVerified == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.bmApprovalSelectFinanceVerified)));
      return;
    }
    if (_grtDocument == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.bmApprovalGrtPhotoRequired)));
      return;
    }
    final vm = context.read<ProposalReviewViewModel>();
    final ok = await vm.submitGrt(
      passed: _grtPassedChoice,
      grtDate: _grtDate!,
      financeAmountVerified: _grtFinanceVerified == true ? loanAmount : null,
      remarks: _grtRemarksController.text.trim(),
      documentFile: _grtDocument,
    );
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.bmApprovalGrtRecorded)));
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.grtError ?? l10n.bmApprovalFailedToRecordGrt), backgroundColor: Colors.red.shade600));
    }
  }

  Future<String?> _showRemarksDialog({required bool approve}) {
    final l10n = AppLocalizations.of(context)!;
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(approve ? l10n.bmApprovalConfirmApproveProposalTitle : l10n.bmApprovalConfirmRejectProposalTitle),
        content: TextField(
          controller: controller,
          decoration: InputDecoration(labelText: l10n.bmApprovalRemarksOptional, border: const OutlineInputBorder()),
          maxLines: 2,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: Text(l10n.commonCancel)),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: approve ? const Color(0xFF2FBF87) : const Color(0xFFDC3545)),
            onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()),
            child: Text(approve ? l10n.bmApprovalApprove : l10n.bmApprovalReject),
          ),
        ],
      ),
    );
  }

  Future<void> _showGrtRequiredDialog() {
    final l10n = AppLocalizations.of(context)!;
    return showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l10n.bmApprovalGrtRequiredTitle),
        content: Text(l10n.bmApprovalGrtRequiredMessage),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: Text(l10n.bmApprovalOk)),
        ],
      ),
    );
  }

  Future<void> _onApprove(ProposalReviewDetail d) async {
    final l10n = AppLocalizations.of(context)!;
    if (!d.grtPassed) {
      await _showGrtRequiredDialog();
      return;
    }
    if (_schemeId == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.bmApprovalSelectProduct)));
      return;
    }
    final amount = double.tryParse(_amountController.text.trim());
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.bmApprovalEnterValidAmount)));
      return;
    }
    if (_memberFirstNameController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.bmApprovalMemberFirstNameRequired)));
      return;
    }
    if (!RegExp(r'^[6-9]\d{9}$').hasMatch(_memberMobileController.text.trim())) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.bmApprovalEnterValidMobile)));
      return;
    }
    final remarks = await _showRemarksDialog(approve: true);
    if (remarks == null || !mounted) return;

    final vm = context.read<ProposalReviewViewModel>();
    String? schemeLabel;
    for (final s in vm.schemes) {
      if (s.id == _schemeId) {
        schemeLabel = '${schemeBaseName(s.name)} (${s.defaultAmount.toStringAsFixed(0)})';
        break;
      }
    }
    String? purposeName;
    for (final p in vm.purposes) {
      if (p.id == _purposeId) {
        purposeName = p.name;
        break;
      }
    }

    final confirmed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ChangeNotifierProvider.value(
          value: vm,
          child: ProposalConfirmScreen(
            detail: d,
            schemeId: _schemeId!,
            productLabel: schemeLabel ?? '-',
            amount: amount,
            emiAmount: double.tryParse(_emiController.text.trim()),
            purposeId: _purposeId,
            purposeName: purposeName,
            selfIncome: double.tryParse(_selfIncome.text.trim()),
            husbandSonIncome: double.tryParse(_husbandSonIncome.text.trim()),
            otherIncome: double.tryParse(_otherIncome.text.trim()),
            residentIncome: double.tryParse(_residentIncome.text.trim()),
            totalIncome: _totalIncome,
            houseExpenses: double.tryParse(_houseExpenses.text.trim()),
            mfiExpenses: double.tryParse(_mfiExpenses.text.trim()),
            businessExpenses: double.tryParse(_businessExpenses.text.trim()),
            totalExpenses: _totalExpenses,
            disposableIncome: _disposableIncome,
            ifscCode: _ifscController.text.trim(),
            bankName: _bankNameController.text.trim(),
            bankBranch: _bankBranchController.text.trim(),
            bankAddress: _bankAddressController.text.trim(),
            accountNumber: _accountNumberController.text.trim(),
            beneficiaryName: _beneficiaryNameController.text.trim(),
            memberFirstName: _memberFirstNameController.text.trim(),
            memberLastName: _memberLastNameController.text.trim(),
            fatherHusbandName: _fatherHusbandNameController.text.trim(),
            memberMobileNo: _memberMobileController.text.trim(),
            memberAddress: _memberAddressController.text.trim(),
            memberVillageCity: _memberVillageCityController.text.trim(),
            coBorrowerFirstName: _coBorrowerFirstNameController.text.trim(),
            coBorrowerLastName: _coBorrowerLastNameController.text.trim(),
            coBorrowerAddress: _coBorrowerAddressController.text.trim(),
            coBorrowerVillageCity: _coBorrowerVillageCityController.text.trim(),
            memberPhotoFile: _memberPhotoFile,
            coBorrowerPhotoFile: _coBorrowerPhotoFile,
            remarks: remarks.isEmpty ? null : remarks,
          ),
        ),
      ),
    );
    if (confirmed == true && mounted) {
      Navigator.of(context).pop(true);
    }
  }

  Future<void> _onReject(ProposalReviewDetail d) async {
    final l10n = AppLocalizations.of(context)!;
    final remarks = await _showRemarksDialog(approve: false);
    if (remarks == null || !mounted) return;
    final vm = context.read<ProposalReviewViewModel>();
    final decided = await vm.decide(loanApprovalId: d.loanApprovalId!, approve: false, remarks: remarks.isEmpty ? null : remarks);
    if (!mounted) return;
    if (decided) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.bmApprovalProposalRejectedSnack)));
      Navigator.of(context).pop(true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.decideError ?? l10n.bmApprovalFailedToReject), backgroundColor: Colors.red.shade600));
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final vm = context.watch<ProposalReviewViewModel>();
    final d = vm.detail;
    if (d != null) _initFrom(d);
    final busy = vm.saving || vm.deciding;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.bmApprovalReviewTitle)),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.loadError != null || d == null
              ? Center(child: Text(vm.loadError ?? l10n.bmApprovalLoanNotFound))
              : d.loanApprovalId == null
                  ? Center(child: Text(l10n.bmApprovalNoPendingProposal))
                  : ListView(
                      padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
                      children: [
                        if (d.approvalStatus == 'BM Approved')
                          Container(
                            margin: const EdgeInsets.only(bottom: 16),
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                            decoration: BoxDecoration(color: AppColors.warningTint(context), borderRadius: BorderRadius.circular(12)),
                            child: Row(
                              children: [
                                Icon(Icons.info_outline_rounded, size: 18, color: Colors.orange.shade800),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    l10n.bmApprovalAlreadyApprovedBanner,
                                    style: TextStyle(fontSize: 12, color: Colors.orange.shade900),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        FormSectionCard(
                          icon: Icons.badge_rounded,
                          title: l10n.bmApprovalSectionApplicantSummary,
                          children: [
                            _readonly(l10n.bmApprovalLabelBranchName, d.branchName ?? '-'),
                            const SizedBox(height: 12),
                            _readonly(l10n.bmApprovalLabelEnrollmentId, d.enrollmentNo ?? '-'),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _memberFirstNameController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelMemberFirstName),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _memberLastNameController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelMemberLastName),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _fatherHusbandNameController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelHusbandFatherName),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _memberMobileController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelMobileNumberRequired),
                              keyboardType: TextInputType.phone,
                              maxLength: 10,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _memberAddressController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelMemberAddress),
                              maxLines: 2,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _memberVillageCityController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelMemberVillageCity),
                            ),
                            const SizedBox(height: 12),
                            if (d.coBorrowerId != null) ...[
                              TextFormField(
                                controller: _coBorrowerFirstNameController,
                                decoration: appFieldDecoration(l10n.bmApprovalLabelCoBorrowerFirstName),
                              ),
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: _coBorrowerLastNameController,
                                decoration: appFieldDecoration(l10n.bmApprovalLabelCoBorrowerLastName),
                              ),
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: _coBorrowerAddressController,
                                decoration: appFieldDecoration(l10n.bmApprovalLabelCoBorrowerAddress),
                                maxLines: 2,
                              ),
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: _coBorrowerVillageCityController,
                                decoration: appFieldDecoration(l10n.bmApprovalLabelCoBorrowerVillageCity),
                              ),
                              const SizedBox(height: 12),
                            ] else ...[
                              _readonly(l10n.bmApprovalLabelCoBorrowerName, '-'),
                              const SizedBox(height: 12),
                            ],
                            _readonly(l10n.bmApprovalLabelCenterName, d.centerName ?? '-'),
                            const SizedBox(height: 12),
                            _readonly(l10n.bmApprovalLabelProposalEnteredOn, fdate(d.applicationDate)),
                            if (d.loanCycle != null) ...[
                              const SizedBox(height: 12),
                              _readonly(l10n.bmApprovalLabelLoanCycle, '${d.loanCycle}'),
                            ],
                            const SizedBox(height: 12),
                            Text(l10n.bmApprovalSectionPhotos, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                _profilePhotoTile(
                                  context: context,
                                  label: l10n.bmApprovalPhotoBorrower,
                                  networkUrl: d.memberPhotoUrl,
                                  localFile: _memberPhotoFile,
                                  uploading: _memberPhotoUploading,
                                  onTap: () => _pickProfilePhoto(isMember: true, d: d),
                                ),
                                const SizedBox(width: 10),
                                _profilePhotoTile(
                                  context: context,
                                  label: l10n.bmApprovalPhotoCoBorrower,
                                  networkUrl: d.coBorrowerPhotoUrl,
                                  localFile: _coBorrowerPhotoFile,
                                  uploading: _coBorrowerPhotoUploading,
                                  onTap: () => _pickProfilePhoto(isMember: false, d: d),
                                ),
                              ],
                            ),
                          ],
                        ),
                        FormSectionCard(
                          icon: Icons.request_quote_rounded,
                          title: l10n.bmApprovalSectionLoanDetails,
                          children: [
                            DropdownButtonFormField<int>(
                              initialValue: _schemeId,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelProductReduced),
                              isExpanded: true,
                              items: _selectableSchemes(vm.schemes)
                                  .map((s) => DropdownMenuItem(value: s.id, child: Text('${schemeBaseName(s.name)} (${s.defaultAmount.toStringAsFixed(0)})')))
                                  .toList(),
                              onChanged: (v) => _onSchemeChanged(v, vm.schemes),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _amountController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelAmountAppliedRequired),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _emiController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelEmiAmount),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            ),
                            const SizedBox(height: 12),
                            DropdownButtonFormField<int>(
                              initialValue: _purposeId,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelNatureOfBusiness),
                              isExpanded: true,
                              items: vm.purposes.map((p) => DropdownMenuItem(value: p.id, child: Text(p.name))).toList(),
                              onChanged: (v) => setState(() => _purposeId = v),
                            ),
                          ],
                        ),
                        FormSectionCard(
                          icon: Icons.account_balance_wallet_rounded,
                          title: l10n.bmApprovalSectionIncomeAssessment,
                          children: [
                            TextFormField(
                              controller: _selfIncome,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelSelfIncome),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _husbandSonIncome,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelHusbandSonIncome),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _otherIncome,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelOtherIncome),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _residentIncome,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelRemittanceIncome),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            _readonly(l10n.bmApprovalLabelTotalIncome, '₹${_totalIncome.toStringAsFixed(0)}'),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _houseExpenses,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelHouseExpenses),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _mfiExpenses,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelMfiExpenses),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _businessExpenses,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelBusinessExpenses),
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              onChanged: (_) => setState(() {}),
                            ),
                            const SizedBox(height: 12),
                            _readonly(l10n.bmApprovalLabelTotalExpenses, '₹${_totalExpenses.toStringAsFixed(0)}'),
                            const SizedBox(height: 12),
                            _readonly(l10n.bmApprovalLabelDisposableIncome, '₹${_disposableIncome.toStringAsFixed(0)}'),
                          ],
                        ),
                        FormSectionCard(
                          icon: Icons.account_balance_rounded,
                          title: l10n.bmApprovalSectionBankDetails,
                          children: [
                            TextFormField(
                              controller: _ifscController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelIfscCode).copyWith(
                                errorText: _ifscError,
                                suffixIcon: _ifscLookingUp
                                    ? const Padding(
                                        padding: EdgeInsets.all(12),
                                        child: SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                                      )
                                    : IconButton(icon: const Icon(Icons.search_rounded), onPressed: _lookupIfsc),
                              ),
                              textCapitalization: TextCapitalization.characters,
                              onFieldSubmitted: (_) => _lookupIfsc(),
                            ),
                            const SizedBox(height: 12),
                            _readonly(l10n.bmApprovalLabelBankNameAuto, _bankNameController.text.isEmpty ? '-' : _bankNameController.text),
                            const SizedBox(height: 12),
                            _readonly(l10n.bmApprovalLabelBankBranchAuto, _bankBranchController.text.isEmpty ? '-' : _bankBranchController.text),
                            const SizedBox(height: 12),
                            _readonly(l10n.bmApprovalLabelBankAddressAuto, _bankAddressController.text.isEmpty ? '-' : _bankAddressController.text),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _accountNumberController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelAccountNumber),
                              keyboardType: TextInputType.number,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _beneficiaryNameController,
                              decoration: appFieldDecoration(l10n.bmApprovalLabelBeneficiaryName),
                            ),
                          ],
                        ),
                        FormSectionCard(
                          icon: Icons.groups_rounded,
                          title: l10n.bmApprovalSectionGrt,
                          children: [
                            if (d.grtStatus != null) ...[
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                                margin: const EdgeInsets.only(bottom: 12),
                                decoration: BoxDecoration(
                                  color: d.grtPassed ? Colors.green.shade50 : Colors.red.shade50,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Row(
                                  children: [
                                    Icon(
                                      d.grtPassed ? Icons.check_circle_rounded : Icons.cancel_rounded,
                                      size: 18,
                                      color: d.grtPassed ? Colors.green.shade700 : Colors.red.shade700,
                                    ),
                                    const SizedBox(width: 8),
                                    Text(
                                      '${l10n.bmApprovalLastGrt(d.grtStatus!)} ${d.grtDate != null ? l10n.bmApprovalLastGrtOn(_fmtDate(d.grtDate!)) : ''}',
                                      style: TextStyle(
                                        fontSize: 12.5,
                                        fontWeight: FontWeight.w600,
                                        color: d.grtPassed ? Colors.green.shade800 : Colors.red.shade800,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                            TextFormField(
                              controller: _grtDateController,
                              readOnly: true,
                              onTap: () => _pickGrtDate(d.applicationDate),
                              decoration: appFieldDecoration(l10n.bmApprovalGrtDateRequired),
                            ),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                Expanded(
                                  child: _GrtStatusChip(
                                    label: l10n.bmApprovalGrtPass,
                                    selected: _grtPassedChoice,
                                    color: const Color(0xFF2FBF87),
                                    onTap: () => setState(() => _grtPassedChoice = true),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: _GrtStatusChip(
                                    label: l10n.bmApprovalGrtFail,
                                    selected: !_grtPassedChoice,
                                    color: Colors.red.shade400,
                                    onTap: () => setState(() => _grtPassedChoice = false),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            Text(l10n.bmApprovalLabelFinanceVerified, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                Expanded(
                                  child: _GrtStatusChip(
                                    label: l10n.bmApprovalYes,
                                    selected: _grtFinanceVerified == true,
                                    color: const Color(0xFF2FBF87),
                                    onTap: () => setState(() => _grtFinanceVerified = true),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: _GrtStatusChip(
                                    label: l10n.bmApprovalNo,
                                    selected: _grtFinanceVerified == false,
                                    color: Colors.red.shade400,
                                    onTap: () => setState(() => _grtFinanceVerified = false),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _grtRemarksController,
                              decoration: appFieldDecoration(l10n.bmApprovalRemarksOptional),
                              maxLines: 2,
                            ),
                            const SizedBox(height: 12),
                            Text(l10n.bmApprovalLabelGrtPhotoRequired, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context), fontWeight: FontWeight.w600)),
                            const SizedBox(height: 8),
                            Material(
                              color: Colors.transparent,
                              child: InkWell(
                                borderRadius: BorderRadius.circular(12),
                                onTap: _grtProcessingPhoto ? null : _pickGrtDocument,
                                child: Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(color: AppColors.surfaceAlt(context), borderRadius: BorderRadius.circular(12)),
                                  child: Row(
                                    children: [
                                      ClipRRect(
                                        borderRadius: BorderRadius.circular(10),
                                        child: _grtProcessingPhoto
                                            ? Container(
                                                width: 44,
                                                height: 44,
                                                color: AppColors.border(context),
                                                child: const Center(child: SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))),
                                              )
                                            : _grtDocument != null
                                                ? GestureDetector(
                                                    onTap: () => showLocalPhotoPreview(context, _grtDocument!),
                                                    child: Image.file(_grtDocument!, width: 44, height: 44, fit: BoxFit.cover),
                                                  )
                                                : Container(
                                                    width: 44,
                                                    height: 44,
                                                    color: AppColors.border(context),
                                                    child: const Icon(Icons.add_a_photo_rounded, color: Color(0xFFE65100), size: 20),
                                                  ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Text(
                                          _grtProcessingPhoto ? l10n.bmApprovalProcessingPhoto : (_grtDocument != null ? l10n.bmApprovalGrtDocumentPhoto : l10n.bmApprovalTapToCaptureGrtPhoto),
                                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5),
                                        ),
                                      ),
                                      if (!_grtProcessingPhoto)
                                        Icon(
                                          _grtDocument != null ? Icons.check_circle_rounded : Icons.chevron_right_rounded,
                                          color: _grtDocument != null ? Colors.green.shade600 : AppColors.textFaint(context),
                                        ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(height: 12),
                            SizedBox(
                              width: double.infinity,
                              child: OutlinedButton(
                                onPressed: (vm.grtSubmitting || _grtProcessingPhoto) ? null : () => _submitGrt(_num(_amountController)),
                                child: vm.grtSubmitting
                                    ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                    : Text(l10n.bmApprovalSubmitGrt),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: const Color(0xFFDC3545),
                                  side: const BorderSide(color: Color(0xFFDC3545)),
                                  padding: const EdgeInsets.symmetric(vertical: 14),
                                ),
                                onPressed: busy ? null : () => _onReject(d),
                                child: Text(l10n.bmApprovalReject),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: FilledButton(
                                style: FilledButton.styleFrom(
                                  backgroundColor: d.grtPassed ? const Color(0xFF2FBF87) : Colors.grey,
                                  padding: const EdgeInsets.symmetric(vertical: 14),
                                ),
                                onPressed: busy ? null : () => _onApprove(d),
                                child: busy
                                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                    : Text(l10n.bmApprovalSaveAndApprove),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
    );
  }

  Widget _readonly(String label, String value) {
    return TextFormField(
      readOnly: true,
      controller: TextEditingController(text: value),
      style: TextStyle(color: AppColors.textPrimary(context), fontWeight: FontWeight.w600),
      decoration: appFieldDecoration(label),
    );
  }
}

class _GrtStatusChip extends StatelessWidget {
  final String label;
  final bool selected;
  final Color color;
  final VoidCallback onTap;

  const _GrtStatusChip({required this.label, required this.selected, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: selected ? color.withValues(alpha: 0.12) : AppColors.surfaceAlt(context),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: selected ? color : Colors.transparent, width: 1.5),
          ),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5, color: selected ? color : AppColors.textMuted(context)),
          ),
        ),
      ),
    );
  }
}
