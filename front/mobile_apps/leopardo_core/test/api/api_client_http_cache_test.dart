// ============================================================
// ApiClient HTTP cache tests — RTMX (#5407) : GET conditionnels
// ETag/If-None-Match + 304 résolu avec le corps caché.
//
// Le serveur (HttpCacheMiddleware, #5277) pose un ETag fort (sha1 du corps)
// sur les réponses JSON 2xx et répond 304 quand le client envoie
// `If-None-Match` correspondant. Le client mémorise ETag + corps par URL et
// traite le 304 comme un succès dont le corps est le corps caché.
// ============================================================

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';

/// Intercepteur simulant HttpCacheMiddleware (#5277) : 200 + ETag sur un
/// contenu donné, 304 quand If-None-Match correspond au dernier ETag servi.
class _HttpCacheSimulator extends Interceptor {
  _HttpCacheSimulator();

  final requests = <RequestOptions>[];
  Object _body = const {
    'data': {'version': 0}
  };

  void setBody(Object body) {
    _body = body;
  }

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    requests.add(options);
    // Le serveur recalcule l'ETag sur le corps COURANT (comme
    // HttpCacheMiddleware : sha1 du corps) — un contenu modifié ne peut
    // jamais produire un faux 304.
    final version = (_body as Map)['data']['version'];
    final currentEtag = '"v$version"';
    final ifNoneMatch = options.headers['If-None-Match']?.toString();
    if (ifNoneMatch != null && ifNoneMatch == currentEtag) {
      handler.resolve(
        Response(requestOptions: options, statusCode: 304),
      );
      return;
    }
    handler.resolve(
      Response(
        requestOptions: options,
        statusCode: 200,
        headers: Headers.fromMap({
          'etag': [currentEtag]
        }),
        data: _body,
      ),
    );
  }
}

void main() {
  late ApiClient client;
  late _HttpCacheSimulator simulator;

  setUp(() {
    client = ApiClient(SecureStorage(), AppPreferences());
    simulator = _HttpCacheSimulator();
    client.dio.interceptors.add(simulator);
  });

  test(
      'GET 200 + ETag : la relecture envoie If-None-Match et un 304 sert le corps caché',
      () async {
    final first = await client.requestWithRetry('/attendance/today');
    expect(first.statusCode, 200);
    expect((first.data as Map)['data']['version'], 0);

    final second = await client.requestWithRetry('/attendance/today');

    expect(simulator.requests.length, 2);
    expect(simulator.requests[1].headers['If-None-Match'], '"v0"',
        reason: 'le client doit envoyer If-None-Match sur la relecture');
    expect(second.statusCode, 304,
        reason: 'le 304 est un succès (validateStatus étendu)');
    expect((second.data as Map)['data']['version'], 0,
        reason: 'le 304 retourne le corps caché, pas un corps vide');
  });

  test('GET sans header ETag : aucun If-None-Match à la relecture', () async {
    final noEtag = _NoEtagInterceptor();
    client.dio.interceptors.clear();
    client.dio.interceptors.add(noEtag);

    await client.requestWithRetry('/attendance/config');
    await client.requestWithRetry('/attendance/config');

    expect(noEtag.requests.length, 2);
    expect(noEtag.requests[1].headers.containsKey('If-None-Match'), isFalse,
        reason: 'pas de cache sans ETag serveur');
  });

  test('contenu modifié : le nouvel ETag remplace l’ancien en cache', () async {
    await client.requestWithRetry('/attendance/today');
    simulator.setBody(const {
      'data': {'version': 1}
    });

    final updated = await client.requestWithRetry('/attendance/today');

    expect(simulator.requests[1].headers['If-None-Match'], '"v0"',
        reason: 'l’ancien ETag est envoyé');
    expect(updated.statusCode, 200);
    expect((updated.data as Map)['data']['version'], 1,
        reason: 'le nouveau corps remplace l’ancien');

    final third = await client.requestWithRetry('/attendance/today');
    expect(simulator.requests[2].headers['If-None-Match'], '"v1"',
        reason: 'le cache pointe désormais sur le nouvel ETag');
    expect(third.statusCode, 304);
    expect((third.data as Map)['data']['version'], 1);
  });

  test('clearHttpCache : plus d’If-None-Match après vidage (logout)', () async {
    await client.requestWithRetry('/attendance/today');

    client.clearHttpCache();

    final after = await client.requestWithRetry('/attendance/today');
    expect(simulator.requests[1].headers.containsKey('If-None-Match'), isFalse);
    expect(after.statusCode, 200);
  });

  test('la clé de cache distingue les query parameters (ordre indifférent)',
      () async {
    await client.requestWithRetry(
      '/me/daily-summary',
      queryParameters: {'month': 8, 'year': 2026},
    );
    await client.requestWithRetry(
      '/me/daily-summary',
      queryParameters: {'year': 2026, 'month': 8},
    );

    expect(simulator.requests[1].headers['If-None-Match'], '"v0"',
        reason: 'même URL + même query (ordre différent) = même entrée cache');
  });

  test('une écriture (POST) ne remplit jamais le cache ETag', () async {
    await client.requestWithRetry(
      '/attendance/check-in',
      method: 'POST',
      data: {'work_type': 'normal'},
      maxRetriesOverride: 0,
    );

    final read = await client.requestWithRetry('/attendance/check-in');
    expect(read.statusCode, 200);
    expect(simulator.requests[1].headers.containsKey('If-None-Match'), isFalse,
        reason: 'POST ne produit pas d’entrée cache (clé par URL)');
  });
}

/// Intercepteur sans ETag (ancien serveur) — vérifie l’absence d’If-None-Match.
class _NoEtagInterceptor extends Interceptor {
  final requests = <RequestOptions>[];

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    requests.add(options);
    handler.resolve(
      Response(
        requestOptions: options,
        statusCode: 200,
        data: {
          'data': {'ok': true}
        },
      ),
    );
  }
}
