import 'package:flutter/material.dart';

import '../../../core/location/location_service.dart';
import '../../../core/theme/app_colors.dart';

/// Full-bleed prompt shown on the dashboard when location services are off
/// or permission hasn't been granted -- blocks the stat cards until resolved.
class LocationGate extends StatelessWidget {
  final LocationStatus status;
  final bool checking;
  final VoidCallback onRetry;
  final VoidCallback onOpenLocationSettings;
  final VoidCallback onOpenAppSettings;

  const LocationGate({
    super.key,
    required this.status,
    required this.checking,
    required this.onRetry,
    required this.onOpenLocationSettings,
    required this.onOpenAppSettings,
  });

  bool get _isServiceDisabled => status == LocationStatus.serviceDisabled;

  @override
  Widget build(BuildContext context) {
    final title = _isServiceDisabled ? 'Turn On Location' : 'Location Access Needed';
    final message = _isServiceDisabled
        ? 'Your device\'s location service is switched off. Please turn it on to continue using the app.'
        : 'Sidhyanti CRM needs location permission to continue. Please allow access from settings.';

    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 108,
              height: 108,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Colors.red.shade300, Colors.red.shade500],
                ),
                boxShadow: [
                  BoxShadow(color: Colors.red.withValues(alpha: 0.28), blurRadius: 28, offset: const Offset(0, 12)),
                ],
              ),
              child: const Icon(Icons.location_off_rounded, color: Colors.white, size: 52),
            ),
            const SizedBox(height: 28),
            Text(
              title,
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 21, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context)),
            ),
            const SizedBox(height: 10),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 14, color: AppColors.textMuted(context), height: 1.4),
            ),
            const SizedBox(height: 32),
            SizedBox(
              width: double.infinity,
              height: 52,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(16),
                  gradient: const LinearGradient(colors: [Color(0xFF6A4FC7), Color(0xFF3A3D9F)]),
                  boxShadow: [
                    BoxShadow(color: const Color(0xFF6A4FC7).withValues(alpha: 0.35), blurRadius: 16, offset: const Offset(0, 8)),
                  ],
                ),
                child: Material(
                  color: Colors.transparent,
                  child: InkWell(
                    borderRadius: BorderRadius.circular(16),
                    onTap: _isServiceDisabled ? onOpenLocationSettings : onOpenAppSettings,
                    child: Center(
                      child: Text(
                        _isServiceDisabled ? 'Open Location Settings' : 'Open App Settings',
                        style: const TextStyle(color: Colors.white, fontSize: 15.5, fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 14),
            TextButton.icon(
              onPressed: checking ? null : onRetry,
              icon: checking
                  ? const SizedBox(height: 16, width: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.refresh_rounded, size: 18),
              label: Text(checking ? 'Checking...' : "I've turned it on -- Retry"),
              style: TextButton.styleFrom(foregroundColor: const Color(0xFF3A3D9F)),
            ),
          ],
        ),
      ),
    );
  }
}
