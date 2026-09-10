import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/members_api.dart';
import '../../core/theme/app_colors.dart';
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

  @override
  void dispose() {
    for (final c in [
      _firstName, _middleName, _lastName, _fatherName, _motherName, _dobController,
      _mobile, _noOfDependents, _address, _pincode, _landmark, _aadhar, _pan, _voterId,
    ]) {
      c.dispose();
    }
    super.dispose();
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
                            onChanged: (v) => setState(() => _leadSourceId = v),
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
                          ),
                          const SizedBox(height: 12),
                          TextFormField(controller: _middleName, decoration: appFieldDecoration('Middle Name')),
                          const SizedBox(height: 12),
                          TextFormField(controller: _lastName, decoration: appFieldDecoration('Last Name')),
                          const SizedBox(height: 12),
                          TextFormField(controller: _fatherName, decoration: appFieldDecoration('Father Name')),
                          const SizedBox(height: 12),
                          TextFormField(controller: _motherName, decoration: appFieldDecoration('Mother Name')),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<String>(
                            initialValue: _maritalStatus,
                            decoration: appFieldDecoration('Marital Status'),
                            items: _maritalStatuses.map((m) => DropdownMenuItem(value: m, child: Text(m))).toList(),
                            onChanged: (v) => setState(() => _maritalStatus = v),
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
                            onChanged: (v) => setState(() => _educationId = v),
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<String>(
                            initialValue: _gender,
                            decoration: appFieldDecoration('Gender'),
                            items: const ['Female', 'Male', 'Other'].map((g) => DropdownMenuItem(value: g, child: Text(g))).toList(),
                            onChanged: (v) => setState(() => _gender = v ?? 'Female'),
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
                            onChanged: (v) => setState(() => _occupationId = v),
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _religionId,
                            decoration: appFieldDecoration('Religion'),
                            items: vm.religions.map((r) => DropdownMenuItem(value: r.id, child: Text(r.name))).toList(),
                            onChanged: (v) => setState(() => _religionId = v),
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _casteId,
                            decoration: appFieldDecoration('Caste'),
                            items: vm.castes.map((c) => DropdownMenuItem(value: c.id, child: Text(c.name))).toList(),
                            onChanged: (v) => setState(() => _casteId = v),
                          ),
                        ],
                      ),
                      FormSectionCard(
                        icon: Icons.location_on_rounded,
                        title: 'Address',
                        children: [
                          TextFormField(controller: _address, decoration: appFieldDecoration('Address')),
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
                          TextFormField(controller: _landmark, decoration: appFieldDecoration('Landmark (Optional)')),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _houseTypeId,
                            decoration: appFieldDecoration('House Type'),
                            items: vm.houseTypes.map((h) => DropdownMenuItem(value: h.id, child: Text(h.name))).toList(),
                            onChanged: (v) => setState(() => _houseTypeId = v),
                          ),
                        ],
                      ),
                      FormSectionCard(
                        icon: Icons.assignment_ind_rounded,
                        title: 'KYC',
                        children: [
                          TextFormField(controller: _aadhar, decoration: appFieldDecoration('Aadhar No.')),
                          const SizedBox(height: 12),
                          TextFormField(controller: _pan, decoration: appFieldDecoration('PAN Card No.')),
                          const SizedBox(height: 12),
                          TextFormField(controller: _voterId, decoration: appFieldDecoration('Voter Id No.')),
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
