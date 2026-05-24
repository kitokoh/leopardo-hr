import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:leopardo_rh/core/storage/app_preferences.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';
import 'package:leopardo_rh/core/api/api_exceptions.dart';
import 'package:leopardo_rh/core/api/mock_interceptor.dart';

typedef RetryCallback = void Function(int attempt, Object error);

class ApiClient {
  static const String _defaultRemoteBaseUrl =
      'https://gestionemployerbackend.onrender.com/api/v1';
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
          final token = await _storage.getToken();
          final preferredLanguage = _preferences.preferredLanguage;

          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }

          options.headers['Accept-Language'] =
              preferredLanguage.isNotEmpty
                  ? preferredLanguage
                  : PlatformDispatcher.instance.locale.languageCode
                      .toLowerCase();

          return handler.next(options);
        },
        onError: (DioException e, handler) async {
          if (e.response?.statusCode == 401) {
            await _storage.deleteToken();
            if (onUnauthorized != null) {
              onUnauthorized!();
            }
          }
          return handler.next(_handleError(e));
        },
      ),
    );

    if (const String.fromEnvironment('API_BASE_URL') == 'mock') {
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

    if (kReleaseMode) {
      return _defaultRemoteBaseUrl;
    }

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
  /// and network errors. Uses extended timeouts for login requests.
  Future<Response<T>> requestWithRetry<T>(
    String path, {
    String method = 'GET',
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    bool isLoginRequest = false,
    RetryCallback? onRetry,
  }) async {
    final maxRetries = isLoginRequest ? _loginMaxRetries : _defaultMaxRetries;
    final timeout = isLoginRequest ? _loginTimeout : _defaultTimeout;

    Object? lastError;

    for (int attempt = 0; attempt <= maxRetries; attempt++) {
      try {
        final response = await _dio.request<T>(
          path,
          data: data,
          queryParameters: queryParameters,
          options: (options ?? Options()).copyWith(
            method: method,
            sendTimeout: timeout,
            receiveTimeout: timeout,
          ),
        );

        final statusCode = response.statusCode ?? 0;
        if (_isColdStartStatus(statusCode) && attempt < maxRetries) {
          onRetry?.call(attempt + 1, ApiException(
            'Server returned $statusCode',
            statusCode: statusCode,
            code: 'COLD_START',
          ));
          await _backoff(attempt);
          continue;
        }

        return response;
      } on DioException catch (e) {
        lastError = e;

        final isColdStart = _isColdStartStatus(e.response?.statusCode ?? 0);
        final isTimeout = e.type == DioExceptionType.connectionTimeout ||
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
      throw lastError;
    }
    throw ApiException('Request failed after retries');
  }

  bool _isColdStartStatus(int statusCode) =>
      statusCode == 502 || statusCode == 503 || statusCode == 504;

  Future<void> _backoff(int attempt) =>
      Future.delayed(Duration(milliseconds: (3000 * (attempt + 1)).clamp(0, 10000)));

  DioException _handleError(DioException e) {
    String message = "Impossible de se connecter au serveur";
    String? code;

    if (e.response?.statusCode == 404 || e.response?.statusCode == 501) {
      message = "Fonction bientôt disponible";
      code = "NOT_IMPLEMENTED";
    } else if (e.response?.statusCode == 403) {
      message = "Compte suspendu - contactez votre employeur";
      code = "FORBIDDEN";
    } else if (e.response != null && e.response?.data != null) {
      if (e.response?.data is Map) {
        final data = (e.response?.data as Map).cast<dynamic, dynamic>();
        message =
            (data['localized_message'] ?? data['message'] ?? message)
                .toString();
        code = data['error']?.toString();
      }
    } else if (e.type == DioExceptionType.connectionTimeout) {
      message = "Delai de connexion depasse";
    } else if (e.type == DioExceptionType.receiveTimeout) {
      message = "Le serveur met trop de temps a repondre";
    } else if (e.type == DioExceptionType.connectionError) {
      message = "Connexion indisponible - verifiez internet ou l'URL API";
    }

    throw ApiException(message, statusCode: e.response?.statusCode, code: code);
  }
}
