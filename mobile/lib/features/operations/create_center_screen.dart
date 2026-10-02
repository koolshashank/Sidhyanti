import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../core/api/centers_api.dart';
import '../../core/theme/app_colors.dart';
import 'create_center_viewmodel.dart';
import 'widgets/form_section_card.dart';
import 'widgets/form_styles.dart';

class CreateCenterScreen extends StatelessWidget {
  const CreateCenterScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (context) => CreateCenterViewModel(context.read<CentersApi>()),
      child: const _CreateCenterView(),
    );
  }
}

class _CreateCenterView extends StatefulWidget {
  const _CreateCenterView();

  @override
  State<_CreateCenterView> createState() => _CreateCenterViewState();
}

class _CreateCenterViewState extends State<_CreateCenterView> {
  final _formKey = GlobalKey<FormState>();
  final _centerName = TextEditingController();
  final _centerPlace = TextEditingController();
  final _address = TextEditingController();
  final _pincode = TextEditingController();
  final _contactPerson = TextEditingController();
  final _contactNo = TextEditingController();
  final _meetingTimeController = TextEditingController();

  String? _meetingDay;
  TimeOfDay? _meetingTime;
  int? _stateId;
  bool _addressPrefilled = false;
  bool _statePrefilled = false;

  static const _days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

  // Captured once in initState -- calling context.read() from dispose() is
  // unsafe (the element tree may already be deactivated by then).
  late final CreateCenterViewModel _vm;

  @override
  void initState() {
    super.initState();
    _vm = context.read<CreateCenterViewModel>();
    // Prefill Address/State/Pincode once reverse-geocoding finishes -- only
    // the first time, so it never clobbers something the user already typed.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _vm.addListener(_prefillFromGeocode);
      _prefillFromGeocode();
    });
  }

  void _prefillFromGeocode() {
    if (!mounted) return;
    final vm = _vm;
    if (!_addressPrefilled && (vm.geocodedAddress != null || vm.geocodedPincode != null)) {
      setState(() {
        _address.text = vm.geocodedAddress ?? '';
        _pincode.text = vm.geocodedPincode ?? '';
        _addressPrefilled = true;
      });
    }
    // The state match can resolve later than the address (it needs both the
    // states list and the geocoding result), so it gets its own guard.
    if (!_statePrefilled && vm.geocodedStateId != null) {
      setState(() {
        _stateId = vm.geocodedStateId;
        _statePrefilled = true;
      });
    }
  }

  @override
  void dispose() {
    _vm.removeListener(_prefillFromGeocode);
    for (final c in [_centerName, _centerPlace, _address, _pincode, _contactPerson, _contactNo, _meetingTimeController]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(context: context, initialTime: TimeOfDay.now());
    if (picked != null) {
      setState(() {
        _meetingTime = picked;
        _meetingTimeController.text = picked.format(context);
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final vm = context.read<CreateCenterViewModel>();
    final result = await vm.submit(
      centerName: _centerName.text.trim(),
      centerPlace: _centerPlace.text.trim(),
      address: _address.text.trim(),
      stateId: _stateId,
      pincode: _pincode.text.trim(),
      meetingDay: _meetingDay,
      meetingTime: _meetingTime?.format(context),
      contactPerson: _contactPerson.text.trim(),
      contactNo: _contactNo.text.trim(),
    );
    if (result != null && mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text('${result.centerName} created (${result.centerCode})')));
      context.pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final vm = context.watch<CreateCenterViewModel>();

    return Scaffold(
      appBar: AppBar(title: const Text('Create New Center')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
          children: [
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
                _LocationStatusRow(vm: vm),
                if (vm.geocodingInProgress) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const SizedBox(
                        height: 12,
                        width: 12,
                        child: CircularProgressIndicator(strokeWidth: 1.8),
                      ),
                      const SizedBox(width: 8),
                      Text('Looking up address...', style: TextStyle(fontSize: 12, color: AppColors.textMuted(context))),
                    ],
                  ),
                ] else if (vm.geocodeError != null) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Icon(Icons.info_outline_rounded, size: 14, color: AppColors.textFaint(context)),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(vm.geocodeError!, style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context))),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: 12),
                TextFormField(
                  controller: _address,
                  decoration: appFieldDecoration('Address', hint: 'Auto-filled from current location'),
                  maxLines: 2,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: _stateId,
                  decoration: appFieldDecoration('State'),
                  isExpanded: true,
                  items: vm.states
                      .map((s) => DropdownMenuItem(value: s.id, child: Text(s.name, overflow: TextOverflow.ellipsis)))
                      .toList(),
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
                TextFormField(
                  controller: _contactPerson,
                  decoration: appFieldDecoration('Contact Person'),
                  inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r"[a-zA-Z.' ]"))],
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _contactNo,
                  decoration: appFieldDecoration('Contact Number'),
                  keyboardType: TextInputType.phone,
                ),
              ],
            ),
            if (vm.submitError != null) ...[
              Text(vm.submitError!, style: const TextStyle(color: Colors.red)),
              const SizedBox(height: 14),
            ],
            SubmitButton(loading: vm.submitting, label: 'Save Center', onPressed: _submit),
          ],
        ),
      ),
    );
  }
}

class _LocationStatusRow extends StatelessWidget {
  final CreateCenterViewModel vm;

  const _LocationStatusRow({required this.vm});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surfaceAlt(context),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(
            vm.latitude != null ? Icons.gps_fixed_rounded : Icons.gps_not_fixed_rounded,
            size: 18,
            color: vm.latitude != null ? const Color(0xFF2FBF87) : Colors.grey,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: vm.locating
                ? const Text('Capturing current location...', style: TextStyle(fontSize: 13))
                : vm.latitude != null
                    ? Text('${vm.latitude!.toStringAsFixed(5)}, ${vm.longitude!.toStringAsFixed(5)}',
                        style: const TextStyle(fontSize: 13))
                    : Text(vm.locationError ?? 'Location not captured',
                        style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
          ),
          if (!vm.locating)
            TextButton(
              onPressed: vm.retryLocation,
              style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 8), minimumSize: Size.zero),
              child: const Text('Retry', style: TextStyle(fontSize: 13)),
            ),
        ],
      ),
    );
  }
}
