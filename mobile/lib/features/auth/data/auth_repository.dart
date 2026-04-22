import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/models/employee.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';

class AuthRepository {
  final ApiClient apiClient;
  final SecureStorage storage;

  AuthRepository(this.apiClient, this.storage);

  Future<Map<String, dynamic>> login(String email, String password) async {
    final response = await apiClient.dio.post('/auth/login', data: {
      'email': email,
      'password': password,
      'device_name': 'Mobile App',
    });

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
        return {
          'employee': Employee.fromJson(meData.cast<String, dynamic>()),
        };
      }
    } catch (_) {
      // Si /auth/me echoue on retombe sur la reponse de login.
    }

    return {
      'employee': Employee.fromJson(employeeJson),
    };
  }

  Future<void> logout() async {
    try {
      await apiClient.dio.post('/auth/logout');
    } catch (_) {
      // Ignore errors if token is already invalid
    } finally {
      await storage.deleteToken();
    }
  }

  Future<Map<String, dynamic>?> checkAuth() async {
    final token = await storage.getToken();
    if (token == null) return null;

    try {
      final response = await apiClient.dio.get('/auth/me');
      final data = response.data['data'];
      return {
        'employee': Employee.fromJson(data),
      };
    } catch (e) {
      await storage.deleteToken();
      return null;
    }
  }

  Future<Employee> updateProfile({
    required String firstName,
    required String lastName,
    required String email,
  }) async {
    final response = await apiClient.dio.patch('/auth/profile', data: {
      'first_name': firstName.trim(),
      'last_name': lastName.trim(),
      'email': email.trim(),
    });

    return Employee.fromJson((response.data['data'] as Map).cast<String, dynamic>());
  }

  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    await apiClient.dio.post('/auth/change-password', data: {
      'current_password': currentPassword,
      'new_password': newPassword,
      'new_password_confirmation': confirmation,
    });
  }

  static Map<String, dynamic> extractEmployeeJson(Map<String, dynamic> payload) {
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
