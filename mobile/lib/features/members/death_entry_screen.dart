import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/api/death_entry_api.dart';
import '../../core/models/death_entry.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/geo_photo_stamp.dart';
import '../../core/utils/input_formatters.dart';
import '../../l10n/generated/app_localizations.dart';
import '../operations/widgets/form_section_card.dart';
import '../operations/widgets/form_styles.dart';
import '../operations/widgets/local_photo_preview.dart';
import 'death_entry_viewmodel.dart';

class DeathEntryScreen extends StatelessWidget {
  final int memberId;
  final String memberName;

  const DeathEntryScreen({super.key, required this.memberId, required this.memberName});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => DeathEntryViewModel(context.read<DeathEntryApi>(), memberId),
      child: _DeathEntryView(memberName: memberName),
    );
  }
}

class _DeathEntryView extends StatefulWidget {
  final String memberName;

  const _DeathEntryView({required this.memberName});

  @override
  State<_DeathEntryView> createState() => _DeathEntryViewState();
}

class _DeathEntryViewState extends State<_DeathEntryView> {
  final _causeController = TextEditingController();
  final _informantNameController = TextEditingController();
  final _dobController = TextEditingController();
  String? _informantRelation;
  DateTime? _dateOfDeath;
  File? _proofFile;
  bool _processingProof = false;

  // Values sent to the API stay fixed English tokens; only the on-screen
  // label is localized (see _relationLabel below).
  static const _relations = ['Husband', 'Wife', 'Son', 'Daughter', 'Father', 'Mother', 'Sibling', 'Other'];

  String _relationLabel(AppLocalizations l10n, String relation) {
    switch (relation) {
      case 'Husband':
        return l10n.deathEntryRelationHusband;
      case 'Wife':
        return l10n.deathEntryRelationWife;
      case 'Son':
        return l10n.deathEntryRelationSon;
      case 'Daughter':
        return l10n.deathEntryRelationDaughter;
      case 'Father':
        return l10n.deathEntryRelationFather;
      case 'Mother':
        return l10n.deathEntryRelationMother;
      case 'Sibling':
        return l10n.deathEntryRelationSibling;
      case 'Other':
        return l10n.deathEntryRelationOther;
      default:
        return relation;
    }
  }

