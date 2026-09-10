import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';

class DashboardCard extends StatelessWidget {
  final IconData icon;
  final List<Color> gradient;
  final String title;
  final String? value;
  final String? subtitle;
  final VoidCallback? onTap;
  final bool loading;

  const DashboardCard({
    super.key,
    required this.icon,
    required this.gradient,
    required this.title,
    this.value,
    this.subtitle,
    this.onTap,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.surface(context),
      borderRadius: BorderRadius.circular(20),
      elevation: 0,
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: AppColors.border(context)),
            boxShadow: [
              BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 14, offset: const Offset(0, 6)),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(13),
                  gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: gradient),
                ),
                child: Icon(icon, color: Colors.white, size: 22),
              ),
              const SizedBox(height: 14),
              if (loading)
                const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2.2))
              else if (value != null && value!.isNotEmpty)
                Text(value!, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context))),
              if (value != null && value!.isNotEmpty) const SizedBox(height: 4),
              Text(
                title,
                style: TextStyle(
                  fontSize: value != null && value!.isNotEmpty ? 13 : 15,
                  fontWeight: FontWeight.w700,
                  color: value != null && value!.isNotEmpty ? AppColors.textMuted(context) : AppColors.textPrimary(context),
                ),
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 2),
                Text(subtitle!, style: TextStyle(fontSize: 11.5, color: AppColors.textFaint(context))),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
