import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:provider/provider.dart';

import '../../core/api/api_exception.dart';
import '../../core/api/tracking_api.dart';
import '../../core/theme/app_colors.dart';
import '../../core/tracking/duty_tracking_service.dart';
import '../../core/tracking/tracking_permission_service.dart';
import '../../l10n/generated/app_localizations.dart';

/// Start/End Duty for the signed-in staff member -- shows a one-time
/// consent screen before the very first duty session, then walks through
/// the while-in-use -> background -> notification permission flow every
/// time Start Duty is pressed (cheap no-ops if already granted).
class DutyTrackingScreen extends StatefulWidget {
  const DutyTrackingScreen({super.key});

  @override
  State<DutyTrackingScreen> createState() => _DutyTrackingScreenState();
}

class _DutyTrackingScreenState extends State<DutyTrackingScreen> {
  static const _storage = FlutterSecureStorage();
  static const _consentKey = 'tracking_consent_given_locally';

  final _permissionService = TrackingPermissionService();
  bool? _hasConsented; // null while loading from local storage
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadConsent();
  }

  Future<void> _loadConsent() async {
    final raw = await _storage.read(key: _consentKey);
    if (mounted) setState(() => _hasConsented = raw == 'true');
  }

  Future<void> _giveConsent() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<TrackingApi>().submitConsent(true);
      await _storage.write(key: _consentKey, value: 'true');
      if (mounted) setState(() => _hasConsented = true);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _startDuty() async {
    final l10n = AppLocalizations.of(context)!;
    final dutyService = context.read<DutyTrackingService>();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await _permissionService.requestAll();
      if (result != TrackingPermissionResult.granted) {
        if (mounted) await _showPermissionDeniedDialog(result);
        return;
      }
      final ok = await dutyService.startDuty();
      if (!ok && mounted) {
        setState(() => _error = l10n.trackingStartError);
      }
    } on ApiException catch (e) {
      if (e.isConsentRequired) {
        // Server-side safety net -- consent was revoked/missing even though
        // the local flag said otherwise. Fall back to the consent screen.
        await _storage.delete(key: _consentKey);
        if (mounted) setState(() => _hasConsented = false);
      } else if (mounted) {
        setState(() => _error = e.message);
      }
    } catch (_) {
      if (mounted) setState(() => _error = l10n.trackingStartError);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _showPermissionDeniedDialog(TrackingPermissionResult result) async {
    final l10n = AppLocalizations.of(context)!;
    final (title, body) = switch (result) {
      TrackingPermissionResult.whileInUseDenied => (l10n.trackingPermissionWhileInUseTitle, l10n.trackingPermissionWhileInUseBody),
      TrackingPermissionResult.backgroundDenied => (l10n.trackingPermissionBackgroundTitle, l10n.trackingPermissionBackgroundBody),
      TrackingPermissionResult.notificationDenied => (l10n.trackingPermissionNotificationTitle, l10n.trackingPermissionNotificationBody),
      TrackingPermissionResult.granted => ('', ''),
    };
    final permanentlyDenied = await _permissionService.isPermanentlyDenied(result);
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: Text(l10n.commonCancel)),
          FilledButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              if (permanentlyDenied) {
                _permissionService.openSettings();
              } else {
                _startDuty();
              }
            },
            child: Text(permanentlyDenied ? l10n.trackingPermissionOpenSettings : l10n.trackingPermissionRetry),
          ),
        ],
      ),
    );
  }

  Future<void> _endDuty() async {
    final l10n = AppLocalizations.of(context)!;
    final dutyService = context.read<DutyTrackingService>();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l10n.trackingEndConfirmTitle),
        content: Text(l10n.trackingEndConfirmBody),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: Text(l10n.commonCancel)),
          FilledButton(onPressed: () => Navigator.of(dialogContext).pop(true), child: Text(l10n.trackingEndDuty)),
        ],
      ),
    );
    if (confirmed != true) return;
    setState(() => _busy = true);
    await dutyService.endDuty();
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final service = context.watch<DutyTrackingService>();

    return Scaffold(
      appBar: AppBar(title: Text(l10n.menuDutyTrackingTitle)),
      body: _hasConsented == null
          ? const Center(child: CircularProgressIndicator())
          : _hasConsented == false
              ? _buildConsent(l10n)
              : _buildDutyStatus(l10n, service),
    );
  }

  Widget _buildConsent(AppLocalizations l10n) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.location_on_rounded, size: 72, color: AppColors.textMuted(context)),
          const SizedBox(height: 20),
          Text(l10n.trackingConsentTitle, style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          Text(l10n.trackingConsentBody, textAlign: TextAlign.center, style: TextStyle(color: AppColors.textMuted(context), height: 1.4)),
          if (_error != null) ...[
            const SizedBox(height: 16),
            Text(_error!, style: const TextStyle(color: Colors.red), textAlign: TextAlign.center),
          ],
          const SizedBox(height: 28),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: FilledButton(
              onPressed: _busy ? null : _giveConsent,
              child: _busy
                  ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(l10n.trackingConsentAgree),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDutyStatus(AppLocalizations l10n, DutyTrackingService service) {
    final onDuty = service.isOnDuty;
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            onDuty ? Icons.my_location_rounded : Icons.location_disabled_rounded,
            size: 72,
            color: onDuty ? const Color(0xFF2FBF87) : AppColors.textMuted(context),
          ),
          const SizedBox(height: 20),
          Text(
            onDuty && service.startTime != null
                ? l10n.trackingOnDutySince(TimeOfDay.fromDateTime(service.startTime!).format(context))
                : l10n.trackingNotOnDuty,
            style: Theme.of(context).textTheme.titleMedium,
            textAlign: TextAlign.center,
          ),
          if (_error != null) ...[
            const SizedBox(height: 16),
            Text(_error!, style: const TextStyle(color: Colors.red), textAlign: TextAlign.center),
          ],
          const SizedBox(height: 28),
          SizedBox(
            width: double.infinity,
            height: 52,
            child: FilledButton(
              style: FilledButton.styleFrom(backgroundColor: onDuty ? Colors.red.shade500 : const Color(0xFF2FBF87)),
              onPressed: _busy ? null : (onDuty ? _endDuty : _startDuty),
              child: _busy
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(onDuty ? l10n.trackingEndDuty : l10n.trackingStartDuty),
            ),
          ),
        ],
      ),
    );
  }
}
