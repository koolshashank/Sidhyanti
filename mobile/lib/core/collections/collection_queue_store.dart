import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../models/queued_collection.dart';

/// Local-only queue of payments that failed to reach the server because
/// there was no network -- retried by CollectionSyncService until each one
/// succeeds. Same shape as LocationQueueStore (one JSON-array key in the
/// existing secure-storage keychain), so an app kill/restart while offline
/// never loses a collection a field officer already told the client was
/// recorded.
class CollectionQueueStore {
  static const _storage = FlutterSecureStorage();
  static const _key = 'collection_payment_queue';

  static Future<void> enqueue(QueuedCollection payment) async {
    final queue = await peekAll();
    queue.add(payment);
    await _write(queue);
  }

  static Future<List<QueuedCollection>> peekAll() async {
    final raw = await _storage.read(key: _key);
    if (raw == null) return [];
    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list.map((e) => QueuedCollection.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      return [];
    }
  }

  static Future<void> replaceAll(List<QueuedCollection> queue) => _write(queue);

  static Future<void> clear() => _storage.delete(key: _key);

  static Future<void> _write(List<QueuedCollection> queue) =>
      _storage.write(key: _key, value: jsonEncode(queue.map((p) => p.toJson()).toList()));
}
