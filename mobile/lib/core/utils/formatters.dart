import 'package:intl/intl.dart';

final _moneyFormat = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 2);
final _dateFormat = DateFormat('d MMM y');

String money(double? value) => _moneyFormat.format(value ?? 0);

String fdate(DateTime? date) => date == null ? '-' : _dateFormat.format(date);

/// Loan_Scheme.SchemeName is always "`<Product Type>` #`<N>`" (e.g. "JLG #6") --
/// the "#N" part is an internal sequence number, not meaningful to a field
/// officer. Used to build dropdown labels like "JLG (30000)" instead.
String schemeBaseName(String schemeName) => schemeName.replaceAll(RegExp(r'\s*#\d+$'), '');
