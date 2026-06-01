import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';
import 'package:leopardo_core/models/app_user.dart';

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
    final response = await apiClient.requestWithRetry(
      '/user/register',
      method: 'POST',
      isLoginRequest: true,
      data: {
        'first_name': firstName,
        'last_name': lastName,
        'email': email,
        'password': password,
        if (phone != null) 'phone': phone,
      },
    );

    final data = _authPayload(response.data);
    final token = data['token'] as String;
    await storage.saveToken(token);

    final user = AppUser.fromJson(_userPayload(data));
    return {'user': user};
  }

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/user/login',
      method: 'POST',
      isLoginRequest: true,
      data: {'email': email, 'password': password, 'device_name': 'Mobile App'},
    );

    final data = _authPayload(response.data);
    final token = data['token'] as String;
    await storage.saveToken(token);

    final user = AppUser.fromJson(_userPayload(data));
    return {'user': user};
  }

  Future<Map<String, dynamic>> googleSignIn({
    required String googleId,
    required String email,
    required String firstName,
    required String lastName,
    String? avatarUrl,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/user/google-signin',
      method: 'POST',
      isLoginRequest: true,
      data: {
        'google_id': googleId,
        'email': email,
        'first_name': firstName,
        'last_name': lastName,
        if (avatarUrl != null) 'avatar_url': avatarUrl,
      },
    );

    final data = _authPayload(response.data);
    final token = data['token'] as String;
    await storage.saveToken(token);

    final user = AppUser.fromJson(_userPayload(data));
    return {'user': user, 'is_new': data['is_new'] ?? false};
  }

  Future<AppUser?> checkAuth() async {
    final token = await storage.getToken();
    if (token == null) return null;

    try {
      final response = await apiClient.requestWithRetry(
        '/user/me',
        timeoutOverride: const Duration(seconds: 8),
        maxRetriesOverride: 0,
      );
      final data = _userPayload(extractDataMap(response.data));
      return AppUser.fromJson(data);
    } catch (_) {
      return null;
    }
  }

  Future<void> logout() async {
    try {
      await apiClient.requestWithRetry(
        '/user/logout',
        method: 'POST',
        timeoutOverride: const Duration(seconds: 8),
        maxRetriesOverride: 0,
      );
    } catch (_) {
      // Ignore
    } finally {
      await storage.deleteToken();
    }
  }

  Future<List<Map<String, dynamic>>> getCompanyRequests() async {
    final response = await apiClient.requestWithRetry(
      '/user/company-requests',
      timeoutOverride: const Duration(seconds: 12),
    );
    return extractDataList(
      response.data,
    ).whereType<Map>().map((item) => item.cast<String, dynamic>()).toList();
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
    final response = await apiClient.requestWithRetry(
      '/user/company-requests',
      method: 'POST',
      timeoutOverride: const Duration(seconds: 15),
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
    return extractDataMap(response.data);
  }

  Future<AppUser> updateProfile({
    String? firstName,
    String? lastName,
    String? phone,
    String? preferredLanguage,
  }) async {
    final response = await apiClient.requestWithRetry(
      '/user/profile',
      method: 'PATCH',
      timeoutOverride: const Duration(seconds: 12),
      data: {
        if (firstName != null) 'first_name': firstName,
        if (lastName != null) 'last_name': lastName,
        if (phone != null) 'phone': phone,
        if (preferredLanguage != null) 'preferred_language': preferredLanguage,
      },
    );

    return AppUser.fromJson(_userPayload(extractDataMap(response.data)));
  }

  Map<String, dynamic> _authPayload(dynamic payload) {
    if (payload is! Map) {
      return const <String, dynamic>{};
    }

    final root = payload.cast<String, dynamic>();
    final rootToken = root['token'];
    if (rootToken is String && rootToken.isNotEmpty) {
      return root;
    }

    final data = root['data'];
    if (data is Map) {
      final dataMap = data.cast<String, dynamic>();
      final dataToken = dataMap['token'];
      if (dataToken is String && dataToken.isNotEmpty) {
        return dataMap;
      }
    }

    final nested = root['auth'];
    if (nested is Map) {
      return nested.cast<String, dynamic>();
    }

    return extractDataMap(payload);
  }

  Map<String, dynamic> _userPayload(Map<String, dynamic> payload) {
    final user = payload['user'] ?? payload['employee'] ?? payload['data'];
    if (user is Map) {
      return user.cast<String, dynamic>();
    }
    return payload;
  }
}
