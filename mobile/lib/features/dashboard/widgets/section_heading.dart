import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';

class SectionHeading extends StatelessWidget {
  final String text;

  const SectionHeading(this.text, {super.key});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 4,
          height: 20,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(2),
            gradient: const LinearGradient(colors: [Color(0xFF6A4FC7), Color(0xFF3A3D9F)]),
          ),
        ),
        const SizedBox(width: 10),
        Text(
          text,
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textPrimary(context)),
        ),
      ],
    );
  }
}
