import 'package:flutter/material.dart';

import '../../../core/models/member.dart';

/// The "you have an incomplete lead" card -- originally the single banner
/// shown atop the center-list page, now reused as one row in the Pending
/// Journey list so every pending client keeps the exact same look.
class JourneyResumeCard extends StatelessWidget {
  final IncompleteJourney journey;
  final bool discarding;
  final VoidCallback onResume;
  final VoidCallback onDiscard;

  const JourneyResumeCard({
    super.key,
    required this.journey,
    required this.discarding,
    required this.onResume,
    required this.onDiscard,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFFFF9F4A), Color(0xFFFF6F4A)]),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(color: const Color(0xFFFF6F4A).withValues(alpha: 0.3), blurRadius: 12, offset: const Offset(0, 6)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.history_rounded, color: Colors.white, size: 18),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'You have an incomplete lead',
                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13.5),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            journey.memberName,
            style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w800),
            overflow: TextOverflow.ellipsis,
          ),
          if (journey.centerName != null)
            Text(journey.centerName!, style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12)),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: discarding ? null : onDiscard,
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: Colors.white),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 10),
                  ),
                  child: discarding
                      ? const SizedBox(
                          height: 16,
                          width: 16,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Discard'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton(
                  onPressed: discarding ? null : onResume,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: const Color(0xFFFF6F4A),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                  ),
                  child: const Text('Continue', style: TextStyle(fontWeight: FontWeight.w700)),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
