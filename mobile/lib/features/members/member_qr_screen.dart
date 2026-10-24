import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/api/endpoints.dart';
import '../../core/theme/app_colors.dart';
import '../../l10n/generated/app_localizations.dart';

/// A full-screen QR code encoding a public member_verify.php URL (id +
/// enrollment number) -- shown to the member or printed. A generic phone
/// camera/scanner opens it as a normal web link (no more "No usable data
/// found" for a plain custom-text payload); the app's own in-app scanner
/// (scan_member_qr_screen.dart) instead parses the `id` query param and
/// jumps straight to the member's profile.
class MemberQrScreen extends StatelessWidget {
  final int memberId;
  final String memberName;
  final String? enrollmentNo;

  const MemberQrScreen({super.key, required this.memberId, required this.memberName, this.enrollmentNo});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final qrData = '$webBaseUrl/member_verify.php?id=$memberId&code=${Uri.encodeComponent(enrollmentNo ?? '')}';
    return Scaffold(
      appBar: AppBar(title: Text(l10n.memberQrTitle)),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.border(context)),
                ),
                child: QrImageView(
                  data: qrData,
                  version: QrVersions.auto,
                  size: 240,
                ),
              ),
              const SizedBox(height: 20),
              Text(memberName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              if (enrollmentNo != null) Text(enrollmentNo!, style: TextStyle(color: AppColors.textMuted(context), fontSize: 13)),
              const SizedBox(height: 16),
              Text(
                l10n.memberQrInstruction,
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textFaint(context), fontSize: 12),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
