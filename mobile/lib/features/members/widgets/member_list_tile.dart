import 'package:flutter/material.dart';

import '../../../core/models/member.dart';
import '../../../core/theme/app_colors.dart';

class MemberListTile extends StatelessWidget {
  final MemberSummary member;
  final VoidCallback onTap;

  const MemberListTile({super.key, required this.member, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: AppColors.surface(context),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border(context)),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 12, offset: const Offset(0, 4)),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                MemberAvatar(name: member.fullName, photoUrl: member.photoUrl, radius: 26),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        member.fullName,
                        style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w700, color: AppColors.textPrimary(context)),
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 3),
                      Text(
                        member.enrollmentNo,
                        style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context), fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 6),
                      if (member.centerName != null)
                        _IconLabel(icon: Icons.groups_rounded, text: member.centerName!),
                      if (member.mobileNo != null) ...[
                        const SizedBox(height: 3),
                        _IconLabel(icon: Icons.call_rounded, text: member.mobileNo!),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                if (member.stage != null) _StageBadge(stage: member.stage!),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Client photo avatar with a graceful fallback to name initials -- most
/// members don't have a `photo_url` (KYC document migration only covers a
/// subset), and even when one exists the legacy host can be unreachable.
class MemberAvatar extends StatelessWidget {
  final String name;
  final String? photoUrl;
  final double radius;

  const MemberAvatar({super.key, required this.name, required this.photoUrl, this.radius = 26});

  String get _initials {
    final trimmed = name.trim();
    if (trimmed.isEmpty) return '?';
    final parts = trimmed.split(RegExp(r'\s+'));
    final first = parts.first.isNotEmpty ? parts.first[0] : '';
    final second = parts.length > 1 && parts.last.isNotEmpty ? parts.last[0] : '';
    return (first + second).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final fallback = Container(
      width: radius * 2,
      height: radius * 2,
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(colors: [Color(0xFF6A4FC7), Color(0xFF3A3D9F)]),
      ),
      alignment: Alignment.center,
      child: Text(
        _initials,
        style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: radius * 0.65),
      ),
    );

    if (photoUrl == null || photoUrl!.isEmpty) return fallback;

    return ClipOval(
      child: SizedBox(
        width: radius * 2,
        height: radius * 2,
        child: Image.network(
          photoUrl!,
          fit: BoxFit.cover,
          loadingBuilder: (context, child, progress) => progress == null ? child : fallback,
          errorBuilder: (context, error, stack) => fallback,
        ),
      ),
    );
  }
}

class _IconLabel extends StatelessWidget {
  final IconData icon;
  final String text;

  const _IconLabel({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 13, color: AppColors.textFaint(context)),
        const SizedBox(width: 5),
        Expanded(
          child: Text(
            text,
            style: TextStyle(fontSize: 12.5, color: AppColors.textMuted(context)),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}

class _StageBadge extends StatelessWidget {
  final String stage;

  const _StageBadge({required this.stage});

  Color get _color {
    switch (stage.toLowerCase()) {
      case 'converted':
      case 'active':
        return const Color(0xFF2FBF87);
      case 'rejected':
      case 'closed':
        return Colors.red.shade400;
      case 'verified':
        return const Color(0xFF4A9FFF);
      default:
        return const Color(0xFFC8952C);
    }
  }

  @override
  Widget build(BuildContext context) {
    final color = _color;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Text(
        stage,
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: color),
        textAlign: TextAlign.center,
      ),
    );
  }
}
