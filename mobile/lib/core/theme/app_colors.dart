import 'package:flutter/material.dart';

/// Semantic, theme-aware color tokens for the app's structural surfaces
/// (backgrounds, cards, borders, text). Brand/accent colors (the purple/navy
/// gradients, status greens/oranges/reds used on badges and headers) stay
/// fixed across light and dark -- they're already saturated enough to read
/// well on both, and are defined locally where used.
class AppColors {
  const AppColors._();

  static bool isDark(BuildContext context) => Theme.of(context).brightness == Brightness.dark;

  /// Scaffold / page background.
  static Color scaffoldBackground(BuildContext context) =>
      isDark(context) ? const Color(0xFF121319) : const Color(0xFFF5F5FB);

  /// Card / tile / sheet surface.
  static Color surface(BuildContext context) => isDark(context) ? const Color(0xFF1D1F27) : Colors.white;

  /// Tinted surface used for filled inputs, subtle info rows, chip backgrounds.
  static Color surfaceAlt(BuildContext context) =>
      isDark(context) ? const Color(0xFF262933) : const Color(0xFFF4F4FA);

  /// Card / input border.
  static Color border(BuildContext context) => isDark(context) ? const Color(0xFF32353F) : const Color(0xFFECEBF7);

  /// Primary body text.
  static Color textPrimary(BuildContext context) =>
      isDark(context) ? const Color(0xFFEDEDF2) : const Color(0xFF20223B);

  /// Secondary / muted text (labels, captions, timestamps).
  static Color textMuted(BuildContext context) => isDark(context) ? const Color(0xFF9A9DAE) : const Color(0xFF6B7280);

  /// Even lighter muted text (placeholders, faint icons).
  static Color textFaint(BuildContext context) => isDark(context) ? const Color(0xFF6B6E7C) : const Color(0xFF9CA3AF);

  /// Divider lines.
  static Color divider(BuildContext context) => isDark(context) ? const Color(0xFF2A2C36) : const Color(0xFFE5E7EB);

  /// Icon on a neutral (non-branded) surface.
  static Color iconNeutral(BuildContext context) => isDark(context) ? const Color(0xFFB4B6C2) : const Color(0xFF6B7280);

  /// Light saffron-tinted info banner/pill background (e.g. "Member: X" strips
  /// on the sourcing/KYC/GRT/Death-Entry forms) -- a deeper, desaturated tint
  /// on dark so it doesn't glow against the dark surface.
  static Color accentTint(BuildContext context) =>
      isDark(context) ? const Color(0xFF3D2A1A) : const Color(0xFFFFF0E0);

  /// Amber warning-tint banner background (info/caution strips).
  static Color warningTint(BuildContext context) =>
      isDark(context) ? const Color(0xFF332B1A) : const Color(0xFFFFF6E5);
}
