import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

/// One-tap call/WhatsApp from anywhere a client's mobile number is shown
/// (Member Detail, Dormant Client, Existing Client Journey) -- saves the
/// field officer from leaving the app to open their own dialer/WhatsApp.
Future<void> callNumber(BuildContext context, String phone) async {
  final uri = Uri(scheme: 'tel', path: phone);
  final ok = await launchUrl(uri).catchError((_) => false);
  if (!ok && context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Couldn't open the dialer")));
  }
}

Future<void> whatsAppNumber(BuildContext context, String phone, {String? message}) async {
  // wa.me needs a bare number (country code, no +/spaces/dashes) -- Indian
  // numbers are stored as 10 digits with no country code in this app, so
  // "91" is prefixed here.
  final digits = phone.replaceAll(RegExp(r'[^0-9]'), '');
  final withCountryCode = digits.length == 10 ? '91$digits' : digits;
  final uri = Uri.parse('https://wa.me/$withCountryCode').replace(
    queryParameters: message != null && message.isNotEmpty ? {'text': message} : null,
  );
  final ok = await launchUrl(uri, mode: LaunchMode.externalApplication).catchError((_) => false);
  if (!ok && context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Couldn't open WhatsApp")));
  }
}
