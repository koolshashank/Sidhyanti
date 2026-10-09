import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/draft_store.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/input_formatters.dart';
import 'client_sourcing_viewmodel.dart';
import 'widgets/form_section_card.dart';
import 'widgets/form_styles.dart';

class ClientSourcingScreen extends StatelessWidget {
  final int centerId;
  final String centerName;

  const ClientSourcingScreen({super.key, required this.centerId, required this.centerName});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => ClientSourcingViewModel(context.read<MembersApi>()),
      child: _ClientSourcingView(centerId: centerId, centerName: centerName),
    );
  }
}

class _ClientSourcingView extends StatefulWidget {
  final int centerId;
  final String centerName;

  const _ClientSourcingView({required this.centerId, required this.centerName});

  @override
  State<_ClientSourcingView> createState() => _ClientSourcingViewState();
}

class _ClientSourcingViewState extends State<_ClientSourcingView> {
  final _formKey = GlobalKey<FormState>();

  final _firstName = TextEditingController();
  final _middleName = TextEditingController();
  final _lastName = TextEditingController();
  final _fatherName = TextEditingController();
  final _motherName = TextEditingController();
  final _dobController = TextEditingController();
  final _mobile = TextEditingController();
  final _noOfDependents = TextEditingController();
  final _address = TextEditingController();
  final _pincode = TextEditingController();
  final _landmark = TextEditingController();
  final _aadhar = TextEditingController();
  final _pan = TextEditingController();
  final _voterId = TextEditingController();

  String? _maritalStatus;
  DateTime? _dob;
  int? _educationId;
  String _gender = 'Female';
  int? _occupationId;
  int? _religionId;
  int? _casteId;
  int? _stateId;
  int? _districtId;
  int? _houseTypeId;
  int? _leadSourceId;

  static const _maritalStatuses = ['Married', 'Unmarried', 'Widowed', 'Divorced'];

  String get _draftKey => 'client_sourcing:${widget.centerId}';
  Timer? _draftDebounce;

  @override
  void initState() {
    super.initState();
    for (final c in [
      _firstName, _middleName, _lastName, _fatherName, _motherName,
      _mobile, _noOfDependents, _address, _pincode, _landmark, _aadhar, _pan, _voterId,
    ]) {
      c.addListener(_scheduleDraftSave);
    }
    WidgetsBinding.instance.addPostFrameCallback((_) => _checkForDraft());
  }

