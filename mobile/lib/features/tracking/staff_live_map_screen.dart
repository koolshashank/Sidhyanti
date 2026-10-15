import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:go_router/go_router.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../../core/api/tracking_api.dart';
import '../../core/models/duty_session.dart';
import '../../core/theme/app_colors.dart';
import '../../l10n/generated/app_localizations.dart';

/// Admin-only: every staff member on an active duty session right now, on
/// an OpenStreetMap-tiled map. Polls tracking/live_map.php every 20s (no
/// websocket/Firestore needed -- the write volume here is low enough that
/// polling is simpler and free).
class StaffLiveMapScreen extends StatefulWidget {
  const StaffLiveMapScreen({super.key});

  @override
  State<StaffLiveMapScreen> createState() => _StaffLiveMapScreenState();
}

class _StaffLiveMapScreenState extends State<StaffLiveMapScreen> {
  static const _defaultCenter = LatLng(28.6139, 77.2090); // India fallback

  final _mapController = MapController();
  List<StaffLiveLocation> _staff = [];
  bool _loading = true;
  String? _error;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _load();
    _pollTimer = Timer.periodic(const Duration(seconds: 20), (_) => _load(silent: true));
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent) setState(() => _loading = true);
    try {
      final staff = await context.read<TrackingApi>().liveMap();
      if (!mounted) return;
      setState(() {
        _staff = staff;
        _error = null;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  String _minutesAgoLabel(AppLocalizations l10n, DateTime capturedAt) {
    final minutes = DateTime.now().difference(capturedAt).inMinutes;
    return minutes <= 0 ? l10n.trackingLastUpdatedJustNow : l10n.trackingLastUpdatedMinutesAgo(minutes);
  }

  void _openRouteHistory(StaffLiveLocation staff) {
    context.push('/staff-tracking/route?staff_id=${staff.staffId}&staff_name=${Uri.encodeComponent(staff.fullName)}');
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final center = _staff.isNotEmpty ? LatLng(_staff.first.latitude, _staff.first.longitude) : _defaultCenter;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.trackingAdminLiveMapTitle),
        actions: [IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: () => _load())],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _staff.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: () => _load(), child: Text(l10n.commonRetry)),
                    ],
                  ),
                )
              : Stack(
                  children: [
                    FlutterMap(
                      mapController: _mapController,
                      options: MapOptions(initialCenter: center, initialZoom: _staff.isEmpty ? 5 : 13),
                      children: [
                        TileLayer(
                          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                          userAgentPackageName: 'com.sidhyanti.mobile',
                        ),
                        MarkerLayer(
                          markers: _staff
                              .map((s) => Marker(
                                    point: LatLng(s.latitude, s.longitude),
                                    width: 42,
                                    height: 42,
                                    child: GestureDetector(
                                      onTap: () => _showStaffSheet(l10n, s),
                                      child: const Icon(Icons.location_on_rounded, color: Color(0xFFE65100), size: 42),
                                    ),
                                  ))
                              .toList(),
                        ),
                      ],
                    ),
                    if (_staff.isEmpty)
                      Center(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                          decoration: BoxDecoration(
                            color: AppColors.surface(context),
                            borderRadius: BorderRadius.circular(12),
                            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 10)],
                          ),
                          child: Text(l10n.trackingNoStaffOnDuty),
                        ),
                      ),
                  ],
                ),
    );
  }

  void _showStaffSheet(AppLocalizations l10n, StaffLiveLocation staff) {
    showModalBottomSheet<void>(
      context: context,
      builder: (sheetContext) => Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(staff.fullName, style: Theme.of(context).textTheme.titleMedium),
            if (staff.branchName != null) Text(staff.branchName!, style: TextStyle(color: AppColors.textMuted(context))),
            const SizedBox(height: 6),
            Text(_minutesAgoLabel(l10n, staff.capturedAt), style: TextStyle(color: AppColors.textFaint(context), fontSize: 12.5)),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () {
                  Navigator.of(sheetContext).pop();
                  _openRouteHistory(staff);
                },
                child: Text(l10n.trackingViewRoute),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
