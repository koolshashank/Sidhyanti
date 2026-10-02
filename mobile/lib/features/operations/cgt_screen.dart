import 'dart:io';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../core/api/centers_api.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/geo_photo_stamp.dart';
import '../../core/utils/input_formatters.dart';
import 'cgt_viewmodel.dart';
import 'widgets/form_section_card.dart';
import 'widgets/local_photo_preview.dart';
import 'widgets/form_styles.dart';

class CgtScreen extends StatelessWidget {
  final int centerId;
  final String centerName;

  const CgtScreen({super.key, required this.centerId, required this.centerName});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => CgtViewModel(context.read<CentersApi>()),
      child: _CgtView(centerId: centerId, centerName: centerName),
    );
  }
}

class _CgtView extends StatefulWidget {
  final int centerId;
  final String centerName;

  const _CgtView({required this.centerId, required this.centerName});

  @override
  State<_CgtView> createState() => _CgtViewState();
}

class _CgtViewState extends State<_CgtView> {
  final _formKey = GlobalKey<FormState>();
  final _dateController = TextEditingController();
  final _remarks = TextEditingController();
  final _picker = ImagePicker();
  DateTime _cgtDate = DateTime.now();
  XFile? _photo;
  bool _processingPhoto = false;

  @override
  void initState() {
    super.initState();
    _dateController.text = '${_cgtDate.day}/${_cgtDate.month}/${_cgtDate.year}';
  }

  @override
  void dispose() {
    _dateController.dispose();
    _remarks.dispose();
    super.dispose();
  }

  Future<void> _pickPhoto(ImageSource source) async {
    final picked = await _picker.pickImage(source: source, imageQuality: 80);
    if (picked == null) return;
    if (source == ImageSource.camera) {
      if (mounted) setState(() => _processingPhoto = true);
      try {
        await stampLocationOnPhoto(File(picked.path));
      } finally {
        if (mounted) setState(() => _processingPhoto = false);
      }
    }
    if (mounted) setState(() => _photo = picked);
  }

  void _showPhotoSourceSheet() {
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
                _pickPhoto(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded, color: Color(0xFF6A4FC7)),
              title: const Text('Choose from Gallery'),
              onTap: () {
                Navigator.pop(context);
                _pickPhoto(ImageSource.gallery);
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _cgtDate,
      firstDate: DateTime.now().subtract(const Duration(days: 30)),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      setState(() {
        _cgtDate = picked;
        _dateController.text = '${picked.day}/${picked.month}/${picked.year}';
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_photo == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('CGT photo is required')));
      return;
    }
    final vm = context.read<CgtViewModel>();
    final ok = await vm.submit(
      centerId: widget.centerId,
      cgtDate: _cgtDate,
      remarks: _remarks.text.trim(),
      photo: _photo != null ? File(_photo!.path) : null,
    );
    if (ok && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('CGT recorded')));
      // Natural next step in the sourcing flow: start adding clients for this center.
      context.pushReplacement(
        '/client-sourcing/new-lead?center_id=${widget.centerId}&center_name=${Uri.encodeComponent(widget.centerName)}',
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<CgtViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('CGT')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(color: AppColors.accentTint(context), borderRadius: BorderRadius.circular(12)),
              child: Row(
                children: [
                  const Icon(Icons.storefront_rounded, size: 18, color: Color(0xFF6A4FC7)),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      widget.centerName,
                      style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF3A3D9F)),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ),
            FormSectionCard(
              icon: Icons.groups_2_rounded,
              title: 'Center Group Training',
              children: [
                Material(
                  color: AppColors.surface(context),
                  borderRadius: BorderRadius.circular(16),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(16),
                    onTap: _processingPhoto ? null : _showPhotoSourceSheet,
                    child: Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: _photo != null ? const Color(0xFF6A4FC7) : AppColors.border(context)),
                      ),
                      child: Row(
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: _processingPhoto
                                ? Container(
                                    width: 52,
                                    height: 52,
                                    color: AppColors.surfaceAlt(context),
                                    child: const Center(child: SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2.2))),
                                  )
                                : _photo != null
                                    ? GestureDetector(
                                        onTap: () => showLocalPhotoPreview(context, File(_photo!.path)),
                                        child: Image.file(File(_photo!.path), width: 52, height: 52, fit: BoxFit.cover),
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
                            child: Text(
                              _processingPhoto ? 'Processing photo...' : 'Group Photo *',
                              style: TextStyle(fontWeight: FontWeight.w600, color: AppColors.textPrimary(context)),
                            ),
                          ),
                          if (!_processingPhoto)
                            Icon(
                              _photo != null ? Icons.check_circle_rounded : Icons.chevron_right_rounded,
                              color: _photo != null ? Colors.green.shade600 : AppColors.textFaint(context),
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
                TextFormField(
                  controller: _dateController,
                  readOnly: true,
                  onTap: _pickDate,
                  decoration: appFieldDecoration('CGT Date *'),
                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _remarks,
                  decoration: appFieldDecoration('Remarks (Optional)'),
                  maxLines: 3,
                  inputFormatters: [upperCaseInputFormatter],
                ),
              ],
            ),
            if (vm.submitError != null) ...[
              Text(vm.submitError!, style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 10),
            ],
            SubmitButton(
              loading: vm.submitting,
              label: _processingPhoto ? 'Processing photo...' : 'Complete CGT',
              onPressed: _processingPhoto ? null : _submit,
            ),
          ],
        ),
      ),
    );
  }
}
