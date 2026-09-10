import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api/api_exception.dart';
import '../core/api/sos_api.dart';
import '../core/location/location_service.dart';

/// A small always-on-top emergency button, shown above every authenticated
/// screen (wired in app.dart's MaterialApp.router `builder`) so it's
/// reachable no matter where the user is in the app when they need it.
class SosButton extends StatefulWidget {
  const SosButton({super.key});

  @override
  State<SosButton> createState() => _SosButtonState();
}

class _SosButtonState extends State<SosButton> {
  bool _sending = false;

  Future<void> _confirmAndSend() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        icon: const Icon(Icons.sos_rounded, color: Color(0xFFDC3545), size: 32),
        title: const Text('Send SOS Alert?'),
        content: const Text(
          'This immediately notifies your Branch Manager and Admin with your current location. Only use this in a genuine emergency.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFFDC3545)),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Send SOS'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _sending = true);
    try {
      final (lat, lng) = await context.read<LocationService>().tryGetCurrentPosition();
      final result = await context.read<SosApi>().trigger(latitude: lat, longitude: lng);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        backgroundColor: const Color(0xFF2FBF87),
        content: Text('SOS alert sent — ${result.notifiedCount} people notified'),
      ));
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        backgroundColor: const Color(0xFFDC3545),
        content: Text('Could not send SOS: ${e.message}'),
      ));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Positioned(
      right: 14,
      bottom: 90,
      child: SafeArea(
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: _sending ? null : _confirmAndSend,
            borderRadius: BorderRadius.circular(30),
            child: Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: const LinearGradient(colors: [Color(0xFFE57373), Color(0xFFDC3545)]),
                boxShadow: [BoxShadow(color: const Color(0xFFDC3545).withValues(alpha: 0.45), blurRadius: 12, offset: const Offset(0, 4))],
              ),
              alignment: Alignment.center,
              child: _sending
                  ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white))
                  : const Icon(Icons.sos_rounded, color: Colors.white, size: 28),
            ),
          ),
        ),
      ),
    );
  }
}
