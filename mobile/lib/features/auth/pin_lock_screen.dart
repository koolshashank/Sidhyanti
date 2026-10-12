import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../core/auth/auth_state.dart';
import '../../core/auth/pin_service.dart';
import '../../core/theme/app_colors.dart';
import 'widgets/pin_pad.dart';

/// Full-screen gate shown whenever an app-lock PIN is set and the app was
/// just opened or resumed from the background -- app_router.dart redirects
/// here for every route until the correct PIN is entered, the same pattern
/// the attendance gate uses.
class PinLockScreen extends StatefulWidget {
  const PinLockScreen({super.key});

  @override
  State<PinLockScreen> createState() => _PinLockScreenState();
}

class _PinLockScreenState extends State<PinLockScreen> {
  String _entry = '';
  String? _error;
  bool _checking = false;

  Future<void> _onChanged(String value) async {
    setState(() {
      _entry = value;
      _error = null;
    });
    if (value.length != 4 || _checking) return;

    setState(() => _checking = true);
    final ok = await PinService.verifyPin(value);
    if (!mounted) return;
    if (ok) {
      context.read<AuthState>().markPinUnlocked();
    } else {
      setState(() {
        _error = 'Incorrect PIN -- try again';
        _entry = '';
        _checking = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final staffName = context.watch<AuthState>().currentStaff?.fullName;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.lock_rounded, size: 40, color: AppColors.textFaint(context)),
                const SizedBox(height: 16),
                Text(
                  staffName != null ? 'Enter PIN to continue, $staffName' : 'Enter PIN to continue',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textPrimary(context)),
                ),
                const SizedBox(height: 24),
                PinPad(value: _entry, onChanged: _checking ? (_) {} : _onChanged, error: _error),
                const SizedBox(height: 16),
                TextButton(
                  onPressed: () => _confirmForgotPin(context),
                  child: const Text('Forgot PIN? Log Out'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _confirmForgotPin(BuildContext context) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Log Out'),
        content: const Text('This will log you out so you can sign back in without the PIN. Continue?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          TextButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              context.read<AuthState>().logout();
            },
            child: const Text('Log Out', style: TextStyle(color: Color(0xFFDC3545))),
          ),
        ],
      ),
    );
  }
}
