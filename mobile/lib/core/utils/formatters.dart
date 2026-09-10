import 'package:intl/intl.dart';

final _moneyFormat = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 2);
final _dateFormat = DateFormat('d MMM y');

String money(double? value) => _moneyFormat.format(value ?? 0);

String fdate(DateTime? date) => date == null ? '-' : _dateFormat.format(date);
