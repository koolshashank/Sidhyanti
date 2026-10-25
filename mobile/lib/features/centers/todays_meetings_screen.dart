import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/centers_api.dart';
import '../../core/models/center_meeting.dart';
import '../../core/theme/app_colors.dart';
import '../../shell/account_menu_button.dart';

/// Centers scheduled to meet today, with a GPS check-in action per center --
/// see api/v1/centers/todays_meetings.php / checkin.php. Distinct from the
/// Collection tab's center list: this is about confirming the officer
/// physically showed up, not about recording payments.
class TodaysMeetingsScreen extends StatefulWidget {
  const TodaysMeetingsScreen({super.key});

  @override
  State<TodaysMeetingsScreen> createState() => _TodaysMeetingsScreenState();
}

class _TodaysMeetingsScreenState extends State<TodaysMeetingsScreen> {
  List<TodaysMeetingCenter> _items = [];
  bool _loading = true;
  String? _error;
  int? _checkingInCenterId;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await context.read<CentersApi>().todaysMeetings();
      if (!mounted) return;
      setState(() => _items = items);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<Position?> _getLocation() async {
    try {
      return await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 12)),
      );
    } on TimeoutException {
      return Geolocator.getLastKnownPosition();
    } catch (_) {
      return null;
    }
  }

  Future<void> _checkIn(TodaysMeetingCenter center) async {
    setState(() => _checkingInCenterId = center.centerId);
    try {
      final position = await _getLocation();
      if (!mounted) return;
      if (position == null) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Could not get your location -- enable GPS and try again')));
        return;
      }
      final result = await context.read<CentersApi>().checkin(
            centerId: center.centerId,
            latitude: position.latitude,
            longitude: position.longitude,
          );
      if (!mounted) return;
      final msg = result.withinGeofence == null
          ? 'Checked in'
          : result.withinGeofence!
              ? 'Checked in -- within range'
              : 'Checked in, but ${result.distanceMeters?.round()}m from the center';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
      await _load();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _checkingInCenterId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text("Today's Meetings"), actions: const [AccountMenuButton(), SizedBox(width: 8)]),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: _load, child: const Text('Retry')),
                    ],
                  ),
                )
              : _items.isEmpty
                  ? RefreshIndicator(
                      onRefresh: _load,
                      child: ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const [Padding(padding: EdgeInsets.only(top: 80), child: Center(child: Text('No centers scheduled today.')))],
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _items.length,
                        itemBuilder: (context, index) {
                          final c = _items[index];
                          return _MeetingTile(
                            center: c,
                            checkingIn: _checkingInCenterId == c.centerId,
                            onCheckIn: () => _checkIn(c),
                          );
                        },
                      ),
                    ),
    );
  }
}

class _MeetingTile extends StatelessWidget {
  final TodaysMeetingCenter center;
  final bool checkingIn;
  final VoidCallback onCheckIn;

  const _MeetingTile({required this.center, required this.checkingIn, required this.onCheckIn});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(center.centerName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5)),
                if (center.branchName != null) Text(center.branchName!, style: TextStyle(fontSize: 12, color: AppColors.textMuted(context))),
                if (center.meetingTime != null) Text(center.meetingTime!, style: TextStyle(fontSize: 12, color: AppColors.textMuted(context))),
              ],
            ),
          ),
          if (center.isCheckedIn)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: (center.withinGeofence == false ? Colors.red : const Color(0xFF2FBF87)).withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                center.withinGeofence == false ? 'Checked in (out of range)' : 'Checked in',
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: center.withinGeofence == false ? Colors.red : const Color(0xFF2FBF87)),
              ),
            )
          else
            ElevatedButton.icon(
              onPressed: checkingIn ? null : onCheckIn,
              icon: checkingIn
                  ? const SizedBox(height: 14, width: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.my_location_rounded, size: 16),
              label: const Text('Check In'),
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF14A9AB), padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10)),
            ),
        ],
      ),
    );
  }
}
