import 'package:hive_flutter/hive_flutter.dart';

class TranslationCatalogCache {
  static const String _boxName = 'offlineCache';
  static const String _catalogPrefix = 'i18n_catalog_';
  static const String _checksumPrefix = 'i18n_checksum_';
  static const String _versionPrefix = 'i18n_version_';
  static const String _updatedAtPrefix = 'i18n_updated_at_';

  static final Map<String, Object?> _memory = <String, Object?>{};

  Box<dynamic>? get _boxOrNull =>
      Hive.isBoxOpen(_boxName) ? Hive.box(_boxName) : null;

  Object? _read(String key) {
    final value = _boxOrNull?.get(key) ?? _memory[key];
    if (value != null) {
      _memory[key] = value;
    }

    return value;
  }

  Future<void> _write(String key, Object? value) async {
    _memory[key] = value;
    await _boxOrNull?.put(key, value);
  }

  Map<String, dynamic>? readCatalog(String locale) {
    final value = _read('$_catalogPrefix$locale');

    if (value is Map) {
      return value.cast<String, dynamic>();
    }

    return null;
  }

  String? readChecksum(String locale) {
    final value = _read('$_checksumPrefix$locale');

    return value is String && value.isNotEmpty ? value : null;
  }

  String? readVersion(String locale) {
    final value = _read('$_versionPrefix$locale');

    return value is String && value.isNotEmpty ? value : null;
  }

  String? readUpdatedAt(String locale) {
    final value = _read('$_updatedAtPrefix$locale');

    return value is String && value.isNotEmpty ? value : null;
  }

  Future<void> save({
    required String locale,
    required Map<String, dynamic> catalog,
    required String checksum,
    required String version,
    required String updatedAt,
  }) async {
    await _write('$_catalogPrefix$locale', catalog);
    await _write('$_checksumPrefix$locale', checksum);
    await _write('$_versionPrefix$locale', version);
    await _write('$_updatedAtPrefix$locale', updatedAt);
  }
}
