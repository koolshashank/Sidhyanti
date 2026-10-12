import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/coborrowers_api.dart';
import '../../core/api/members_api.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/input_formatters.dart';
import 'coborrower_viewmodel.dart';
import 'widgets/form_section_card.dart';
import 'widgets/form_styles.dart';

class CoBorrowerScreen extends StatelessWidget {
  final int memberId;
  final String memberName;

  const CoBorrowerScreen({super.key, required this.memberId, required this.memberName});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => CoBorrowerViewModel(context.read<MembersApi>(), context.read<CoborrowersApi>()),
      child: _CoBorrowerView(memberId: memberId, memberName: memberName),
    );
  }
}

class _CoBorrowerView extends StatefulWidget {
  final int memberId;
  final String memberName;

  const _CoBorrowerView({required this.memberId, required this.memberName});

  @override
  State<_CoBorrowerView> createState() => _CoBorrowerViewState();
}

class _CoBorrowerViewState extends State<_CoBorrowerView> {
  final _formKey = GlobalKey<FormState>();

  final _firstName = TextEditingController();
  final _middleName = TextEditingController();
  final _lastName = TextEditingController();
  final _motherName = TextEditingController();
  final _dobController = TextEditingController();
  final _mobile = TextEditingController();
  final _address = TextEditingController();
  final _villageCity = TextEditingController();
  final _pincode = TextEditingController();
  final _aadhar = TextEditingController();
  final _pan = TextEditingController();
  final _voterId = TextEditingController();
  final _rationCardNo = TextEditingController();
  final _dlNo = TextEditingController();
  final _electricityBillNo = TextEditingController();

  String? _relationToMember;
  String? _maritalStatus;
  DateTime? _dob;
  int? _educationId;
  String _gender = 'Female';
  int? _occupationId;
  int? _religionId;
  int? _casteId;
  int? _houseTypeId;

  static const _relations = ['Husband', 'Wife', 'Father', 'Mother', 'Son', 'Daughter', 'Father-in-law', 'Mother-in-law'];
  static const _maritalStatuses = ['Married', 'Unmarried', 'Widowed', 'Divorced'];

  @override
  void dispose() {
    for (final c in [
      _firstName, _middleName, _lastName, _motherName, _dobController, _mobile,
      _address, _villageCity, _pincode, _aadhar, _pan, _voterId, _rationCardNo, _dlNo, _electricityBillNo,
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
    final vm = context.read<CoBorrowerViewModel>();
    final result = await vm.submit(
      memberId: widget.memberId,
      firstName: _firstName.text.trim(),
      middleName: _middleName.text.trim(),
      lastName: _lastName.text.trim(),
      relationToMember: _relationToMember,
      motherName: _motherName.text.trim(),
      maritalStatus: _maritalStatus,
      dob: _dob,
      mobile: _mobile.text.trim(),
      educationId: _educationId,
      gender: _gender,
      occupationId: _occupationId,
      religionId: _religionId,
      casteId: _casteId,
      houseTypeId: _houseTypeId,
      address: _address.text.trim(),
      villageCity: _villageCity.text.trim(),
      pincode: _pincode.text.trim(),
      aadharNo: _aadhar.text.trim(),
      panNo: _pan.text.trim(),
      voterId: _voterId.text.trim(),
      rationCardNo: _rationCardNo.text.trim(),
      dlNo: _dlNo.text.trim(),
      electricityBillNo: _electricityBillNo.text.trim(),
    );
    if (!mounted) return;
    if (result != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Co-Borrower added successfully')),
      );
      final coBorrowerName = [_firstName.text.trim(), _middleName.text.trim(), _lastName.text.trim()]
          .where((s) => s.isNotEmpty)
          .join(' ');
      context.pushReplacement(
        '/client-sourcing/kyc'
        '?flow=member'
        '&member_id=${result.memberId}'
        '&member_name=${Uri.encodeComponent(widget.memberName)}'
        '&coborrower_id=${result.coBorrowerId}'
        '&coborrower_name=${Uri.encodeComponent(coBorrowerName)}',
      );
    } else if (vm.submitError != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(vm.submitError!), backgroundColor: Colors.red.shade600),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<CoBorrowerViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('Co-Borrower Details')),
      body: vm.lookupsLoading
          ? const Center(child: CircularProgressIndicator())
          : vm.lookupsError != null
              ? Center(child: Text(vm.lookupsError!))
              : Form(
                  key: _formKey,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
                    children: [
                      Container(
                        margin: const EdgeInsets.only(bottom: 16),
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                        decoration: BoxDecoration(
                          color: AppColors.accentTint(context),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.person_rounded, size: 18, color: Color(0xFFE65100)),
                            const SizedBox(width: 8),
                            Text('Member', style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
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
                      FormSectionCard(
                        icon: Icons.badge_rounded,
                        title: 'Co-Borrower Details',
                        children: [
                          TextFormField(controller: _firstName, decoration: appFieldDecoration('First Name'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _middleName, decoration: appFieldDecoration('Middle Name'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _lastName, decoration: appFieldDecoration('Last Name'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<String>(
                            initialValue: _relationToMember,
                            decoration: appFieldDecoration('Relation to Member'),
                            items: _relations.map((r) => DropdownMenuItem(value: r, child: Text(r))).toList(),
                            onChanged: (v) => setState(() => _relationToMember = v),
                          ),
                          const SizedBox(height: 12),
                          TextFormField(controller: _motherName, decoration: appFieldDecoration('Mother Name'), inputFormatters: [upperCaseInputFormatter]),
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
                            decoration: appFieldDecoration('Mobile No'),
                            keyboardType: TextInputType.phone,
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
                        icon: Icons.location_on_rounded,
                        title: 'Address',
                        children: [
                          TextFormField(controller: _address, decoration: appFieldDecoration('Address'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _villageCity, decoration: appFieldDecoration('Village/City'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _pincode,
                            decoration: appFieldDecoration('Pincode'),
                            keyboardType: TextInputType.number,
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
                          const SizedBox(height: 12),
                          TextFormField(controller: _rationCardNo, decoration: appFieldDecoration('Ration Card No.'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _dlNo, decoration: appFieldDecoration('DL No.'), inputFormatters: [upperCaseInputFormatter]),
                          const SizedBox(height: 12),
                          TextFormField(controller: _electricityBillNo, decoration: appFieldDecoration('Electricity Bill No.'), inputFormatters: [upperCaseInputFormatter]),
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
