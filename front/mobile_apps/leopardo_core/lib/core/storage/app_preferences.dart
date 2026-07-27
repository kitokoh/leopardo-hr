import 'package:hive_flutter/hive_flutter.dart';

class AppPreferences {
  static const String _boxName = 'offlineCache';
  static const String _biometricEnabledKey = 'settings_biometric_enabled';
  static const String _fingerprintEnabledKey = 'settings_fingerprint_enabled';
  static const String _faceEnabledKey = 'settings_face_enabled';
  static const String _attendanceConsentKey = 'settings_attendance_consent';
  static const String _biometricNoteKey = 'settings_biometric_note';
  static const String _preferredLanguageKey = 'settings_preferred_language';
  static const String _isRtlKey = 'settings_is_rtl';
  static const String _edgeNodeIdKey = 'edge_node_id';
  static const String _edgeTokenKey = 'edge_token';
  static const String _edgeBaseUrlKey = 'edge_base_url';

  static final Map<String, Object?> _memory = <String, Object?>{};

  Box<dynamic>? get _boxOrNull =>
      Hive.isBoxOpen(_boxName) ? Hive.box(_boxName) : null;

  Object? _read(String key, Object? defaultValue) {
    final box = _boxOrNull;
    if (box == null) {
      return _memory[key] ?? defaultValue;
    }

    final value = box.get(key, defaultValue: defaultValue);
    _memory[key] = value;
    return value;
  }

  Future<void> _write(String key, Object? value) async {
    _memory[key] = value;
    await _boxOrNull?.put(key, value);
  }

  Future<void> _delete(String key) async {
    _memory.remove(key);
    await _boxOrNull?.delete(key);
  }

  bool get biometricEnabled => _read(_biometricEnabledKey, false) as bool;
  bool get fingerprintEnabled => _read(_fingerprintEnabledKey, false) as bool;
  bool get faceEnabled => _read(_faceEnabledKey, false) as bool;
  bool get attendanceConsent => _read(_attendanceConsentKey, false) as bool;
  String get biometricNote => (_read(_biometricNoteKey, '') as String).trim();
  String get preferredLanguage =>
      (_read(_preferredLanguageKey, '') as String).trim();
  bool get isRtl => _read(_isRtlKey, false) as bool;

  /// Cloud-issued UUID for this device's paired Edge node (see issue #1287 /
  /// docs/edge-sync/ARCHITECTURE.md). Empty when the device has never been
  /// paired with an Edge node — [SyncService] then simply never reaches
  /// `SyncMode.edge` and behaves as cloud/offline only.
  String get edgeNodeId => (_read(_edgeNodeIdKey, '') as String).trim();

  /// Bearer secret returned once at Edge node registration
  /// (`EdgeNodeController::store()`), distinct from [edgeNodeId].
  String get edgeToken => (_read(_edgeTokenKey, '') as String).trim();

  /// Local network base URL of the paired Edge box, e.g.
  /// `http://leopardo.local:7878`. Empty means "not configured".
  String get edgeBaseUrl => (_read(_edgeBaseUrlKey, '') as String).trim();

  Future<void> saveEdgeEnrollment({
    required String edgeNodeId,
    required String edgeToken,
    required String edgeBaseUrl,
  }) async {
    await _write(_edgeNodeIdKey, edgeNodeId.trim());
    await _write(_edgeTokenKey, edgeToken.trim());
    await _write(_edgeBaseUrlKey, edgeBaseUrl.trim());
  }

  Future<void> clearEdgeEnrollment() async {
    await _delete(_edgeNodeIdKey);
    await _delete(_edgeTokenKey);
    await _delete(_edgeBaseUrlKey);
  }

  Future<void> saveBiometricSettings({
    required bool biometricEnabled,
    required bool fingerprintEnabled,
    required bool faceEnabled,
    required bool attendanceConsent,
    required String biometricNote,
  }) async {
    await _write(_biometricEnabledKey, biometricEnabled);
    await _write(_fingerprintEnabledKey, fingerprintEnabled);
    await _write(_faceEnabledKey, faceEnabled);
    await _write(_attendanceConsentKey, attendanceConsent);
    await _write(_biometricNoteKey, biometricNote.trim());
  }

  Future<void> saveLocaleSettings({
    required String preferredLanguage,
    required bool isRtl,
  }) async {
    await _write(_preferredLanguageKey, preferredLanguage.trim().toLowerCase());
    await _write(_isRtlKey, isRtl);
  }

  Future<void> clearLocaleSettings() async {
    await _delete(_preferredLanguageKey);
    await _delete(_isRtlKey);
  }
}
