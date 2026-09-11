import 'dart:io';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/members_api.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/geo_photo_stamp.dart';
import 'widgets/form_styles.dart';
import 'widgets/local_photo_preview.dart';

const List<String> kycDocumentTypes = [
  'Client Photograph',
  'Aadhar Front Image',
  'Aadhar Back Image',
  'PAN Card Image',
  'Voter ID Front Image',
  'Voter ID Back Image',
  'House Photo',
];

class KycCaptureScreen extends StatefulWidget {
  final String flow; // 'member' or 'coborrower'
  final int memberId;
  final String memberName;
  final int coBorrowerId;
  final String coBorrowerName;

  const KycCaptureScreen({
    super.key,
    required this.flow,
    required this.memberId,
    required this.memberName,
    required this.coBorrowerId,
    required this.coBorrowerName,
  });

  @override
  State<KycCaptureScreen> createState() => _KycCaptureScreenState();
}

class _KycCaptureScreenState extends State<KycCaptureScreen> {
  final _picker = ImagePicker();
  final Map<String, XFile?> _captures = {for (final t in kycDocumentTypes) t: null};

  bool _loadingDocTypes = true;
  String? _loadError;
  Map<String, int> _docTypeIds = {};
  bool _uploading = false;
  String? _uploadError;

  bool get _isMemberFlow => widget.flow == 'member';

  @override
  void initState() {
    super.initState();
    _loadDocTypes();
  }

  Future<void> _loadDocTypes() async {
    setState(() {
      _loadingDocTypes = true;
      _loadError = null;
    });
    try {
      final lookups = await context.read<MembersApi>().lookups();
      _docTypeIds = {for (final t in lookups.kycDocTypes) t.name: t.id};
    } on ApiException catch (e) {
      _loadError = e.message;
    } finally {
      if (mounted) setState(() => _loadingDocTypes = false);
    }
  }

  Future<void> _capture(String docType, ImageSource source) async {
    final picked = await _picker.pickImage(source: source, imageQuality: 80);
    if (picked == null) return;
    if (source == ImageSource.camera) {
      await stampLocationOnPhoto(File(picked.path));
    }
    if (mounted) setState(() => _captures[docType] = picked);
  }

  void _showSourceSheet(String docType) {
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
              leading: const Icon(Icons.photo_camera_rounded, color: Color(0xFF6A4FC7)),
              title: const Text('Take Photo'),
              onTap: () {
                Navigator.pop(context);
                _capture(docType, ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded, color: Color(0xFF6A4FC7)),
              title: const Text('Choose from Gallery'),
              onTap: () {
                Navigator.pop(context);
                _capture(docType, ImageSource.gallery);
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    final toUpload = _captures.entries.where((e) => e.value != null).toList();
    if (toUpload.isNotEmpty) {
      setState(() {
        _uploading = true;
        _uploadError = null;
      });
      try {
        final membersApi = context.read<MembersApi>();
        for (final entry in toUpload) {
          final docTypeId = _docTypeIds[entry.key];
          if (docTypeId == null) continue; // shouldn't happen -- every label in kycDocumentTypes has a matching row
          await membersApi.uploadKycDocument(
            memberId: widget.memberId,
            docTypeId: docTypeId,
            file: File(entry.value!.path),
            coBorrowerId: _isMemberFlow ? null : widget.coBorrowerId,
          );
        }
      } on ApiException catch (e) {
        if (mounted) setState(() => _uploadError = e.message);
        return;
      } finally {
        if (mounted) setState(() => _uploading = false);
      }
    }
    if (!mounted) return;
    final message = _isMemberFlow ? 'Member KYC documents uploaded' : 'Co-Borrower KYC documents uploaded';
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$message (${toUpload.length}/${kycDocumentTypes.length} captured)')),
    );
    if (_isMemberFlow) {
      context.pushReplacement(
        '/client-sourcing/kyc'
        '?flow=coborrower'
        '&member_id=${widget.memberId}'
        '&member_name=${Uri.encodeComponent(widget.memberName)}'
        '&coborrower_id=${widget.coBorrowerId}'
        '&coborrower_name=${Uri.encodeComponent(widget.coBorrowerName)}',
      );
    } else {
      context.pushReplacement(
        '/client-sourcing/loan-proposal'
        '?member_id=${widget.memberId}'
        '&coborrower_name=${Uri.encodeComponent(widget.coBorrowerName)}',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final subjectName = _isMemberFlow ? widget.memberName : widget.coBorrowerName;
    final title = _isMemberFlow ? 'Member KYC Documents' : 'Co-Borrower KYC Documents';

    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: [
          Container(
            margin: const EdgeInsets.only(bottom: 16),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(color: AppColors.accentTint(context), borderRadius: BorderRadius.circular(12)),
            child: Row(
              children: [
                Icon(_isMemberFlow ? Icons.person_rounded : Icons.people_alt_rounded, size: 18, color: const Color(0xFF6A4FC7)),
                const SizedBox(width: 8),
                Text(_isMemberFlow ? 'Member' : 'Co-Borrower', style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    subjectName,
                    style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF3A3D9F)),
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.right,
                  ),
                ),
              ],
            ),
          ),
          if (_loadError != null)
            Container(
              margin: const EdgeInsets.only(bottom: 16),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(color: AppColors.warningTint(context), borderRadius: BorderRadius.circular(12)),
              child: Row(
                children: [
                  Icon(Icons.error_outline_rounded, size: 18, color: Colors.orange.shade800),
                  const SizedBox(width: 8),
                  Expanded(child: Text(_loadError!, style: TextStyle(fontSize: 12, color: Colors.orange.shade900))),
                  TextButton(onPressed: _loadDocTypes, child: const Text('Retry')),
                ],
              ),
            ),
          if (_loadingDocTypes)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 40),
              child: Center(child: CircularProgressIndicator()),
            )
          else ...[
            for (final docType in kycDocumentTypes) ...[
              _DocumentTile(
                label: docType,
                file: _captures[docType],
                onTap: () => _showSourceSheet(docType),
              ),
              const SizedBox(height: 12),
            ],
            if (_uploadError != null) ...[
              Text(_uploadError!, style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 10),
            ],
            const SizedBox(height: 8),
            SubmitButton(loading: _uploading, label: 'CONTINUE', onPressed: _submit),
          ],
        ],
      ),
    );
  }
}

class _DocumentTile extends StatelessWidget {
  final String label;
  final XFile? file;
  final VoidCallback onTap;

  const _DocumentTile({required this.label, required this.file, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final captured = file != null;
    return Material(
      color: AppColors.surface(context),
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: captured ? const Color(0xFF6A4FC7) : AppColors.border(context)),
          ),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: captured
                    ? GestureDetector(
                        onTap: () => showLocalPhotoPreview(context, File(file!.path)),
                        child: Image.file(File(file!.path), width: 52, height: 52, fit: BoxFit.cover),
                      )
                    : Container(
                        width: 52,
                        height: 52,
                        color: AppColors.surfaceAlt(context),
                        child: const Icon(Icons.add_a_photo_rounded, color: Color(0xFF6A4FC7), size: 22),
                      ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(label, style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.textPrimary(context))),
              ),
              Icon(
                captured ? Icons.check_circle_rounded : Icons.chevron_right_rounded,
                color: captured ? Colors.green.shade600 : AppColors.textFaint(context),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
