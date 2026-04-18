import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:hive_flutter/hive_flutter.dart';

class SecureStorage {
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  static const String _tokenKey = 'auth_token';
  static const Duration _timeout = Duration(seconds: 2);
  String? _cachedToken;

  Box<dynamic> get _box => Hive.box('offlineCache');

  Future<void> saveToken(String token) async {
    _cachedToken = token;
    await _box.put(_tokenKey, token);

    try {
      await _storage.write(key: _tokenKey, value: token).timeout(_timeout);
    } catch (_) {
      // Keep Hive + memory fallback when secure storage is unavailable/slow.
    }
  }

  Future<String?> getToken() async {
    if (_cachedToken != null && _cachedToken!.isNotEmpty) {
      return _cachedToken;
    }

    try {
      final token = await _storage.read(key: _tokenKey).timeout(_timeout);
      if (token != null && token.isNotEmpty) {
        _cachedToken = token;
        await _box.put(_tokenKey, token);
        return token;
      }
    } catch (_) {
      // Fall back to Hive below.
    }

    final token = _box.get(_tokenKey) as String?;
    _cachedToken = token;
    return token;
  }

  Future<void> deleteToken() async {
    _cachedToken = null;
    await _box.delete(_tokenKey);

    try {
      await _storage.delete(key: _tokenKey).timeout(_timeout);
    } catch (_) {
      // Ignore secure storage cleanup failures when fallback storage is already cleared.
    }
  }
  
  Future<void> clearAll() async {
    _cachedToken = null;
    await _box.delete(_tokenKey);

    try {
      await _storage.deleteAll().timeout(_timeout);
    } catch (_) {
      // Ignore secure storage cleanup failures when fallback storage is already cleared.
    }
  }
}
