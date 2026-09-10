import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Holds the user's Light/Dark/System preference and persists it across app
/// restarts (same secure-storage backing as auth tokens, for consistency).
class ThemeController extends ChangeNotifier {
  static const _storageKey = 'theme_mode';

  final _storage = const FlutterSecureStorage();
  ThemeMode _mode = ThemeMode.system;

  ThemeController() {
    _restore();
  }

  ThemeMode get mode => _mode;

  Future<void> _restore() async {
    final saved = await _storage.read(key: _storageKey);
    _mode = switch (saved) {
      'light' => ThemeMode.light,
      'dark' => ThemeMode.dark,
      _ => ThemeMode.system,
    };
    notifyListeners();
  }

  Future<void> setMode(ThemeMode mode) async {
    if (_mode == mode) return;
    _mode = mode;
    notifyListeners();
    await _storage.write(key: _storageKey, value: mode.name);
  }
}
