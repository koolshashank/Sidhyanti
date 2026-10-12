import 'package:flutter/material.dart';

import '../../core/auth/pin_service.dart';
import '../../core/theme/app_colors.dart';
import 'widgets/pin_pad.dart';

/// Create or change the app-lock PIN -- enter a new 4-digit PIN, then
/// confirm it. Pops `true` once saved.
class PinSetupScreen extends StatefulWidget {
  const PinSetupScreen({super.key});

  @override
  State<PinSetupScreen> createState() => _PinSetupScreenState();
}

class _PinSetupScreenState extends State<PinSetupScreen> {
  String _firstPin = '';
  String _entry = '';
  bool _confirming = false;
  String? _error;

  Future<void> _onChanged(String value) async {
    setState(() {
      _entry = value;
      _error = null;
    });
    if (value.length != 4) return;

    if (!_confirming) {
      setState(() {
        _firstPin = value;
        _entry = '';
        _confirming = true;
      });
      return;
    }

    if (value != _firstPin) {
      setState(() {
        _error = "PINs didn't match -- try again";
        _entry = '';
        _confirming = false;
        _firstPin = '';
      });
      return;
    }

    await PinService.setPin(value);
    if (mounted) Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('App Lock PIN')),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.lock_outline_rounded, size: 40, color: AppColors.textFaint(context)),
              const SizedBox(height: 16),
              Text(
                _confirming ? 'Confirm your new PIN' : 'Enter a new 4-digit PIN',
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textPrimary(context)),
              ),
              const SizedBox(height: 24),
              PinPad(value: _entry, onChanged: _onChanged, error: _error),
            ],
          ),
        ),
      ),
    );
  }
}
