import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/i18n/error_messages.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';
import 'package:leopardo_core/core/api/mock_interceptor.dart';

typedef RetryCallback = void Function(int attempt, Object error);

class ApiClient {
  // #4524 : plus d'URL distante en dur — le backend cible est fourni par le
  // define API_BASE_URL (workflows). En debug, défaut = serveur local (#4530).
  static const String _defaultLocalAndroidBaseUrl =
      'http://10.0.2.2:8000/api/v1';
  static const String _defaultLocalLoopbackBaseUrl =
      'http://127.0.0.1:8000/api/v1';

  static const int _defaultMaxRetries = 2;
  static const int _loginMaxRetries = 3;
  static const Duration _defaultTimeout = Duration(seconds: 20);
  static const Duration _loginTimeout = Duration(seconds: 60);

  final Dio _dio;
  final SecureStorage _storage;
  final AppPreferences _preferences;
  final VoidCallback? onUnauthorized;

  ApiClient(this._storage, this._preferences, {this.onUnauthorized})
    : _dio = Dio(
        BaseOptions(
          baseUrl: resolveBaseUrl(),
          connectTimeout: _defaultTimeout,
          receiveTimeout: _defaultTimeout,
          headers: {'Accept': 'application/json'},
        ),
      ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          // T084 : session user_api (/user/*) → jeton dédié auth_token_user.
          // Le header reste en place : onError le relit pour supprimer le bon
          // jeton en cas de 401 (le serveur l'ignore).
          final useUserSession = options.headers['X-User-Session'] == 'true';
          final token = useUserSession
              ? await _storage.getUserToken()
              : await _storage.getToken();
          final preferredLanguage = _preferences.preferredLanguage;

          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }

          options.headers['Accept-Language'] = preferredLanguage.isNotEmpty
              ? preferredLanguage
              : PlatformDispatcher.instance.locale.languageCode.toLowerCase();

