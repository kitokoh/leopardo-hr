// ============================================================
// Offline Token Service — Manages tokens when offline
// ============================================================

import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Stores auth tokens securely and provides offline validation.
/// When offline, validates JWT expiry locally without Cloud call.
class OfflineTokenService {
  final FlutterSecureStorage _storage;

  static const _accessKey = 'leopardo_access_token';
  static const _refreshKey = 'leopardo_refresh_token';
  static const _profileKey = 'leopardo_user_profile';
  static const _edgeLicenseKey = 'leopardo_edge_license';

  OfflineTokenService({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  Future<void> saveTokens({
    required String accessToken,
    required String refreshToken,
  }) async {
    await _storage.write(key: _accessKey, value: accessToken);
    await _storage.write(key: _refreshKey, value: refreshToken);
  }

  Future<String?> getAccessToken() => _storage.read(key: _accessKey);
  Future<String?> getRefreshToken() => _storage.read(key: _refreshKey);

  Future<bool> isAccessTokenValid() async {
    final token = await getAccessToken();
    if (token == null) return false;
    return !_isJwtExpired(token);
  }

  Future<Map<String, dynamic>?> getLocalProfile() async {
    final raw = await _storage.read(key: _profileKey);
    if (raw == null) return null;
    return jsonDecode(raw) as Map<String, dynamic>;
  }

  Future<void> saveProfile(Map<String, dynamic> profile) async {
    await _storage.write(key: _profileKey, value: jsonEncode(profile));
  }

  Future<void> saveEdgeLicense(String signedPayload) async {
    await _storage.write(key: _edgeLicenseKey, value: signedPayload);
  }

  Future<bool> isEdgeLicenseValid() async {
    final raw = await _storage.read(key: _edgeLicenseKey);
    if (raw == null) return false;
    return !_isJwtExpired(raw);
  }

  Future<void> clearAll() async {
    await _storage.deleteAll();
  }

  bool _isJwtExpired(String token) {
    try {
      final parts = token.split('.');
      if (parts.length < 2) return true;

      final payload = utf8.decode(
        base64Url.decode(base64Url.normalize(parts[1])),
      );
      final map = jsonDecode(payload) as Map<String, dynamic>;
      final exp = map['exp'] as int?;
      if (exp == null) return false;

      return DateTime.fromMillisecondsSinceEpoch(
        exp * 1000,
      ).isBefore(DateTime.now());
    } catch (_) {
      return true;
    }
  }
}
