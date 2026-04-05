import 'dart:ui';
import 'package:dio/dio.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';
import 'package:leopardo_rh/core/api/api_exceptions.dart';
import 'package:leopardo_rh/core/api/mock_interceptor.dart';

class ApiClient {
  final Dio _dio;
  final SecureStorage _storage;
  final VoidCallback? onUnauthorized;

  ApiClient(this._storage, {this.onUnauthorized})
      : _dio = Dio(BaseOptions(
          baseUrl: const String.fromEnvironment('API_BASE_URL', defaultValue: 'http://localhost:8000/api/v1'),
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 10),
          headers: {'Accept': 'application/json'},
        )) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storage.getToken();
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
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

  DioException _handleError(DioException e) {
    String message = "Impossible de se connecter au serveur";
    String? code;
    
    if (e.response?.statusCode == 404 || e.response?.statusCode == 501) {
      message = "Fonction bientôt disponible";
      code = "NOT_IMPLEMENTED";
    } else if (e.response?.statusCode == 403) {
      message = "Compte suspendu — contactez votre employeur";
      code = "FORBIDDEN";
    } else if (e.response != null && e.response?.data != null) {
      if (e.response?.data is Map) {
        message = e.response?.data['message'] ?? message;
        code = e.response?.data['error'];
      }
    } else if (e.type == DioExceptionType.connectionTimeout) {
      message = "Délai de connexion dépassé";
    }

    throw ApiException(message, statusCode: e.response?.statusCode, code: code);
  }
}