          return handler.next(options);
        },
        onError: (DioException e, handler) async {
          if (e.response?.statusCode == 401) {
            final useUserSession =
                e.requestOptions.headers['X-User-Session'] == 'true';
            if (useUserSession) {
              await _storage.deleteUserToken();
            } else {
              await _storage.deleteToken();
              if (onUnauthorized != null) {
                onUnauthorized!();
              }
            }
          }
          // #4960 : on ne convertit PAS ici (l'ancien `_handleError` jetait
          // une ApiException, ce qui neutralisait la classification et donc
          // le retry de `requestWithRetry` — les 502/503/timeouts n'étaient
          // jamais re-tentés). La DioException originale circule ; la
          // conversion en ApiException localisée se fait après épuisement
          // des tentatives (fin de requestWithRetry/downloadWithRetry).
          return handler.next(e);
        },
      ),
    );

    // Never activate the mock interceptor in release builds: a release APK
    // compiled with --dart-define=API_BASE_URL=mock (misconfigured CI, demo
    // build distributed by mistake) must not silently serve fake data in
    // production. See issue #1470 / audit T14 (2026-04-22).
    if (!kReleaseMode &&
        const String.fromEnvironment('API_BASE_URL') == 'mock') {
      importMockInterceptor();
    }
  }

  void importMockInterceptor() {
    _dio.interceptors.insert(0, MockInterceptor());
  }

  Dio get dio => _dio;

  static String resolveBaseUrl() {
    const configured = String.fromEnvironment('API_BASE_URL', defaultValue: '');

    if (configured.isNotEmpty) {
      return configured;
    }

    // #4524 : un build release/profile sans API_BASE_URL échouait en silence
    // vers le backend legacy (gestionemployerbackend.onrender.com). Désormais
    // l'erreur est explicite : le CI passe toujours le define, un build local
    // release doit le fournir.
    if (kReleaseMode || (!kIsWeb && !kDebugMode)) {
      throw StateError(
        'API_BASE_URL must be provided in release/profile builds '
        '(silent fallback removed, issue #4524).',
      );
    }

    // #4530 : en debug, défaut = serveur local (10.0.2.2 émulateur Android,
    // loopback ailleurs). Pointer un backend distant exige API_BASE_URL.
    if (kIsWeb) {
      return _defaultLocalLoopbackBaseUrl;
    }

    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return _defaultLocalAndroidBaseUrl;
      case TargetPlatform.iOS:
      case TargetPlatform.macOS:
      case TargetPlatform.windows:
      case TargetPlatform.linux:
      case TargetPlatform.fuchsia:
        return _defaultLocalLoopbackBaseUrl;
    }
  }

  /// Performs a request with automatic retry for cold-start (502/503/504)
  /// and network errors only when the HTTP method is idempotent. Mutations
  /// default to one attempt; callers may opt into a different policy only
  /// with an explicit [maxRetriesOverride] and an idempotency guarantee.
  Future<Response<T>> requestWithRetry<T>(
    String path, {
    String method = 'GET',
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    bool isLoginRequest = false,
    bool useUserSession = false,
    int? maxRetriesOverride,
    Duration? timeoutOverride,
    RetryCallback? onRetry,
  }) async {
    // A response can be lost after the server has committed a mutation.
    // Retrying POST/PATCH (and any future mutation method) can therefore
    // duplicate accounts, AI charges or published content. Only methods with
    // HTTP idempotency semantics keep the automatic transient-error retry.
    final normalizedMethod = method.toUpperCase();
    final isIdempotentMethod = const {
      'GET',
      'HEAD',
      'OPTIONS',
      'PUT',
      'DELETE',
    }.contains(normalizedMethod);
    final maxRetries =
        maxRetriesOverride ??
        (isIdempotentMethod
            ? (isLoginRequest ? _loginMaxRetries : _defaultMaxRetries)
            : 0);
    final timeout =
        timeoutOverride ?? (isLoginRequest ? _loginTimeout : _defaultTimeout);

    Object? lastError;

    for (int attempt = 0; attempt <= maxRetries; attempt++) {
      try {
        final requestOptions = (options ?? Options()).copyWith(
          method: method,
          sendTimeout: timeout,
          receiveTimeout: timeout,
        );
        if (useUserSession) {
          // Consommé par l'intercepteur pour choisir le jeton user_api.
          requestOptions.headers = {
            ...?requestOptions.headers,
            'X-User-Session': 'true',
          };
        }
        final response = await _dio.request<T>(
          path,
          data: data,
          queryParameters: queryParameters,
          options: requestOptions,
        );

        final statusCode = response.statusCode ?? 0;
        if (_isColdStartStatus(statusCode) && attempt < maxRetries) {
          onRetry?.call(
            attempt + 1,
            ApiException(
              'Server returned $statusCode',
              statusCode: statusCode,
              code: 'COLD_START',
            ),
          );
          await _backoff(attempt);
          continue;
        }

        return response;
      } on DioException catch (e) {
        lastError = e;

        final isColdStart = _isColdStartStatus(e.response?.statusCode ?? 0);
        final isTimeout =
            e.type == DioExceptionType.connectionTimeout ||
            e.type == DioExceptionType.receiveTimeout ||
            e.type == DioExceptionType.sendTimeout;
        final isNetwork = e.type == DioExceptionType.connectionError;

        if ((isColdStart || isTimeout || isNetwork) && attempt < maxRetries) {
          onRetry?.call(attempt + 1, e);
          await _backoff(attempt);
          continue;
        }

        rethrow;
      }
    }

    if (lastError is DioException) {
      // Conversion en ApiException localisée uniquement après épuisement
      // des tentatives (la classification isColdStart/timeout/network se
      // fait sur la DioException originale, cf. interceptor onError #4960).
      throw _handleError(lastError);
    }
    throw ApiException('Request failed after retries');
  }

  /// Téléchargement avec retry + garde anti-page-d'erreur (issue #3289).
  ///
  /// Remplace les `dio.download` directs : retry GET idempotent sur
  /// cold-start/timeout/réseau, validation du status 2xx, suppression du
  /// fichier partiel ou de la page d'erreur JSON écrite localement, et
  /// mapping d'erreur cohérent avec [requestWithRetry].
  Future<String> downloadWithRetry(
    String path,
    String savePath, {
    Options? options,
    Duration? timeoutOverride,
    RetryCallback? onRetry,
  }) async {
    final maxRetries = _defaultMaxRetries;
    final timeout = timeoutOverride ?? _defaultTimeout;

    Object? lastError;

    for (int attempt = 0; attempt <= maxRetries; attempt++) {
      try {
        final requestOptions = (options ?? Options()).copyWith(
          responseType: ResponseType.bytes,
          sendTimeout: timeout,
          receiveTimeout: timeout,
        );

        final response = await _dio.download(
          path,
          savePath,
          options: requestOptions,
          deleteOnError: true,
        );

        final statusCode = response.statusCode ?? 0;
        if (statusCode < 200 || statusCode >= 300) {
          await _deleteDownload(savePath);

          throw DioException.badResponse(
            statusCode: statusCode,
            requestOptions: response.requestOptions,
            response: response,
          );
        }

        // Une réponse 2xx peut quand même être une page d'erreur (reverse
        // proxy) : un fichier vide ou minuscule n'est pas un PDF valide.
        final file = File(savePath);
        if (!await file.exists() || await file.length() < 1) {
          await _deleteDownload(savePath);

          throw ApiException(
            'Download produced an empty file',
            statusCode: statusCode,
            code: 'EMPTY_DOWNLOAD',
          );
        }

        return savePath;
      } on DioException catch (e) {
        lastError = e;

        final isRetryable =
            _isColdStartStatus(e.response?.statusCode ?? 0) ||
            e.type == DioExceptionType.connectionTimeout ||
            e.type == DioExceptionType.receiveTimeout ||
            e.type == DioExceptionType.sendTimeout ||
            e.type == DioExceptionType.connectionError;

        if (isRetryable && attempt < maxRetries) {
          onRetry?.call(attempt + 1, e);
          await _backoff(attempt);
          continue;
        }

        await _deleteDownload(savePath);
        // Conversion en ApiException localisée (cf. #4960) — le retry a déjà
        // été tenté au-dessus si l'erreur était transitoire.
        throw _handleError(e);
      }
    }

    await _deleteDownload(savePath);
    if (lastError is DioException) {
      throw _handleError(lastError);
    }
    throw lastError ?? ApiException(localizedErrorCode('DOWNLOAD_FAILED'));
  }

  Future<void> _deleteDownload(String savePath) async {
    try {
      final file = File(savePath);
      if (await file.exists()) {
        await file.delete();
      }
    } catch (_) {
      // Nettoyage best-effort — ne jamais masquer l'erreur d'origine.
    }
  }

  bool _isColdStartStatus(int statusCode) =>
      statusCode == 502 || statusCode == 503 || statusCode == 504;

  Future<void> _backoff(int attempt) => Future.delayed(
    Duration(milliseconds: (3000 * (attempt + 1)).clamp(0, 10000)),
  );

  DioException _handleError(DioException e) {
    // #4408 : messages de repli localisés par la locale appareil (catalogue
    // core) — avant : FR codé en dur affiché dans les 4 locales.
    String message = localizedErrorCode('CONNECTION');
    String? code;

    if (e.response?.statusCode == 404 || e.response?.statusCode == 501) {
      message = localizedErrorCode('NOT_IMPLEMENTED');
      code = 'NOT_IMPLEMENTED';
    } else if (e.response?.statusCode == 403) {
      // Issue #2743 — un 403 n'est pas toujours une suspension : on distingue
      // la suspension explicite (payload) du simple défaut de permission.
      final data = e.response?.data;
      final isSuspended =
          data is Map &&
          (data['suspended'] == true || data['error'] == 'ACCOUNT_SUSPENDED');
      message = localizedErrorCode(
        isSuspended ? 'ACCOUNT_SUSPENDED' : 'FORBIDDEN',
      );
      code = isSuspended ? 'ACCOUNT_SUSPENDED' : 'FORBIDDEN';
    } else if (e.response != null && e.response?.data != null) {
      if (e.response?.data is Map) {
        final data = (e.response?.data as Map).cast<dynamic, dynamic>();
        // Le backend fournit déjà un message localisé (Accept-Language) :
        // on le préfère au catalogue de repli.
        final serverMessage = data['localized_message'] ?? data['message'];
        if (serverMessage != null) {
          message = serverMessage.toString();
        }
        code = data['error']?.toString();
      }
    } else if (e.type == DioExceptionType.connectionTimeout) {
      message = localizedErrorCode('CONNECTION_TIMEOUT');
    } else if (e.type == DioExceptionType.receiveTimeout) {
      message = localizedErrorCode('RECEIVE_TIMEOUT');
    } else if (e.type == DioExceptionType.connectionError) {
      message = localizedErrorCode('CONNECTION_ERROR');
    }

    throw ApiException(message, statusCode: e.response?.statusCode, code: code);
  }
}
