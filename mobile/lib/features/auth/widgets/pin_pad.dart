import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';

/// 4-digit entry UI shared by the PIN setup and PIN lock screens -- a row
/// of filled/empty dots above a 0-9 numeric keypad with backspace. Sized to
/// a fixed, generous width (not just the keys' own intrinsic size) so the
/// keypad reads as one deliberate block instead of a few small buttons
/// floating in the middle of the screen.
class PinPad extends StatelessWidget {
  final String value;
  final ValueChanged<String> onChanged;
  final int length;
  final String? error;

  const PinPad({super.key, required this.value, required this.onChanged, this.length = 4, this.error});

  void _tapDigit(String digit) {
    if (value.length >= length) return;
    onChanged(value + digit);
  }

  void _backspace() {
    if (value.isEmpty) return;
    onChanged(value.substring(0, value.length - 1));
  }

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 320),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(length, (i) {
              final filled = i < value.length;
              return Container(
                margin: const EdgeInsets.symmetric(horizontal: 10),
                width: 18,
                height: 18,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: filled ? const Color(0xFFE65100) : Colors.transparent,
                  border: Border.all(color: const Color(0xFFE65100), width: 1.5),
                ),
              );
            }),
          ),
          SizedBox(
            height: 24,
            child: error != null
                ? Center(child: Text(error!, style: TextStyle(color: Colors.red.shade400, fontSize: 12.5), textAlign: TextAlign.center))
                : null,
          ),
          const SizedBox(height: 16),
          for (final row in [
            ['1', '2', '3'],
            ['4', '5', '6'],
            ['7', '8', '9'],
            ['', '0', 'back'],
          ])
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Row(
                children: row.map((key) {
                  if (key.isEmpty) return const Expanded(child: SizedBox());
                  if (key == 'back') {
                    return Expanded(
                      child: _PadButton(
                        onTap: _backspace,
                        child: Icon(Icons.backspace_outlined, size: 22, color: AppColors.textMuted(context)),
                      ),
                    );
                  }
                  return Expanded(
                    child: _PadButton(onTap: () => _tapDigit(key), child: Text(key, style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w600))),
                  );
                }).toList(),
              ),
            ),
        ],
      ),
    );
  }
}

class _PadButton extends StatelessWidget {
  final VoidCallback onTap;
  final Widget child;

  const _PadButton({required this.onTap, required this.child});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: AspectRatio(
        aspectRatio: 1,
        child: Material(
          color: AppColors.surfaceAlt(context),
          shape: const CircleBorder(),
          child: InkWell(
            customBorder: const CircleBorder(),
            onTap: onTap,
            child: Center(child: child),
          ),
        ),
      ),
    );
  }
}
