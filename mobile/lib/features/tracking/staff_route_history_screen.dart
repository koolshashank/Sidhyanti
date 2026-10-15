import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../../core/api/tracking_api.dart';
import '../../core/models/duty_session.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../l10n/generated/app_localizations.dart';

/// Admin-only: a staff member's full route for one calendar day -- a
/// polyline over the filtered points, start/end markers, and the
/// server-computed distance/duration summary. Reached by tapping a staff
/// marker on the Staff Live Map, with that staff + today's date pre-filled;
/// the date can be changed from here.
class StaffRouteHistoryScreen extends StatefulWidget {
  final int staffId;
  final String staffName;

  const StaffRouteHistoryScreen({super.key, required this.staffId, required this.staffName});

  @override
  State<StaffRouteHistoryScreen> createState() => _StaffRouteHistoryScreenState();
}

class _StaffRouteHistoryScreenState extends State<StaffRouteHistoryScreen> {
  DateTime _date = DateTime.now();
  StaffRouteResult? _result;
  bool _loading = true;
  String? _error;

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
      final result = await context.read<TrackingApi>().route(staffId: widget.staffId, date: _date);
      if (!mounted) return;
      setState(() {
        _result = result;
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

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      setState(() => _date = picked);
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final result = _result;
    final points = result?.points ?? [];

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.staffName),
        actions: [
          TextButton(
            onPressed: _loading ? null : _pickDate,
            child: Text(fdate(_date), style: const TextStyle(color: Colors.white)),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!, textAlign: TextAlign.center),
                      const SizedBox(height: 12),
                      OutlinedButton(onPressed: _load, child: Text(l10n.commonRetry)),
                    ],
                  ),
                )
              : points.isEmpty
                  ? Center(child: Text(l10n.trackingNoRouteData, textAlign: TextAlign.center))
                  : Column(
                      children: [
                        Expanded(
                          child: FlutterMap(
                            options: MapOptions(
                              initialCenter: LatLng(points.first.latitude, points.first.longitude),
                              initialZoom: 13,
                            ),
                            children: [
                              TileLayer(
                                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                                userAgentPackageName: 'com.sidhyanti.mobile',
                              ),
                              PolylineLayer(polylines: [
                                Polyline(
                                  points: points.map((p) => LatLng(p.latitude, p.longitude)).toList(),
                                  color: const Color(0xFFE65100),
                                  strokeWidth: 4,
                                ),
                              ]),
                              MarkerLayer(markers: [
                                Marker(
                                  point: LatLng(points.first.latitude, points.first.longitude),
                                  width: 36,
                                  height: 36,
                                  child: const Icon(Icons.trip_origin_rounded, color: Color(0xFF2FBF87), size: 30),
                                ),
                                Marker(
                                  point: LatLng(points.last.latitude, points.last.longitude),
                                  width: 36,
                                  height: 36,
                                  child: const Icon(Icons.location_on_rounded, color: Colors.red, size: 36),
                                ),
                              ]),
                            ],
                          ),
                        ),
                        _buildSummary(l10n, result!),
                      ],
                    ),
    );
  }

  Widget _buildSummary(AppLocalizations l10n, StaffRouteResult result) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10, offset: const Offset(0, -2))],
      ),
      child: Row(
        children: [
          Expanded(child: _SummaryStat(label: l10n.trackingDistance, value: '${result.totalDistanceKm} km')),
          Expanded(child: _SummaryStat(label: l10n.trackingDuration, value: '${result.durationMinutes} min')),
          Expanded(child: _SummaryStat(label: l10n.trackingPoints, value: '${result.points.length}')),
        ],
      ),
    );
  }
}

class _SummaryStat extends StatelessWidget {
  final String label;
  final String value;

  const _SummaryStat({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(label, style: TextStyle(fontSize: 10.5, color: AppColors.textFaint(context), fontWeight: FontWeight.w700)),
        const SizedBox(height: 4),
        Text(value, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context))),
      ],
    );
  }
}
