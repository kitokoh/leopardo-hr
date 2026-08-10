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
  static const String _tokenKey = 'auth_token';
  static const Duration _timeout = Duration(seconds: 2);
  static const String _legacyHiveBox = 'offlineCache';
  static const String _legacyHiveKey = 'auth_token';
  String? _cachedToken;

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
      final token = await _storage.read(key: _tokenKey).timeout(_timeout);
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
    } catch (_) {
      // ignore
    }
  }

  Future<void> clearAll() async {
    _cachedToken = null;

    try {
      await _storage.deleteAll().timeout(_timeout);
    } catch (_) {
      // ignore
    }
  }
}
