import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Local-only draft persistence for long field-entry forms (Loan Proposal,
/// Client Sourcing) -- a dropped network, a low-battery kill, or an
/// accidental back-tap shouldn't lose everything a field officer just
/// typed. Backed by the same secure-storage keychain the session tokens
/// already use, so it survives an app restart and never leaves the device
/// until the form is actually submitted.
class DraftStore {
  static const _storage = FlutterSecureStorage();
  static const _prefix = 'draft:';

  static Future<void> save(String key, Map<String, dynamic> data) {
    final envelope = {'saved_at': DateTime.now().toIso8601String(), 'data': data};
    return _storage.write(key: '$_prefix$key', value: jsonEncode(envelope));
  }

  /// The saved field values and when they were saved, or null if this key
  /// has no draft (or the stored value is unreadable, e.g. from an older
  /// app version's format -- treated the same as "no draft").
  static Future<(Map<String, dynamic> data, DateTime savedAt)?> load(String key) async {
    final raw = await _storage.read(key: '$_prefix$key');
    if (raw == null) return null;
    try {
      final envelope = jsonDecode(raw) as Map<String, dynamic>;
      return (envelope['data'] as Map<String, dynamic>, DateTime.parse(envelope['saved_at'] as String));
    } catch (_) {
      return null;
    }
  }

  static Future<void> clear(String key) => _storage.delete(key: '$_prefix$key');
}
