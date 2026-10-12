import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/hr_api.dart';
import '../../core/auth/auth_state.dart';
import '../../core/theme/app_colors.dart';

/// Full-screen gate shown in place of the dashboard whenever the signed-in
/// staff member hasn't checked in yet today -- `app_router.dart` redirects
/// here for every route until [AuthState.attendanceRequired] clears, so
/// there is no way to reach any other screen without marking attendance
/// first. The same check is also enforced server-side (require_api_auth)
/// so this isn't just a client-side nicety.
class AttendanceGateScreen extends StatefulWidget {
  const AttendanceGateScreen({super.key});

  @override
  State<AttendanceGateScreen> createState() => _AttendanceGateScreenState();
}

class _AttendanceGateScreenState extends State<AttendanceGateScreen> {
  bool _checkingIn = false;
  String? _error;

  Future<(double?, double?)> _tryGetLocation() async {
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 12)),
      );
      return (position.latitude, position.longitude);
    } on TimeoutException {
      final last = await Geolocator.getLastKnownPosition();
      return (last?.latitude, last?.longitude);
    } catch (_) {
      return (null, null);
    }
  }

  Future<void> _markAttendance() async {
    setState(() {
      _checkingIn = true;
      _error = null;
    });
    try {
      final (lat, lng) = await _tryGetLocation();
      await context.read<HrApi>().checkIn(latitude: lat, longitude: lng);
      if (!mounted) return;
      context.read<AuthState>().markAttendanceDone();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _checkingIn = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final staffName = context.watch<AuthState>().currentStaff?.fullName;
    return Scaffold(
      backgroundColor: AppColors.surfaceAlt(context),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    width: 84,
                    height: 84,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: const LinearGradient(colors: [Color(0xFFE65100), Color(0xFFBF360C)]),
                      boxShadow: [
                        BoxShadow(color: const Color(0xFFE65100).withValues(alpha: 0.35), blurRadius: 24, offset: const Offset(0, 10)),
                      ],
                    ),
                    child: const Icon(Icons.fingerprint_rounded, size: 44, color: Colors.white),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    staffName != null ? 'Good day, $staffName' : 'Good day',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context)),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Please mark today\'s attendance to continue. You won\'t be able to use the app until you check in.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 13.5, color: AppColors.textMuted(context)),
                  ),
                  const SizedBox(height: 28),
                  if (_error != null) ...[
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(12)),
                      child: Row(
                        children: [
                          Icon(Icons.error_outline_rounded, size: 18, color: Colors.red.shade700),
                          const SizedBox(width: 8),
                          Expanded(child: Text(_error!, style: TextStyle(fontSize: 12.5, color: Colors.red.shade800))),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],
                  SizedBox(
                    height: 54,
                    width: double.infinity,
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(16),
                        gradient: const LinearGradient(colors: [Color(0xFFE65100), Color(0xFFBF360C)]),
                      ),
                      child: Material(
                        color: Colors.transparent,
                        child: InkWell(
                          borderRadius: BorderRadius.circular(16),
                          onTap: _checkingIn ? null : _markAttendance,
                          child: Center(
                            child: _checkingIn
                                ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
                                : const Text(
                                    'Mark Attendance',
                                    style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
                                  ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextButton(
                    onPressed: _checkingIn ? null : () => context.read<AuthState>().logout(),
                    child: const Text('Not you? Log out'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
