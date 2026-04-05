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
    final employeeJson = (data['data'] as Map).cast<String, dynamic>();
    final token = data['token'] as String;

    await storage.saveToken(token);

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
}
