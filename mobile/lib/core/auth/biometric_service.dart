import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';

/// Optional fingerprint/face unlock for the PIN-lock screen -- only ever
/// offered as a shortcut to re-enter an existing App Lock PIN (see
/// PinService), never as a replacement for it, so "Forgot PIN? Log Out"
/// still always works as the fallback.
class BiometricService {
  static const _storage = FlutterSecureStorage();
  static const _enabledKey = 'biometric_unlock_enabled';
  static final _localAuth = LocalAuthentication();

  static Future<bool> isDeviceSupported() async {
    try {
      final canCheck = await _localAuth.canCheckBiometrics;
      final isSupported = await _localAuth.isDeviceSupported();
      return canCheck && isSupported;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> isEnabled() async => (await _storage.read(key: _enabledKey)) == 'true';

  static Future<void> setEnabled(bool enabled) => enabled ? _storage.write(key: _enabledKey, value: 'true') : _storage.delete(key: _enabledKey);

  /// Returns true only on a genuine successful biometric match.
  static Future<bool> authenticate() async {
    try {
      return await _localAuth.authenticate(
        localizedReason: 'Unlock Sidhyanti CRM',
        options: const AuthenticationOptions(biometricOnly: true, stickyAuth: true),
      );
    } catch (_) {
      return false;
    }
  }
}
