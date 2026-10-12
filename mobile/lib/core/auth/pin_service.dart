import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Optional app-lock PIN -- field staff phones get shared/left around, so a
/// quick 4-digit PIN gate can be turned on from the Account screen. Backed
/// by the same secure-storage keychain the session tokens already use (the
/// PIN itself is stored as-is, same as any other secret kept there -- the
/// OS keychain/keystore is what actually protects it at rest).
class PinService {
  static const _storage = FlutterSecureStorage();
  static const _pinKey = 'app_lock_pin';

  static Future<bool> hasPin() async => (await _storage.read(key: _pinKey)) != null;

  static Future<void> setPin(String pin) => _storage.write(key: _pinKey, value: pin);

  static Future<bool> verifyPin(String pin) async => (await _storage.read(key: _pinKey)) == pin;

  static Future<void> clearPin() => _storage.delete(key: _pinKey);
}
