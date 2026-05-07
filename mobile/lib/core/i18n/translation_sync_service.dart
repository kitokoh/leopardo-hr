import 'package:dio/dio.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/core/i18n/translation_catalog_cache.dart';

class TranslationSyncResult {
  const TranslationSyncResult({
    required this.locale,
    required this.version,
    required this.checksum,
    required this.catalog,
    required this.fromCache,
  });

  final String locale;
  final String version;
  final String checksum;
  final Map<String, dynamic> catalog;
  final bool fromCache;
}

class TranslationSyncService {
  TranslationSyncService(this._apiClient, this._cache);

  final ApiClient _apiClient;
  final TranslationCatalogCache _cache;

  String normalizeLocale(String locale) {
    final normalized = locale.trim().replaceAll('_', '-').toLowerCase();

    if (normalized.length < 2) {
      return 'fr';
    }

    final base = normalized.substring(0, 2);

    return const {'fr', 'ar', 'tr', 'en'}.contains(base) ? base : 'fr';
  }

  Future<TranslationSyncResult> sync(String locale) async {
    final resolvedLocale = normalizeLocale(locale);
    final knownChecksum = _cache.readChecksum(resolvedLocale);

    try {
      final response = await _apiClient.dio.get(
        '/i18n/catalog/$resolvedLocale',
        options: Options(
          headers: {
            if (knownChecksum != null && knownChecksum.isNotEmpty)
              'If-None-Match': 'W/"$knownChecksum"',
          },
        ),
      );

      final data = (response.data as Map).cast<String, dynamic>();
      final payload = (data['data'] as Map).cast<String, dynamic>();
      final catalog = (payload['catalog'] as Map).cast<String, dynamic>();
      final checksum = payload['checksum']?.toString() ?? '';
      final version = payload['version']?.toString() ?? '1.0.0';
      final updatedAt = payload['updated_at']?.toString() ?? '';

      await _cache.save(
        locale: resolvedLocale,
        catalog: catalog,
        checksum: checksum,
        version: version,
        updatedAt: updatedAt,
      );

      return TranslationSyncResult(
        locale: resolvedLocale,
        version: version,
        checksum: checksum,
        catalog: catalog,
        fromCache: false,
      );
    } on DioException catch (error) {
      if (error.response?.statusCode == 304) {
        final cachedCatalog = _cache.readCatalog(resolvedLocale) ?? <String, dynamic>{};
        return TranslationSyncResult(
          locale: resolvedLocale,
          version: _cache.readVersion(resolvedLocale) ?? '1.0.0',
          checksum: knownChecksum ?? '',
          catalog: cachedCatalog,
          fromCache: true,
        );
      }

      final cachedCatalog = _cache.readCatalog(resolvedLocale);

      if (cachedCatalog != null) {
        return TranslationSyncResult(
          locale: resolvedLocale,
          version: _cache.readVersion(resolvedLocale) ?? '1.0.0',
          checksum: _cache.readChecksum(resolvedLocale) ?? '',
          catalog: cachedCatalog,
          fromCache: true,
        );
      }

      rethrow;
    }
  }
}
