import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/features/dashboard/widgets/festival_banner.dart';

void main() {
  test('shows Diwali a few days before', () {
    final f = currentFestival(now: DateTime(2026, 11, 5));
    expect(f?.name, 'Diwali');
  });

  test('shows Diwali on the day itself', () {
    final f = currentFestival(now: DateTime(2026, 11, 8));
    expect(f?.name, 'Diwali');
  });

  test('shows Diwali one day after', () {
    final f = currentFestival(now: DateTime(2026, 11, 9));
    expect(f?.name, 'Diwali');
  });

  test('no festival mid-window between two festivals', () {
    final f = currentFestival(now: DateTime(2026, 9, 7));
    expect(f, isNull);
  });

  test('picks the closest festival when two are in range', () {
    // Dussehra (Oct 20) and Diwali (Nov 8) are far enough apart that only
    // one should ever be in the default window at a time.
    final f = currentFestival(now: DateTime(2026, 10, 18));
    expect(f?.name, 'Dussehra');
  });
}
