import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';
import 'package:leopardo_core/models/employee.dart';

/// Authentification email/mot de passe (pattern leopardo_marketing/manager).
///
/// `/accounting/*` exige `auth:sanctum` + `api.manager:comptable,principal` :
/// l'app est réservée au responsable comptabilité (RBAC porté par le compte).
class AuthRepository {
  final ApiClient apiClient;
  final SecureStorage storage;
  final AppPreferences preferences;

  AuthRepository(this.apiClient, this.storage, this.preferences);

  static const _actionTimeout = Duration(seconds: 12);
  static const _authCheckTimeout = Duration(seconds: 10);

  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await apiClient.requestWithRetry(
      '/auth/login',
      method: 'POST',
      data: {
        'email': email,
        'password': password,
        'device_name': 'Accounting App',
      },
      isLoginRequest: true,
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );

    final data = _envelope(response.data);
    final employeeJson = extractEmployeeJson(data);
    final token = extractToken(data);

    await storage.saveToken(token);

    // Hydrate depuis /auth/me pour récupérer manager_role + capabilities.
    try {
      final meResponse = await apiClient.requestWithRetry(
        '/auth/me',
        timeoutOverride: _authCheckTimeout,
        maxRetriesOverride: 0,
      );
      final meData = extractDataMap(meResponse.data);
      if (meData.isNotEmpty) {
        final employee = Employee.fromJson(meData);
        await _persistEmployeeContext(employee);
        return {'employee': employee};
      }
    } catch (_) {
      // Si /auth/me échoue on retombe sur la réponse de login.
    }

    final employee = Employee.fromJson(employeeJson);
    await _persistEmployeeContext(employee);

    return {'employee': employee};
  }

  Future<void> logout() async {
    try {
      await apiClient.requestWithRetry(
        '/auth/logout',
        method: 'POST',
        maxRetriesOverride: 0,
        timeoutOverride: _actionTimeout,
      );
    } catch (_) {
      // Ignore errors if token is already invalid.
    } finally {
      await storage.deleteToken();
      await preferences.clearLocaleSettings();
    }
  }

  Future<Map<String, dynamic>?> checkAuth() async {
    final token = await storage.getToken();
    if (token == null) return null;

    try {
      final response = await apiClient.requestWithRetry(
        '/auth/me',
        timeoutOverride: _authCheckTimeout,
        maxRetriesOverride: 0,
      );
      final data = extractDataMap(response.data);
      final employee = Employee.fromJson(data);
      await _persistEmployeeContext(employee);
      return {'employee': employee};
    } catch (e) {
      // Ne supprimer le token que sur un 401 explicite (une erreur réseau ne
      // doit pas détruire la session).
      final isAuthError = e is ApiException &&
          (e.statusCode == 401 || e.code == 'UNAUTHENTICATED');
      if (isAuthError) {
        await storage.deleteToken();
        await preferences.clearLocaleSettings();
      }
      return null;
    }
  }

  Future<void> _persistEmployeeContext(Employee employee) async {
    await preferences.saveLocaleSettings(
      preferredLanguage: employee.language,
      isRtl: employee.isRtl,
    );
  }

  static Map<String, dynamic> _envelope(dynamic payload) =>
      payload is Map ? payload.cast<String, dynamic>() : const <String, dynamic>{};

  static Map<String, dynamic> extractEmployeeJson(
    Map<String, dynamic> payload,
  ) {
    final data = payload['data'];
    if (data is Map) {
      final user = data['user'];
      if (user is Map) {
        return user.cast<String, dynamic>();
      }

      return data.cast<String, dynamic>();
    }

    throw const FormatException('Invalid auth payload: missing employee data');
  }

  static String extractToken(Map<String, dynamic> payload) {
    final rootToken = payload['token'];
    if (rootToken is String && rootToken.isNotEmpty) {
      return rootToken;
    }

    final data = payload['data'];
    if (data is Map) {
      final nestedToken = data['token'];
      if (nestedToken is String && nestedToken.isNotEmpty) {
        return nestedToken;
      }
    }

    throw const FormatException('Invalid auth payload: missing token');
  }
}
