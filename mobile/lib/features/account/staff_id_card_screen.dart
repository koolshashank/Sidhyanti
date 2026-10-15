import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../core/api/endpoints.dart';
import '../../core/auth/auth_state.dart';
import '../../core/theme/app_colors.dart';
import '../../l10n/generated/app_localizations.dart';

/// A staff member's own digital ID card -- photo, name, designation, branch,
/// and a QR code that opens staff_verify.php (a public, no-login web page)
/// when scanned by any phone camera, so a client can confirm the person in
/// front of them is a genuine, currently active Sidhyanti CRM staff member.
class StaffIdCardScreen extends StatelessWidget {
  const StaffIdCardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final staff = context.watch<AuthState>().currentStaff;

    if (staff == null) {
      return Scaffold(appBar: AppBar(title: Text(l10n.idCardTitle)), body: const SizedBox());
    }

    final verifyUrl = '$webBaseUrl/staff_verify.php?id=${staff.id}&code=${Uri.encodeComponent(staff.employeeCode ?? '')}';

    return Scaffold(
      appBar: AppBar(title: Text(l10n.idCardTitle)),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Container(
            constraints: const BoxConstraints(maxWidth: 360),
            decoration: BoxDecoration(
              color: AppColors.surface(context),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: AppColors.border(context)),
              boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 20, offset: const Offset(0, 8))],
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(colors: [Color(0xFF3B6FED), Color(0xFF6B93F5)]),
                  ),
                  child: Column(
                    children: [
                      Container(
                        width: 84,
                        height: 84,
                        decoration: const BoxDecoration(shape: BoxShape.circle, color: Colors.white),
                        padding: const EdgeInsets.all(3),
                        child: ClipOval(
                          child: staff.photo != null
                              ? Image.network(
                                  staff.photo!,
                                  fit: BoxFit.cover,
                                  errorBuilder: (_, _, _) => _InitialsAvatar(name: staff.fullName),
                                )
                              : _InitialsAvatar(name: staff.fullName),
                        ),
                      ),
                      const SizedBox(height: 14),
                      Text(
                        staff.fullName,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18),
                        textAlign: TextAlign.center,
                      ),
                      if (staff.designation != null) ...[
                        const SizedBox(height: 2),
                        Text(staff.designation!, style: TextStyle(color: Colors.white.withValues(alpha: 0.9), fontSize: 13)),
                      ],
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    children: [
                      if (staff.employeeCode != null)
                        _InfoRow(icon: Icons.badge_outlined, label: l10n.idCardEmployeeCode, value: staff.employeeCode!),
                      if (staff.branchName != null)
                        _InfoRow(icon: Icons.account_tree_outlined, label: l10n.idCardBranch, value: staff.branchName!),
                      const SizedBox(height: 20),
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: AppColors.border(context)),
                        ),
                        child: QrImageView(data: verifyUrl, version: QrVersions.auto, size: 180),
                      ),
                      const SizedBox(height: 14),
                      Text(
                        l10n.idCardScanInstruction,
                        textAlign: TextAlign.center,
                        style: TextStyle(color: AppColors.textMuted(context), fontSize: 12.5),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _InitialsAvatar extends StatelessWidget {
  final String name;

  const _InitialsAvatar({required this.name});

  @override
  Widget build(BuildContext context) {
    final initial = name.trim().isEmpty ? '?' : name.trim()[0].toUpperCase();
    return Container(
      color: const Color(0xFF3B6FED),
      alignment: Alignment.center,
      child: Text(initial, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 32)),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _InfoRow({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Icon(icon, size: 16, color: AppColors.textFaint(context)),
          const SizedBox(width: 10),
          Text(label, style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context))),
          const Spacer(),
          Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textPrimary(context))),
        ],
      ),
    );
  }
}
