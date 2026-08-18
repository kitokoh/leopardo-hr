import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:hive_flutter/hive_flutter.dart';

/// Audit #1700 : le token de session ne doit JAMAIS être écrit au repos dans
/// un box Hive non chiffré (l'ancien « fallback » écrivait systématiquement
/// le JWT dans `offlineCache`, lisible par quiconque a accès aux fichiers de
/// l'appareil ou à une sauvegarde extraite). Le token ne vit que dans
/// flutter_secure_storage (Keystore/Keychain) + un cache mémoire pour la
/// durée de vie du process.
class SecureStorage {
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  // T084 (QA 2026-08-15) : les deux systèmes d'auth (sanctum /api/v1 et
  // user_api /user/*) partageaient la MÊME clé 'auth_token' → les sessions
  // s'écrasaient. Clés désormais distinctes ; 'auth_token' reste lu en
  // fallback (migration de lecture pour les sessions existantes).
  static const String _tokenKey = 'auth_token_employee';
  static const String _userTokenKey = 'auth_token_user';
  static const String _legacyTokenKey = 'auth_token';
  static const Duration _timeout = Duration(seconds: 2);
  static const String _legacyHiveBox = 'offlineCache';
  static const String _legacyHiveKey = 'auth_token';
  String? _cachedToken;
  String? _cachedUserToken;

  /// Audit #1700 : purge ponctuelle du miroir Hive legacy — les anciennes
  /// versions écrivaient le JWT dans le box `offlineCache` non chiffré.
  /// Les installations mises à niveau doivent nettoyer ce résidu au repos
  /// (idempotent : no-op si le box n'est pas ouvert ou la clé absente).
  static void purgeLegacyHiveToken() {
    try {
      if (Hive.isBoxOpen(_legacyHiveBox)) {
        Hive.box(_legacyHiveBox).delete(_legacyHiveKey);
      }
    } catch (_) {
      // ignore : nettoyage best-effort, ne doit jamais faire échouer le boot
    }
  }

  Future<void> saveToken(String token) async {
    _cachedToken = token;

    try {
      await _storage.write(key: _tokenKey, value: token).timeout(_timeout);
    } catch (_) {
      // Cache mémoire conservé ; un échec d'écriture secure storage ne doit
      // pas faire échouer le login, mais le token ne sera pas persistant.
    }
  }

  Future<String?> getToken() async {
    if (_cachedToken != null && _cachedToken!.isNotEmpty) {
      return _cachedToken;
    }

    try {
      var token = await _storage.read(key: _tokenKey).timeout(_timeout);
      if (token == null || token.isEmpty) {
        // Migration de lecture (T084) : sessions écrites sous l'ancienne clé.
        token = await _storage.read(key: _legacyTokenKey).timeout(_timeout);
      }
      if (token != null && token.isNotEmpty) {
        _cachedToken = token;
        return token;
      }
    } catch (_) {
      // ignore : secure storage indisponible
    }

    return null;
  }

  Future<void> deleteToken() async {
    _cachedToken = null;

    try {
      await _storage.delete(key: _tokenKey).timeout(_timeout);
      await _storage.delete(key: _legacyTokenKey).timeout(_timeout);
    } catch (_) {
      // ignore
    }
  }

  // ── Session user_api (/user/*, comptes sans entreprise — T084) ──────────
  Future<void> saveUserToken(String token) async {
    _cachedUserToken = token;

    try {
      await _storage.write(key: _userTokenKey, value: token).timeout(_timeout);
    } catch (_) {
      // Cache mémoire conservé (même politique que saveToken).
    }
  }

  Future<String?> getUserToken() async {
    if (_cachedUserToken != null && _cachedUserToken!.isNotEmpty) {
      return _cachedUserToken;
    }

    try {
      final token = await _storage.read(key: _userTokenKey).timeout(_timeout);
      if (token != null && token.isNotEmpty) {
        _cachedUserToken = token;
        return token;
      }
    } catch (_) {
      // ignore : secure storage indisponible
    }

    return null;
  }

  Future<void> deleteUserToken() async {
    _cachedUserToken = null;

    try {
      await _storage.delete(key: _userTokenKey).timeout(_timeout);
    } catch (_) {
      // ignore
    }
  }

  Future<void> clearAll() async {
    // #4960 : _cachedUserToken n'était pas purgé → après un logout via
    // clearAll(), getUserToken() retournait encore le jeton user_api depuis
    // le cache mémoire (session `/user/*` fantôme).
    _cachedToken = null;
    _cachedUserToken = null;

    try {
      await _storage.deleteAll().timeout(_timeout);
    } catch (_) {
      // ignore
    }
  }
}
