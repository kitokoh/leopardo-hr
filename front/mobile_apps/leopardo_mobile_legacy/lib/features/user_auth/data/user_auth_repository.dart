import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';
import 'package:leopardo_rh/models/app_user.dart';

class UserAuthRepository {
  final ApiClient apiClient;
  final SecureStorage storage;

  UserAuthRepository(this.apiClient, this.storage);

  Future<Map<String, dynamic>> register({
    required String firstName,
    required String lastName,
    required String email,
    required String password,
    String? phone,
  }) async {
    final response = await apiClient.dio.post(
      '/user/register',
      data: {
        'first_name': firstName,
        'last_name': lastName,
        'email': email,
        'password': password,
        if (phone != null) 'phone': phone,
      },
    );

    final data = response.data as Map<String, dynamic>;
    final token = data['token'] as String;
    await storage.saveToken(token);

    final user = AppUser.fromJson(data['data'] as Map<String, dynamic>);
    return {'user': user};
  }

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    final response = await apiClient.dio.post(
      '/user/login',
      data: {'email': email, 'password': password, 'device_name': 'Mobile App'},
    );

    final data = response.data as Map<String, dynamic>;
    final token = data['token'] as String;
    await storage.saveToken(token);

    final user = AppUser.fromJson(data['data'] as Map<String, dynamic>);
    return {'user': user};
  }

  Future<Map<String, dynamic>> googleSignIn({
    required String googleId,
    required String email,
    required String firstName,
    required String lastName,
    String? avatarUrl,
  }) async {
    final response = await apiClient.dio.post(
      '/user/google-signin',
      data: {
        'google_id': googleId,
        'email': email,
        'first_name': firstName,
        'last_name': lastName,
        if (avatarUrl != null) 'avatar_url': avatarUrl,
      },
    );

    final data = response.data as Map<String, dynamic>;
    final token = data['token'] as String;
    await storage.saveToken(token);

    final user = AppUser.fromJson(data['data'] as Map<String, dynamic>);
    return {'user': user, 'is_new': data['is_new'] ?? false};
  }

  Future<AppUser?> checkAuth() async {
    final token = await storage.getToken();
    if (token == null) return null;

    try {
      final response = await apiClient.dio.get('/user/me');
      final data = response.data['data'] as Map<String, dynamic>;
      return AppUser.fromJson(data);
    } catch (_) {
      return null;
    }
  }

  Future<void> logout() async {
    try {
      await apiClient.dio.post('/user/logout');
    } catch (_) {
      // Ignore
    } finally {
      await storage.deleteToken();
    }
  }

  Future<List<Map<String, dynamic>>> getCompanyRequests() async {
    final response = await apiClient.dio.get('/user/company-requests');
    final data = response.data['data'] as List<dynamic>;
    return data.cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> submitCompanyRequest({
    required String companyName,
    required String email,
    String? sector,
    String? country,
    String? city,
    String? phone,
    String? description,
  }) async {
    final response = await apiClient.dio.post(
      '/user/company-requests',
      data: {
        'company_name': companyName,
        'email': email,
        if (sector != null) 'sector': sector,
        if (country != null) 'country': country,
        if (city != null) 'city': city,
        if (phone != null) 'phone': phone,
        if (description != null) 'description': description,
      },
    );
    return response.data['data'] as Map<String, dynamic>;
  }

  Future<AppUser> updateProfile({
    String? firstName,
    String? lastName,
    String? phone,
    String? preferredLanguage,
  }) async {
    final response = await apiClient.dio.patch(
      '/user/profile',
      data: {
        if (firstName != null) 'first_name': firstName,
        if (lastName != null) 'last_name': lastName,
        if (phone != null) 'phone': phone,
        if (preferredLanguage != null) 'preferred_language': preferredLanguage,
      },
    );

    return AppUser.fromJson(
      (response.data['data'] as Map).cast<String, dynamic>(),
    );
  }
}
