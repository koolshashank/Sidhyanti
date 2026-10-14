import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Holds the user's English/Hindi preference and persists it across app
/// restarts (same secure-storage backing ThemeController uses). Defaults to
/// English (null locale lets Flutter fall back to the device's own locale
/// if it happens to be one of the supported ones, English otherwise).
class LocaleController extends ChangeNotifier {
  static const _storageKey = 'app_locale';

  final _storage = const FlutterSecureStorage();
  Locale? _locale;

  LocaleController() {
    _restore();
  }

  Locale? get locale => _locale;

  Future<void> _restore() async {
    final saved = await _storage.read(key: _storageKey);
    if (saved != null) {
      _locale = Locale(saved);
      notifyListeners();
    }
  }

  Future<void> setLocale(Locale? locale) async {
    if (_locale == locale) return;
    _locale = locale;
    notifyListeners();
    if (locale == null) {
      await _storage.delete(key: _storageKey);
    } else {
      await _storage.write(key: _storageKey, value: locale.languageCode);
    }
  }
}
