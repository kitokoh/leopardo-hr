import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';

/// Harness de test mobile (leopardo_employee, issue #1560).
///
/// Même pattern que `leopardo_hr/test/helpers/mobile_test_harness.dart` :
/// des fakes mémoire (aucun plugin, aucun channel) pour construire un
/// [ApiClient] utilisable dans les tests unitaires.
class FakeSecureStorage extends SecureStorage {
  String? _token;

  @override
  Future<String?> getToken() async => _token;

  @override
  Future<void> saveToken(String token) async {
    _token = token;
  }

  @override
  Future<void> deleteToken() async {
    _token = null;
  }

  @override
  Future<void> clearAll() async {
    _token = null;
  }
}

class FakeAppPreferences extends AppPreferences {
  FakeAppPreferences({this.language = 'fr', this.rtl = false});

  final String language;
  final bool rtl;

  @override
  String get preferredLanguage => language;

  @override
  bool get isRtl => rtl;

  @override
  bool get biometricEnabled => false;

  @override
  bool get fingerprintEnabled => false;

  @override
  bool get faceEnabled => false;

  @override
  bool get attendanceConsent => true;

  @override
  String get biometricNote => '';

  @override
  Future<void> saveBiometricSettings({
    required bool biometricEnabled,
    required bool fingerprintEnabled,
    required bool faceEnabled,
    required bool attendanceConsent,
    required String biometricNote,
  }) async {}

  @override
  Future<void> saveLocaleSettings({
    required String preferredLanguage,
    required bool isRtl,
  }) async {}

  @override
  Future<void> clearLocaleSettings() async {}
}
