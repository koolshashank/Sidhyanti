import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../l10n/generated/app_localizations.dart';

/// Scans a member's "Show My QR" code (member_qr_screen.dart) and jumps
/// straight to their profile -- the QR payload is a member_verify.php URL
/// with an `id` query param, so there's no search/lookup ambiguity to
/// resolve. (The URL form -- rather than a bare "MEMBER:123" -- is what
/// lets a generic phone camera/scanner open it too, instead of showing
/// "No usable data found" for an unrecognized plain-text payload.)
class ScanMemberQrScreen extends StatefulWidget {
  const ScanMemberQrScreen({super.key});

  @override
  State<ScanMemberQrScreen> createState() => _ScanMemberQrScreenState();
}

class _ScanMemberQrScreenState extends State<ScanMemberQrScreen> {
  final _controller = MobileScannerController();
  bool _handled = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_handled) return;
    for (final barcode in capture.barcodes) {
      final raw = barcode.rawValue?.trim();
      if (raw == null || raw.isEmpty) continue;

      // Current format: a member_verify.php URL with an `id` query param.
      final uri = Uri.tryParse(raw);
      final idParam = uri?.queryParameters['id'];
      final memberId = idParam != null ? int.tryParse(idParam) : null;
      if (memberId != null) {
        _handled = true;
        context.pushReplacement('/members/$memberId');
        return;
      }

      // Older printed QR codes may still use the original plain-text form.
      final legacyMatch = RegExp(r'^MEMBER:(\d+)$').firstMatch(raw);
      if (legacyMatch != null) {
        _handled = true;
        context.pushReplacement('/members/${legacyMatch.group(1)}');
        return;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.scanMemberQrTitle),
        actions: [
          IconButton(
            icon: ValueListenableBuilder(
              valueListenable: _controller,
              builder: (context, state, child) => Icon(state.torchState == TorchState.on ? Icons.flash_on_rounded : Icons.flash_off_rounded),
            ),
            onPressed: () => _controller.toggleTorch(),
          ),
        ],
      ),
      body: Stack(
        alignment: Alignment.center,
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),
          Container(
            width: 240,
            height: 240,
            decoration: BoxDecoration(border: Border.all(color: Colors.white, width: 2), borderRadius: BorderRadius.circular(16)),
          ),
          Positioned(
            bottom: 40,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(20)),
              child: Text(l10n.scanMemberQrInstruction, style: const TextStyle(color: Colors.white, fontSize: 12.5)),
            ),
          ),
        ],
      ),
    );
  }
}
