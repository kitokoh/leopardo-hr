import 'package:google_sign_in/google_sign_in.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';
import 'package:leopardo_core/models/employee.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';

class AuthRepository {
  final _googleSignIn = GoogleSignIn.instance;
  final ApiClient apiClient;
  final SecureStorage storage;
  final AppPreferences preferences;

  AuthRepository(this.apiClient, this.storage, this.preferences);

  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await apiClient.requestWithRetry(
      '/auth/login',
      method: 'POST',
      data: {'email': email, 'password': password, 'device_name': 'Mobile App'},
      isLoginRequest: true,
    );

    final data = response.data as Map<String, dynamic>;
    final employeeJson = extractEmployeeJson(data);
    final token = extractToken(data);

    await storage.saveToken(token);

    // Hydrate depuis /auth/me pour recuperer manager_role + capabilities
    // (la reponse /auth/login ne les expose pas).
    try {
      final meResponse = await apiClient.dio.get('/auth/me');
      final meData = meResponse.data['data'];
      if (meData is Map) {
        final employee = Employee.fromJson(meData.cast<String, dynamic>());
        await _persistEmployeeContext(employee);
        return {'employee': employee};
      }
    } catch (_) {
      // Si /auth/me echoue on retombe sur la reponse de login.
    }

    final employee = Employee.fromJson(employeeJson);
    await _persistEmployeeContext(employee);

    return {'employee': employee};
  }

  Future<Map<String, dynamic>> loginWithGoogle() async {
    final googleUser = await _googleSignIn.authenticate();

    final GoogleSignInAuthentication googleAuth = googleUser.authentication;
    final String? idToken = googleAuth.idToken;

    if (idToken == null) {
      throw Exception('Impossible de récupérer le token Google');
    }

    // On envoie le token au backend.
    // Note: Le backend doit être capable de vérifier ce token.
    // Pour simplifier l'intégration Socialite existante, on peut passer par une route
    // qui accepte le token.
    final response = await apiClient.dio.post(
      '/auth/google/token',
      data: {'token': idToken, 'device_name': 'Mobile App'},
    );

    final data = response.data as Map<String, dynamic>;
    final employeeJson = extractEmployeeJson(data);
    final token = extractToken(data);

    await storage.saveToken(token);

    final employee = Employee.fromJson(employeeJson);
    await _persistEmployeeContext(employee);

    return {'employee': employee};
  }

  Future<Map<String, dynamic>> register({
    required String firstName,
    required String lastName,
    required String email,
    required String password,
  }) async {
    final response = await apiClient.dio.post(
      '/auth/register',
      data: {
        'first_name': firstName,
        'last_name': lastName,
        'email': email,
        'password': password,
        'password_confirmation': password,
        'device_name': 'Mobile App',
      },
    );

    final data = response.data as Map<String, dynamic>;
    final employeeJson = extractEmployeeJson(data);
    final token = extractToken(data);

    await storage.saveToken(token);

    final employee = Employee.fromJson(employeeJson);
    await _persistEmployeeContext(employee);

    return {'employee': employee};
  }

  Future<void> logout() async {
    try {
      await apiClient.dio.post('/auth/logout');
    } catch (_) {
      // Ignore errors if token is already invalid
    } finally {
      await storage.deleteToken();
      await preferences.clearLocaleSettings();
    }
  }

  Future<Map<String, dynamic>?> checkAuth() async {
    final token = await storage.getToken();
    if (token == null) return null;

    try {
      final response = await apiClient.dio.get('/auth/me');
      final data = response.data['data'];
      final employee = Employee.fromJson(data);
      await _persistEmployeeContext(employee);
      return {'employee': employee};
    } catch (e) {
      await storage.deleteToken();
      await preferences.clearLocaleSettings();
      return null;
    }
  }

  Future<Employee> updateProfile({
    required String firstName,
    required String lastName,
    required String email,
  }) async {
    final response = await apiClient.dio.patch(
      '/auth/profile',
      data: {
        'first_name': firstName.trim(),
        'last_name': lastName.trim(),
        'email': email.trim(),
      },
    );

    final employee = Employee.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
    await _persistEmployeeContext(employee);
    return employee;
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    await apiClient.dio.post(
      '/auth/change-password',
      data: {
        'current_password': currentPassword,
        'new_password': newPassword,
        'new_password_confirmation': confirmation,
      },
    );
  }

  Future<Employee> updatePreferredLanguage(String language) async {
    final response = await apiClient.dio.patch(
      '/auth/language',
      data: {'language': language.trim().toLowerCase()},
    );

    final employee = Employee.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
    await _persistEmployeeContext(employee);
    return employee;
  }

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

  Future<void> _persistEmployeeContext(Employee employee) {
    return preferences.saveLocaleSettings(
      preferredLanguage: employee.language,
      isRtl: employee.isRtl,
    );
  }
}
