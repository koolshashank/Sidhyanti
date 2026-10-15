import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/duty_session.dart';

/// Local-only queue of GPS points captured while on duty but not yet
/// confirmed uploaded -- survives the app being killed/restarted while
/// offline, backed by the same secure-storage keychain the auth tokens and
/// form drafts already use (see DraftStore). Flushed in one batch by
/// [DutyTrackingService] whenever a ping call succeeds.
class LocationQueueStore {
  static const _storage = FlutterSecureStorage();
  static const _key = 'tracking_point_queue';

  static Future<void> enqueue(LocationPoint point) async {
    final queue = await peekAll();
    queue.add(point);
    await _write(queue);
  }

  static Future<List<LocationPoint>> peekAll() async {
    final raw = await _storage.read(key: _key);
    if (raw == null) return [];
    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list.map((e) => LocationPoint.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      return [];
    }
  }

  static Future<void> clear() => _storage.delete(key: _key);

  static Future<void> _write(List<LocationPoint> queue) =>
      _storage.write(key: _key, value: jsonEncode(queue.map((p) => p.toJson()).toList()));
}
