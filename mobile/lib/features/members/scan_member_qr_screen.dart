import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../l10n/generated/app_localizations.dart';

/// Scans a member's "Show My QR" code (member_qr_screen.dart) and jumps
/// straight to their profile -- the QR payload is just "MEMBER:{memberId}",
/// so there's no search/lookup ambiguity to resolve.
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
      final raw = barcode.rawValue;
      if (raw == null) continue;
      final match = RegExp(r'^MEMBER:(\d+)$').firstMatch(raw.trim());
      if (match != null) {
        _handled = true;
        final memberId = int.parse(match.group(1)!);
        context.pushReplacement('/members/$memberId');
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
