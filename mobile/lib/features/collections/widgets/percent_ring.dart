import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';

/// A percent-complete ring that shifts color with progress -- red when
/// collection has barely started, amber mid-way, green once it's basically
/// done -- so a field officer can gauge a center's status at a glance
/// without reading the numbers.
class PercentRing extends StatelessWidget {
  final double percent; // 0-100
  final double size;
  final Color? colorOverride;

  const PercentRing({super.key, required this.percent, this.size = 56, this.colorOverride});

  static Color colorFor(double percent) {
    if (percent >= 75) return const Color(0xFF2FBF87);
    if (percent >= 35) return const Color(0xFFFF9F4A);
    return const Color(0xFFFF6F4A);
  }

  @override
  Widget build(BuildContext context) {
    final clamped = percent.clamp(0, 100).toDouble();
    final color = colorOverride ?? colorFor(clamped);
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        alignment: Alignment.center,
        children: [
          SizedBox(
            width: size,
            height: size,
            child: CircularProgressIndicator(
              value: 1,
              strokeWidth: size * 0.1,
              strokeCap: StrokeCap.round,
              valueColor: AlwaysStoppedAnimation(color.withValues(alpha: 0.12)),
            ),
          ),
          SizedBox(
            width: size,
            height: size,
            child: CircularProgressIndicator(
              value: clamped / 100,
              strokeWidth: size * 0.1,
              strokeCap: StrokeCap.round,
              backgroundColor: Colors.transparent,
              valueColor: AlwaysStoppedAnimation(color),
            ),
          ),
          Text(
            '${clamped.toStringAsFixed(0)}%',
            style: TextStyle(fontSize: size * 0.24, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context)),
          ),
        ],
      ),
    );
  }
}