  @override
  void dispose() {
    _causeController.dispose();
    _informantNameController.dispose();
    _dobController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      setState(() {
        _dateOfDeath = picked;
        _dobController.text = '${picked.day}/${picked.month}/${picked.year}';
      });
    }
  }

  Future<void> _pickProof() async {
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
              title: Text(l10n.grtTakePhoto),
              onTap: () async {
                Navigator.pop(context);
                final picked = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 80);
                if (picked == null) return;
                final file = File(picked.path);
                if (mounted) setState(() => _processingProof = true);
                try {
                  await stampLocationOnPhoto(file);
                } finally {
                  if (mounted) setState(() => _processingProof = false);
                }
                if (mounted) setState(() => _proofFile = file);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded, color: Color(0xFFE65100)),
              title: Text(l10n.grtChooseFromGallery),
              onTap: () async {
                Navigator.pop(context);
                final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
                if (picked != null) setState(() => _proofFile = File(picked.path));
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    final l10n = AppLocalizations.of(context)!;
    if (_dateOfDeath == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.deathEntrySelectDateRequired)));
      return;
    }
    final vm = context.read<DeathEntryViewModel>();
    final ok = await vm.submit(
      dateOfDeath: _dateOfDeath!,
      cause: _causeController.text.trim(),
      informantName: _informantNameController.text.trim(),
      informantRelation: _informantRelation,
      proofFile: _proofFile,
    );
    if (!mounted) return;
    if (ok) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(l10n.deathEntrySubmittedSuccess)));
    } else if (vm.submitError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.submitError!), backgroundColor: Colors.red.shade600));
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<DeathEntryViewModel>();
    final l10n = AppLocalizations.of(context)!;
    final latest = vm.entries.isNotEmpty ? vm.entries.first : null;
    final canSubmit = latest == null || latest.status == 'Rejected';

    return Scaffold(
      appBar: AppBar(title: Text(l10n.deathEntryTitle)),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(vm.error!),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: vm.load, child: Text(l10n.commonRetry)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: vm.load,
                  child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
                  children: [
                    Container(
                      margin: const EdgeInsets.only(bottom: 16),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      decoration: BoxDecoration(color: AppColors.accentTint(context), borderRadius: BorderRadius.circular(12)),
                      child: Row(
                        children: [
                          const Icon(Icons.person_rounded, size: 18, color: Color(0xFFE65100)),
                          const SizedBox(width: 8),
                          Text(l10n.deathEntryMemberLabel, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              widget.memberName,
                              style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFFBF360C)),
                              overflow: TextOverflow.ellipsis,
                              textAlign: TextAlign.right,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (vm.entries.isNotEmpty) ...[
                      Text(l10n.deathEntryPreviousReports, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context))),
                      const SizedBox(height: 10),
                      ...vm.entries.map((e) => _DeathEntryTile(item: e)),
                      const SizedBox(height: 20),
                    ],
                    if (canSubmit) ...[
                      FormSectionCard(
                        icon: Icons.event_note_rounded,
                        title: l10n.deathEntryReportTitle,
                        children: [
                          TextFormField(
                            controller: _dobController,
                            readOnly: true,
                            onTap: _pickDate,
                            decoration: appFieldDecoration(l10n.deathEntryFieldDateOfDeath),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(controller: _causeController, decoration: appFieldDecoration(l10n.deathEntryFieldCause), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _informantNameController, decoration: appFieldDecoration(l10n.deathEntryFieldInformantName), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<String>(
                            initialValue: _informantRelation,
                            decoration: appFieldDecoration(l10n.deathEntryFieldInformantRelation),
                            items: _relations.map((r) => DropdownMenuItem(value: r, child: Text(_relationLabel(l10n, r)))).toList(),
                            onChanged: (v) => setState(() => _informantRelation = v),
                          ),
                          const SizedBox(height: 12),
                          Material(
                            color: Colors.transparent,
                            child: InkWell(
                              borderRadius: BorderRadius.circular(12),
                              onTap: _processingProof ? null : _pickProof,
                              child: Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(color: AppColors.surfaceAlt(context), borderRadius: BorderRadius.circular(12)),
                                child: Row(
                                  children: [
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(10),
                                      child: _processingProof
                                          ? Container(
                                              width: 44,
                                              height: 44,
                                              color: AppColors.border(context),
                                              child: const Center(child: SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2))),
                                            )
                                          : _proofFile != null
                                              ? GestureDetector(
                                                  onTap: () => showLocalPhotoPreview(context, _proofFile!),
                                                  child: Image.file(_proofFile!, width: 44, height: 44, fit: BoxFit.cover),
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
                                        _processingProof ? l10n.deathEntryProcessingPhoto : l10n.deathEntryProofPhotoLabel,
                                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13.5),
                                      ),
                                    ),
                                    if (!_processingProof)
                                      Icon(
                                        _proofFile != null ? Icons.check_circle_rounded : Icons.chevron_right_rounded,
                                        color: _proofFile != null ? Colors.green.shade600 : AppColors.textFaint(context),
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      SubmitButton(
                        loading: vm.submitting,
                        label: _processingProof ? l10n.deathEntryProcessingPhoto : l10n.deathEntrySubmitForReview,
                        onPressed: _processingProof ? null : _submit,
                      ),
                    ],
                  ],
                  ),
                ),
    );
  }
}

class _DeathEntryTile extends StatelessWidget {
  final DeathEntryItem item;

  const _DeathEntryTile({required this.item});

  Color get _statusColor {
    switch (item.status) {
      case 'Approved':
        return const Color(0xFF2FBF87);
      case 'Rejected':
        return Colors.red.shade400;
      default:
        return const Color(0xFFC8952C);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(l10n.deathEntryTileDateOfDeath(fdate(item.dateOfDeath)), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5))),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                decoration: BoxDecoration(color: _statusColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                child: Text(item.status, style: TextStyle(fontSize: 10.5, fontWeight: FontWeight.w700, color: _statusColor)),
              ),
            ],
          ),
          if (item.cause != null && item.cause!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(l10n.deathEntryTileCause(item.cause!), style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
          ],
          if (item.informantName != null && item.informantName!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              '${l10n.deathEntryTileInformant(item.informantName!)}${item.informantRelation != null ? ' (${item.informantRelation})' : ''}',
              style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
            ),
          ],
          if (item.status != 'Pending' && item.remarks != null && item.remarks!.isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(l10n.deathEntryTileRemarks(item.remarks!), style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context), fontStyle: FontStyle.italic)),
          ],
        ],
      ),
    );
  }
}
