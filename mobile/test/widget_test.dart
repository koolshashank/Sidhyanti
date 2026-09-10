import 'package:flutter_test/flutter_test.dart';
import 'package:mobile/core/utils/formatters.dart';

void main() {
  test('money formats INR with the rupee symbol', () {
    expect(money(1660), '₹1,660.00');
    expect(money(null), '₹0.00');
  });

  test('fdate formats a date and handles null', () {
    expect(fdate(DateTime(2026, 9, 6)), '6 Sep 2026');
    expect(fdate(null), '-');
  });
}
