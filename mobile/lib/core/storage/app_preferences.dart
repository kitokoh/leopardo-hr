import 'package:hive_flutter/hive_flutter.dart';

class AppPreferences {
  static const String _boxName = 'offlineCache';
  static const String _biometricEnabledKey = 'settings_biometric_enabled';
  static const String _fingerprintEnabledKey = 'settings_fingerprint_enabled';
  static const String _faceEnabledKey = 'settings_face_enabled';
  static const String _attendanceConsentKey = 'settings_attendance_consent';
  static const String _biometricNoteKey = 'settings_biometric_note';

  Box<dynamic> get _box => Hive.box(_boxName);

  bool get biometricEnabled => _box.get(_biometricEnabledKey, defaultValue: false) as bool;
  bool get fingerprintEnabled => _box.get(_fingerprintEnabledKey, defaultValue: false) as bool;
  bool get faceEnabled => _box.get(_faceEnabledKey, defaultValue: false) as bool;
  bool get attendanceConsent => _box.get(_attendanceConsentKey, defaultValue: false) as bool;
  String get biometricNote => (_box.get(_biometricNoteKey, defaultValue: '') as String).trim();

  Future<void> saveBiometricSettings({
    required bool biometricEnabled,
    required bool fingerprintEnabled,
    required bool faceEnabled,
    required bool attendanceConsent,
    required String biometricNote,
  }) async {
    await _box.put(_biometricEnabledKey, biometricEnabled);
    await _box.put(_fingerprintEnabledKey, fingerprintEnabled);
    await _box.put(_faceEnabledKey, faceEnabled);
    await _box.put(_attendanceConsentKey, attendanceConsent);
    await _box.put(_biometricNoteKey, biometricNote.trim());
  }
}
