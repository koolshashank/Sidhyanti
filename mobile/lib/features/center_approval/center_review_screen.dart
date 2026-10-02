import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/api/centers_api.dart';
import '../../core/models/center_review.dart';
import '../../core/theme/app_colors.dart';
import '../operations/widgets/form_section_card.dart';
import '../operations/widgets/form_styles.dart';
import 'center_review_viewmodel.dart';

/// Opened by a Branch/Area Manager from their Center Approval queue -- shows
/// everything the CM captured when creating the center, lets the reviewer
/// correct any of it (except the GPS coordinates, which were captured
/// live on-site and must stay exactly as recorded), then approve or reject
/// right here.
class CenterReviewScreen extends StatelessWidget {
  final int centerId;

  const CenterReviewScreen({super.key, required this.centerId});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => CenterReviewViewModel(context.read<CentersApi>(), centerId),
      child: const _CenterReviewView(),
    );
  }
}

class _CenterReviewView extends StatefulWidget {
  const _CenterReviewView();

  @override
  State<_CenterReviewView> createState() => _CenterReviewViewState();
}

class _CenterReviewViewState extends State<_CenterReviewView> {
  final _formKey = GlobalKey<FormState>();
  final _centerName = TextEditingController();
  final _centerPlace = TextEditingController();
  final _address = TextEditingController();
  final _pincode = TextEditingController();
  final _contactPerson = TextEditingController();
  final _contactNo = TextEditingController();
  final _meetingTimeController = TextEditingController();

  String? _meetingDay;
  int? _stateId;
  bool _initialized = false;

  static const _days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

  @override
  void dispose() {
    for (final c in [_centerName, _centerPlace, _address, _pincode, _contactPerson, _contactNo, _meetingTimeController]) {
      c.dispose();
    }
    super.dispose();
  }

  void _initFrom(CenterReviewDetail d) {
    if (_initialized) return;
    _initialized = true;
    _centerName.text = d.centerName;
    _centerPlace.text = d.centerPlace ?? '';
    _address.text = d.address ?? '';
    _pincode.text = d.pincode ?? '';
    _contactPerson.text = d.contactPerson ?? '';
    _contactNo.text = d.contactNo ?? '';
    _meetingTimeController.text = d.meetingTime ?? '';
    _meetingDay = d.meetingDay;
    _stateId = d.stateId;
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(context: context, initialTime: TimeOfDay.now());
    if (picked != null && mounted) {
      setState(() => _meetingTimeController.text = picked.format(context));
    }
  }

