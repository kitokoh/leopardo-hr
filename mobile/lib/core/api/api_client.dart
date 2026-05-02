import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:leopardo_rh/core/storage/app_preferences.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';
import 'package:leopardo_rh/core/api/api_exceptions.dart';
import 'package:leopardo_rh/core/api/mock_interceptor.dart';

class ApiClient {
  static const String _defaultRemoteBaseUrl =
      'https://gestionemployerbackend.onrender.com/api/v1';
  static const String _defaultLocalAndroidBaseUrl =
      'http://10.0.2.2:8000/api/v1';
  static const String _defaultLocalLoopbackBaseUrl =
      'http://127.0.0.1:8000/api/v1';
  final Dio _dio;
  final SecureStorage _storage;
  final AppPreferences _preferences;
  final VoidCallback? onUnauthorized;

  ApiClient(this._storage, this._preferences, {this.onUnauthorized})
      : _dio = Dio(
          BaseOptions(
            baseUrl: resolveBaseUrl(),
            connectTimeout: const Duration(seconds: 20),
            receiveTimeout: const Duration(seconds: 20),
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

          options.headers['Accept-Language'] = preferredLanguage.isNotEmpty
              ? preferredLanguage
              : PlatformDispatcher.instance.locale.languageCode.toLowerCase();

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
        message = (data['localized_message'] ?? data['message'] ?? message)
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