  @override
  void dispose() {
    _draftDebounce?.cancel();
    for (final c in [
      _firstName, _middleName, _lastName, _fatherName, _motherName, _dobController,
      _mobile, _noOfDependents, _address, _pincode, _landmark, _aadhar, _pan, _voterId,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  void _scheduleDraftSave() {
    _draftDebounce?.cancel();
    _draftDebounce = Timer(const Duration(milliseconds: 600), _saveDraft);
  }

  void _saveDraft() {
    DraftStore.save(_draftKey, {
      'first_name': _firstName.text,
      'middle_name': _middleName.text,
      'last_name': _lastName.text,
      'father_name': _fatherName.text,
      'mother_name': _motherName.text,
      'marital_status': _maritalStatus,
      'dob': _dob?.toIso8601String(),
      'mobile': _mobile.text,
      'education_id': _educationId,
      'gender': _gender,
      'no_of_dependents': _noOfDependents.text,
      'occupation_id': _occupationId,
      'religion_id': _religionId,
      'caste_id': _casteId,
      'address': _address.text,
      'state_id': _stateId,
      'district_id': _districtId,
      'pincode': _pincode.text,
      'landmark': _landmark.text,
      'house_type_id': _houseTypeId,
      'aadhar': _aadhar.text,
      'pan': _pan.text,
      'voter_id': _voterId.text,
      'lead_source_id': _leadSourceId,
    });
  }

  Future<void> _checkForDraft() async {
    final draft = await DraftStore.load(_draftKey);
    if (draft == null || !mounted) return;
    final (data, savedAt) = draft;
    final resume = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Resume draft?'),
        content: Text('You have an unsaved lead for this center from ${fdate(savedAt)}. Resume it, or start fresh?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: const Text('Start Fresh')),
          FilledButton(onPressed: () => Navigator.of(dialogContext).pop(true), child: const Text('Resume')),
        ],
      ),
    );
    if (resume == true) {
      await _restoreDraft(data);
    } else {
      await DraftStore.clear(_draftKey);
    }
  }

  Future<void> _restoreDraft(Map<String, dynamic> data) async {
    final vm = context.read<ClientSourcingViewModel>();
    final stateId = data['state_id'] as int?;
    if (stateId != null) {
      await vm.loadDistricts(stateId);
    }
    if (!mounted) return;
    setState(() {
      _firstName.text = data['first_name'] as String? ?? '';
      _middleName.text = data['middle_name'] as String? ?? '';
      _lastName.text = data['last_name'] as String? ?? '';
      _fatherName.text = data['father_name'] as String? ?? '';
      _motherName.text = data['mother_name'] as String? ?? '';
      _maritalStatus = data['marital_status'] as String?;
      _dob = data['dob'] != null ? DateTime.tryParse(data['dob'] as String) : null;
      if (_dob != null) _dobController.text = '${_dob!.day}/${_dob!.month}/${_dob!.year}';
      _mobile.text = data['mobile'] as String? ?? '';
      _educationId = data['education_id'] as int?;
      _gender = data['gender'] as String? ?? 'Female';
      _noOfDependents.text = data['no_of_dependents'] as String? ?? '';
      _occupationId = data['occupation_id'] as int?;
      _religionId = data['religion_id'] as int?;
      _casteId = data['caste_id'] as int?;
      _address.text = data['address'] as String? ?? '';
      _stateId = stateId;
      _districtId = data['district_id'] as int?;
      _pincode.text = data['pincode'] as String? ?? '';
      _landmark.text = data['landmark'] as String? ?? '';
      _houseTypeId = data['house_type_id'] as int?;
      _aadhar.text = data['aadhar'] as String? ?? '';
      _pan.text = data['pan'] as String? ?? '';
      _voterId.text = data['voter_id'] as String? ?? '';
      _leadSourceId = data['lead_source_id'] as int?;
    });
  }

  Future<void> _pickDob() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime(1995),
      firstDate: DateTime(1930),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      setState(() {
        _dob = picked;
        _dobController.text = '${picked.day}/${picked.month}/${picked.year}';
      });
      _scheduleDraftSave();
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final vm = context.read<ClientSourcingViewModel>();
    final result = await vm.submit(
      firstName: _firstName.text.trim(),
      middleName: _middleName.text.trim(),
      lastName: _lastName.text.trim(),
      fatherName: _fatherName.text.trim(),
      motherName: _motherName.text.trim(),
      maritalStatus: _maritalStatus,
      dob: _dob,
      mobile: _mobile.text.trim(),
      educationId: _educationId,
      gender: _gender,
      noOfDependents: int.tryParse(_noOfDependents.text.trim()),
      occupationId: _occupationId,
      religionId: _religionId,
      casteId: _casteId,
      address: _address.text.trim(),
      stateId: _stateId,
      districtId: _districtId,
      pincode: _pincode.text.trim(),
      landmark: _landmark.text.trim(),
      houseTypeId: _houseTypeId,
      aadharNo: _aadhar.text.trim(),
      panNo: _pan.text.trim(),
      voterId: _voterId.text.trim(),
      centerId: widget.centerId,
      leadSourceId: _leadSourceId,
    );
    if (!mounted) return;
    if (result != null) {
      await DraftStore.clear(_draftKey);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${result.fullName} added as a new lead')));
      context.pushReplacement(
        '/client-sourcing/coborrower?member_id=${result.memberId}&member_name=${Uri.encodeComponent(result.fullName)}',
      );
    } else if (vm.submitError != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(vm.submitError!), backgroundColor: Colors.red.shade600));
    }
  }

  String? _requiredValidator(String? v) => (v == null || v.trim().isEmpty) ? 'Required' : null;

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<ClientSourcingViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('Client Sourcing')),
      body: vm.lookupsLoading
          ? const Center(child: CircularProgressIndicator())
          : vm.lookupsError != null
              ? Center(child: Text(vm.lookupsError!))
              : Form(
                  key: _formKey,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
                    children: [
                      FormSectionCard(
                        icon: Icons.campaign_rounded,
                        title: 'Sourcing Details',
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                            decoration: BoxDecoration(
                              color: AppColors.accentTint(context),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.storefront_rounded, size: 18, color: Color(0xFF6A4FC7)),
                                const SizedBox(width: 8),
                                Text('Center', style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
                                const SizedBox(width: 6),
                                Expanded(
                                  child: Text(
                                    widget.centerName,
                                    style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF3A3D9F)),
                                    overflow: TextOverflow.ellipsis,
                                    textAlign: TextAlign.right,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _leadSourceId,
                            decoration: appFieldDecoration('Lead Source'),
                            items: vm.leadSources.map((s) => DropdownMenuItem<int>(value: s.id, child: Text(s.name))).toList(),
                            onChanged: (v) {
                              setState(() => _leadSourceId = v);
                              _scheduleDraftSave();
                            },
                          ),
                        ],
                      ),
                      FormSectionCard(
                        icon: Icons.badge_rounded,
                        title: 'Applicant Details',
                        children: [
                          TextFormField(
                            controller: _firstName,
                            decoration: appFieldDecoration('First Name *'),
                            validator: _requiredValidator,
                            inputFormatters: [upperCaseInputFormatter],
                          ),
                          const SizedBox(height: 12),
                          TextFormField(controller: _middleName, decoration: appFieldDecoration('Middle Name'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _lastName, decoration: appFieldDecoration('Last Name'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _fatherName, decoration: appFieldDecoration('Father Name'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _motherName, decoration: appFieldDecoration('Mother Name'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<String>(
                            initialValue: _maritalStatus,
                            decoration: appFieldDecoration('Marital Status'),
                            items: _maritalStatuses.map((m) => DropdownMenuItem(value: m, child: Text(m))).toList(),
                            onChanged: (v) {
                              setState(() => _maritalStatus = v);
                              _scheduleDraftSave();
                            },
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _dobController,
                            readOnly: true,
                            onTap: _pickDob,
                            decoration: appFieldDecoration('Date of Birth'),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _mobile,
                            decoration: appFieldDecoration('Mobile No *'),
                            keyboardType: TextInputType.phone,
                            validator: _requiredValidator,
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _educationId,
                            decoration: appFieldDecoration('Education'),
                            items: vm.educations.map((e) => DropdownMenuItem(value: e.id, child: Text(e.name))).toList(),
                            onChanged: (v) {
                              setState(() => _educationId = v);
                              _scheduleDraftSave();
                            },
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<String>(
                            initialValue: _gender,
                            decoration: appFieldDecoration('Gender'),
                            items: const ['Female', 'Male', 'Other'].map((g) => DropdownMenuItem(value: g, child: Text(g))).toList(),
                            onChanged: (v) {
                              setState(() => _gender = v ?? 'Female');
                              _scheduleDraftSave();
                            },
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _noOfDependents,
                            decoration: appFieldDecoration('No. of Dependents'),
                            keyboardType: TextInputType.number,
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _occupationId,
                            decoration: appFieldDecoration('Occupation'),
                            isExpanded: true,
                            items: vm.occupations
                                .map((o) => DropdownMenuItem(value: o.id, child: Text(o.name, overflow: TextOverflow.ellipsis)))
                                .toList(),
                            onChanged: (v) {
                              setState(() => _occupationId = v);
                              _scheduleDraftSave();
                            },
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _religionId,
                            decoration: appFieldDecoration('Religion'),
                            items: vm.religions.map((r) => DropdownMenuItem(value: r.id, child: Text(r.name))).toList(),
                            onChanged: (v) {
                              setState(() => _religionId = v);
                              _scheduleDraftSave();
                            },
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _casteId,
                            decoration: appFieldDecoration('Caste'),
                            items: vm.castes.map((c) => DropdownMenuItem(value: c.id, child: Text(c.name))).toList(),
                            onChanged: (v) {
                              setState(() => _casteId = v);
                              _scheduleDraftSave();
                            },
                          ),
                        ],
                      ),
                      FormSectionCard(
                        icon: Icons.location_on_rounded,
                        title: 'Address',
                        children: [
                          TextFormField(controller: _address, decoration: appFieldDecoration('Address'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _stateId,
                            decoration: appFieldDecoration('State'),
                            isExpanded: true,
                            items: vm.states
                                .map((s) => DropdownMenuItem(value: s.id, child: Text(s.name, overflow: TextOverflow.ellipsis)))
                                .toList(),
                            onChanged: (v) {
                              setState(() {
                                _stateId = v;
                                _districtId = null;
                              });
                              _scheduleDraftSave();
                              if (v != null) vm.loadDistricts(v);
                            },
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _districtId,
                            decoration: appFieldDecoration(vm.districtsLoading ? 'Loading...' : 'District'),
                            isExpanded: true,
                            items: vm.districts
                                .map((d) => DropdownMenuItem(value: d.id, child: Text(d.name, overflow: TextOverflow.ellipsis)))
                                .toList(),
                            onChanged: vm.districts.isEmpty ? null : (v) => setState(() => _districtId = v),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _pincode,
                            decoration: appFieldDecoration('Pincode'),
                            keyboardType: TextInputType.number,
                          ),
                          const SizedBox(height: 12),
                          TextFormField(controller: _landmark, decoration: appFieldDecoration('Landmark (Optional)'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _houseTypeId,
                            decoration: appFieldDecoration('House Type'),
                            items: vm.houseTypes.map((h) => DropdownMenuItem(value: h.id, child: Text(h.name))).toList(),
                            onChanged: (v) {
                              setState(() => _houseTypeId = v);
                              _scheduleDraftSave();
                            },
                          ),
                        ],
                      ),
                      FormSectionCard(
                        icon: Icons.assignment_ind_rounded,
                        title: 'KYC',
                        children: [
                          TextFormField(controller: _aadhar, decoration: appFieldDecoration('Aadhar No.'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _pan, decoration: appFieldDecoration('PAN Card No.'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _voterId, decoration: appFieldDecoration('Voter Id No.'), inputFormatters: [upperCaseInputFormatter]),
                        ],
                      ),
                      const SizedBox(height: 12),
                      SubmitButton(loading: vm.submitting, label: 'CONTINUE', onPressed: _submit),
                    ],
                  ),
                ),
    );
  }
}
