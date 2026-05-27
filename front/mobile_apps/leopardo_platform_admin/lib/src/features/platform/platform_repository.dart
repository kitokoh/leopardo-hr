import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';

import 'platform_models.dart';

class PlatformRepository {
  const PlatformRepository(this._apiClient, this._storage);

  final ApiClient _apiClient;
  final SecureStorage _storage;

  Future<PlatformAdminUser> login({
    required String email,
    required String password,
    String? twoFactorCode,
  }) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/auth/login',
      method: 'POST',
      isLoginRequest: true,
      data: {
        'email': email,
        'password': password,
        'device_name': 'leopardo-platform-admin-mobile',
        if (twoFactorCode != null && twoFactorCode.trim().isNotEmpty)
          'two_fa_code': twoFactorCode.trim(),
      },
    );

    final payload = response.data ?? {};
    final token = payload['token']?.toString();
    if (token == null || token.isEmpty) {
      throw StateError('Token plateforme manquant');
    }

    await _storage.saveToken(token);
    return PlatformAdminUser.fromJson(
      (payload['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<PlatformAdminUser> me() async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/auth/me',
    );
    return PlatformAdminUser.fromJson(
      ((response.data ?? {})['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<void> logout() async {
    try {
      await _apiClient.requestWithRetry<Map<String, dynamic>>(
        '/platform/auth/logout',
        method: 'POST',
      );
    } catch (_) {
      // Local cleanup remains mandatory even if the token is already expired.
    } finally {
      await _storage.deleteToken();
    }
  }

  Future<PlatformMetrics> metrics() async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/metrics/overview',
    );
    return PlatformMetrics.fromJson(
      ((response.data ?? {})['data'] as Map).cast<String, dynamic>(),
    );
  }

  Future<List<PlatformPlan>> plans() async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/plans',
    );
    final items = (((response.data ?? {})['data'] as Map?)?['items']);
    if (items is List) {
      return items
          .whereType<Map>()
          .map((item) => PlatformPlan.fromJson(item.cast<String, dynamic>()))
          .toList();
    }
    return const [];
  }

  Future<List<PlatformCompany>> companies() async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies',
    );
    final data = (response.data ?? {})['data'];
    if (data is List) {
      return data
          .whereType<Map>()
          .map((item) => PlatformCompany.fromJson(item.cast<String, dynamic>()))
          .toList();
    }
    return const [];
  }

  Future<PlatformCompanyHealth> companyHealth(String companyId) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies/$companyId/health',
    );
    return PlatformCompanyHealth.fromJson(response.data ?? {});
  }

  Future<PlatformCompanySubscription> companySubscription(
    String companyId,
  ) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies/$companyId/subscription',
    );
    return PlatformCompanySubscription.fromJson(response.data ?? {});
  }

  Future<PlatformCompanySubscription> updateCompanySubscription({
    required String companyId,
    required int planId,
    required String status,
    String? notes,
  }) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies/$companyId/subscription',
      method: 'PATCH',
      data: {
        'plan_id': planId,
        'status': status,
        if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      },
    );
    return PlatformCompanySubscription.fromJson(response.data ?? {});
  }

  Future<PlatformCompanyFeatures> companyFeatures(String companyId) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies/$companyId/features',
    );
    return PlatformCompanyFeatures.fromJson(response.data ?? {});
  }

  Future<PlatformCompanyFeatures> updateCompanyFeatures({
    required String companyId,
    required Map<String, bool> features,
  }) async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies/$companyId/features',
      method: 'PATCH',
      data: {'features': features},
    );
    return PlatformCompanyFeatures.fromJson(response.data ?? {});
  }

  Future<void> createCompany({
    required String name,
    required String email,
    required String country,
    required String city,
    required String managerFirstName,
    required String managerLastName,
    required String managerEmail,
    int? planId,
  }) async {
    await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/companies',
      method: 'POST',
      data: {
        'name': name,
        'email': email,
        'country': country.toUpperCase(),
        'city': city,
        'manager_first_name': managerFirstName,
        'manager_last_name': managerLastName,
        'manager_email': managerEmail,
        if (planId != null) 'plan_id': planId,
      },
    );
  }

  Future<List<PlatformCompanyRequest>> companyRequests() async {
    final response = await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/company-requests',
      queryParameters: {'status': 'pending'},
    );
    final data = (response.data ?? {})['data'];
    if (data is List) {
      return data
          .whereType<Map>()
          .map(
            (item) =>
                PlatformCompanyRequest.fromJson(item.cast<String, dynamic>()),
          )
          .toList();
    }
    return const [];
  }

  Future<void> reviewCompanyRequest({
    required int id,
    required bool approved,
    String? adminNotes,
  }) async {
    await _apiClient.requestWithRetry<Map<String, dynamic>>(
      '/platform/company-requests/$id',
      method: 'PATCH',
      data: {
        'status': approved ? 'approved' : 'rejected',
        if (adminNotes != null && adminNotes.trim().isNotEmpty)
          'admin_notes': adminNotes.trim(),
      },
    );
  }
}
