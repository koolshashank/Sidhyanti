import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Remembers the last Bluetooth printer the staff member picked, so they
/// only have to choose it once -- subsequent receipts connect straight to
/// it without showing the picker again.
class PrinterPreferenceStore {
  static const _storage = FlutterSecureStorage();
  static const _key = 'saved_bluetooth_printer';

  static Future<void> save({required String name, required String macAddress}) =>
      _storage.write(key: _key, value: jsonEncode({'name': name, 'mac': macAddress}));

  static Future<(String name, String macAddress)?> load() async {
    final raw = await _storage.read(key: _key);
    if (raw == null) return null;
    try {
      final map = jsonDecode(raw) as Map<String, dynamic>;
      return (map['name'] as String, map['mac'] as String);
    } catch (_) {
      return null;
    }
  }

  static Future<void> clear() => _storage.delete(key: _key);
}
