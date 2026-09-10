import 'package:flutter/material.dart';

class Festival {
  final String name;
  final String emoji;
  final String message;
  final List<Color> colors;
  final DateTime date;

  const Festival({
    required this.name,
    required this.emoji,
    required this.message,
    required this.colors,
    required this.date,
  });
}

/// Major Indian festivals for 2026. Dates for lunar/moon-sighting-based
/// festivals (Holi, Eid, Janmashtami, Diwali, etc.) are best-effort estimates
/// -- re-check and update this list at the start of every year.
final List<Festival> _festivals2026 = [
  Festival(name: 'New Year', emoji: '🎉', message: 'Wishing you a wonderful New Year!', colors: const [Color(0xFF6A4FC7), Color(0xFF3A3D9F)], date: DateTime(2026, 1, 1)),
  Festival(name: 'Makar Sankranti', emoji: '🪁', message: 'Happy Makar Sankranti!', colors: const [Color(0xFFFF9F4A), Color(0xFFFF6F4A)], date: DateTime(2026, 1, 14)),
  Festival(name: 'Republic Day', emoji: '🇮🇳', message: 'Happy Republic Day!', colors: const [Color(0xFFFF9933), Color(0xFF138808)], date: DateTime(2026, 1, 26)),
  Festival(name: 'Maha Shivratri', emoji: '🔱', message: 'Happy Maha Shivratri!', colors: const [Color(0xFF4A5FD9), Color(0xFF2A2F7F)], date: DateTime(2026, 2, 15)),
  Festival(name: 'Holi', emoji: '🎨', message: 'Happy Holi! Wishing you colours of joy.', colors: const [Color(0xFFEC4899), Color(0xFFF97316)], date: DateTime(2026, 3, 4)),
  Festival(name: 'Ram Navami', emoji: '🏹', message: 'Happy Ram Navami!', colors: const [Color(0xFFEF4444), Color(0xFFB91C1C)], date: DateTime(2026, 3, 26)),
  Festival(name: 'Eid-ul-Fitr', emoji: '🌙', message: 'Eid Mubarak!', colors: const [Color(0xFF10B981), Color(0xFF047857)], date: DateTime(2026, 3, 20)),
  Festival(name: 'Baisakhi', emoji: '🌾', message: 'Happy Baisakhi!', colors: const [Color(0xFFF59E0B), Color(0xFFB45309)], date: DateTime(2026, 4, 14)),
  Festival(name: 'Buddha Purnima', emoji: '☸️', message: 'Happy Buddha Purnima!', colors: const [Color(0xFFF59E0B), Color(0xFF7C3AED)], date: DateTime(2026, 5, 1)),
  Festival(name: 'Eid-ul-Adha', emoji: '🌙', message: 'Eid Mubarak!', colors: const [Color(0xFF10B981), Color(0xFF047857)], date: DateTime(2026, 5, 27)),
  Festival(name: 'Independence Day', emoji: '🇮🇳', message: 'Happy Independence Day!', colors: const [Color(0xFFFF9933), Color(0xFF138808)], date: DateTime(2026, 8, 15)),
  Festival(name: 'Raksha Bandhan', emoji: '🎀', message: 'Happy Raksha Bandhan!', colors: const [Color(0xFFEC4899), Color(0xFFBE185D)], date: DateTime(2026, 8, 28)),
  Festival(name: 'Janmashtami', emoji: '🦚', message: 'Happy Janmashtami!', colors: const [Color(0xFF4A9FFF), Color(0xFF3A3D9F)], date: DateTime(2026, 9, 4)),
  Festival(name: 'Ganesh Chaturthi', emoji: '🐘', message: 'Ganpati Bappa Morya!', colors: const [Color(0xFFFF9F4A), Color(0xFFEF4444)], date: DateTime(2026, 9, 14)),
  Festival(name: 'Gandhi Jayanti', emoji: '🕊️', message: 'Remembering Gandhi Jayanti.', colors: const [Color(0xFF6A4FC7), Color(0xFF3A3D9F)], date: DateTime(2026, 10, 2)),
  Festival(name: 'Navratri', emoji: '💃', message: 'Happy Navratri!', colors: const [Color(0xFFEC4899), Color(0xFFF97316)], date: DateTime(2026, 10, 11)),
  Festival(name: 'Dussehra', emoji: '🏹', message: 'Happy Dussehra!', colors: const [Color(0xFFEF4444), Color(0xFFB91C1C)], date: DateTime(2026, 10, 20)),
  Festival(name: 'Diwali', emoji: '🪔', message: 'Happy Diwali! Wishing you light and prosperity.', colors: const [Color(0xFFF59E0B), Color(0xFFEF4444)], date: DateTime(2026, 11, 8)),
  Festival(name: 'Bhai Dooj', emoji: '👫', message: 'Happy Bhai Dooj!', colors: const [Color(0xFFEC4899), Color(0xFFF97316)], date: DateTime(2026, 11, 10)),
  Festival(name: 'Guru Nanak Jayanti', emoji: '🙏', message: 'Happy Guru Nanak Jayanti!', colors: const [Color(0xFFF59E0B), Color(0xFF4A5FD9)], date: DateTime(2026, 11, 24)),
  Festival(name: 'Christmas', emoji: '🎄', message: 'Merry Christmas!', colors: const [Color(0xFF16A34A), Color(0xFFB91C1C)], date: DateTime(2026, 12, 25)),
];

/// Returns the closest festival within [daysBefore] days ahead or [daysAfter]
/// days behind today, or null if none fall in that window.
Festival? currentFestival({DateTime? now, int daysBefore = 5, int daysAfter = 1}) {
  final today = DateTime(now?.year ?? DateTime.now().year, now?.month ?? DateTime.now().month, now?.day ?? DateTime.now().day);
  Festival? closest;
  int? closestDiff;
  for (final f in _festivals2026) {
    final diff = f.date.difference(today).inDays;
    if (diff >= -daysAfter && diff <= daysBefore) {
      final absDiff = diff.abs();
      if (closestDiff == null || absDiff < closestDiff) {
        closest = f;
        closestDiff = absDiff;
      }
    }
  }
  return closest;
}

class FestivalBannerCard extends StatelessWidget {
  final Festival festival;

  const FestivalBannerCard({super.key, required this.festival});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 18),
      decoration: BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: festival.colors),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(color: festival.colors.last.withValues(alpha: 0.35), blurRadius: 16, offset: const Offset(0, 8)),
        ],
      ),
      child: Row(
        children: [
          Text(festival.emoji, style: const TextStyle(fontSize: 34)),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(festival.name, style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)),
                const SizedBox(height: 2),
                Text(
                  festival.message,
                  style: TextStyle(color: Colors.white.withValues(alpha: 0.92), fontSize: 12.5),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
