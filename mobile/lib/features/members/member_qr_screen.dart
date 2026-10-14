import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/theme/app_colors.dart';
import '../../l10n/generated/app_localizations.dart';

/// A full-screen QR code encoding "MEMBER:{memberId}" -- shown to the
/// member or printed, and scanned back via scan_member_qr_screen.dart for
/// instant profile lookup without a name/mobile search.
class MemberQrScreen extends StatelessWidget {
  final int memberId;
  final String memberName;
  final String? enrollmentNo;

  const MemberQrScreen({super.key, required this.memberId, required this.memberName, this.enrollmentNo});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
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
                  data: 'MEMBER:$memberId',
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