  Future<String?> _showRemarksDialog({required bool approve}) {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(approve ? 'Approve this center?' : 'Reject this center?'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(labelText: 'Remarks (optional)', border: OutlineInputBorder()),
          maxLines: 2,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: approve ? const Color(0xFF2FBF87) : const Color(0xFFDC3545)),
            onPressed: () => Navigator.of(dialogContext).pop(controller.text.trim()),
            child: Text(approve ? 'Approve' : 'Reject'),
          ),
        ],
      ),
    );
  }

  Future<void> _onApprove() async {
    if (!_formKey.currentState!.validate()) return;
    final remarks = await _showRemarksDialog(approve: true);
    if (remarks == null || !mounted) return;

    final vm = context.read<CenterReviewViewModel>();
    final saved = await vm.saveChanges(
      centerName: _centerName.text.trim(),
      address: _address.text.trim(),
      centerPlace: _centerPlace.text.trim(),
      stateId: _stateId,
      pincode: _pincode.text.trim(),
      meetingDay: _meetingDay,
      meetingTime: _meetingTimeController.text.trim(),
      contactPerson: _contactPerson.text.trim(),
      contactNo: _contactNo.text.trim(),
    );
    if (!mounted) return;
    if (!saved) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.saveError ?? 'Failed to save changes'), backgroundColor: Colors.red.shade600));
      return;
    }
    final decided = await vm.decide(approve: true, remarks: remarks.isEmpty ? null : remarks);
    if (!mounted) return;
    if (decided) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Center approved')));
      Navigator.of(context).pop(true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.decideError ?? 'Failed to approve'), backgroundColor: Colors.red.shade600));
    }
  }

  Future<void> _onReject() async {
    final remarks = await _showRemarksDialog(approve: false);
    if (remarks == null || !mounted) return;
    final vm = context.read<CenterReviewViewModel>();
    final decided = await vm.decide(approve: false, remarks: remarks.isEmpty ? null : remarks);
    if (!mounted) return;
    if (decided) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Center rejected')));
      Navigator.of(context).pop(true);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.decideError ?? 'Failed to reject'), backgroundColor: Colors.red.shade600));
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<CenterReviewViewModel>();
    final d = vm.detail;
    if (d != null) _initFrom(d);
    final busy = vm.saving || vm.deciding;

    return Scaffold(
      appBar: AppBar(title: const Text('Review Center')),
      body: vm.loading
          ? const Center(child: CircularProgressIndicator())
          : vm.loadError != null || d == null
              ? Center(child: Text(vm.loadError ?? 'Center not found'))
              : d.approvalStatus != 'Pending'
                  ? Center(child: Text('This center has already been ${d.approvalStatus.toLowerCase()}.'))
                  : Form(
                      key: _formKey,
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
                        children: [
                          FormSectionCard(
                            icon: Icons.badge_rounded,
                            title: 'Submitted By',
                            children: [
                              _readonly('Center Code', d.centerCode),
                              const SizedBox(height: 12),
                              _readonly('Branch Name', d.branchName ?? '-'),
                              const SizedBox(height: 12),
                              _readonly('Managed By', d.managedByName ?? '-'),
                            ],
                          ),
                          FormSectionCard(
                            icon: Icons.storefront_rounded,
                            title: 'Center Details',
                            children: [
                              TextFormField(
                                controller: _centerName,
                                decoration: appFieldDecoration('Center Name *'),
                                validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                              ),
                              const SizedBox(height: 12),
                              TextFormField(controller: _centerPlace, decoration: appFieldDecoration('Center Place')),
                            ],
                          ),
                          FormSectionCard(
                            icon: Icons.location_on_rounded,
                            title: 'Location & Address',
                            children: [
                              _readonly(
                                'GPS Coordinates (locked, captured on-site)',
                                d.latitude != null && d.longitude != null
                                    ? '${d.latitude!.toStringAsFixed(5)}, ${d.longitude!.toStringAsFixed(5)}'
                                    : 'Not captured',
                              ),
                              const SizedBox(height: 12),
                              TextFormField(controller: _address, decoration: appFieldDecoration('Address'), maxLines: 2),
                              const SizedBox(height: 12),
                              DropdownButtonFormField<int>(
                                initialValue: _stateId,
                                decoration: appFieldDecoration('State'),
                                isExpanded: true,
                                items: vm.states.map((s) => DropdownMenuItem(value: s.id, child: Text(s.name, overflow: TextOverflow.ellipsis))).toList(),
                                onChanged: (v) => setState(() => _stateId = v),
                              ),
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: _pincode,
                                decoration: appFieldDecoration('Pincode'),
                                keyboardType: TextInputType.number,
                              ),
                            ],
                          ),
                          FormSectionCard(
                            icon: Icons.calendar_month_rounded,
                            title: 'Meeting Schedule',
                            children: [
                              DropdownButtonFormField<String>(
                                initialValue: _meetingDay,
                                decoration: appFieldDecoration('Meeting Day'),
                                items: _days.map((d) => DropdownMenuItem(value: d, child: Text(d))).toList(),
                                onChanged: (v) => setState(() => _meetingDay = v),
                              ),
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: _meetingTimeController,
                                readOnly: true,
                                onTap: _pickTime,
                                decoration: appFieldDecoration('Meeting Time'),
                              ),
                            ],
                          ),
                          FormSectionCard(
                            icon: Icons.person_rounded,
                            title: 'Contact Person',
                            children: [
                              TextFormField(controller: _contactPerson, decoration: appFieldDecoration('Contact Person')),
                              const SizedBox(height: 12),
                              TextFormField(
                                controller: _contactNo,
                                decoration: appFieldDecoration('Contact Number'),
                                keyboardType: TextInputType.phone,
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
                                  onPressed: busy ? null : _onReject,
                                  child: const Text('Reject'),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: FilledButton(
                                  style: FilledButton.styleFrom(
                                    backgroundColor: const Color(0xFF2FBF87),
                                    padding: const EdgeInsets.symmetric(vertical: 14),
                                  ),
                                  onPressed: busy ? null : _onApprove,
                                  child: busy
                                      ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                      : const Text('Save & Approve'),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
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
