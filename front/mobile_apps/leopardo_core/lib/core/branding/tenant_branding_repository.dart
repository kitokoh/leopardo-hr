import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/core/branding/tenant_branding.dart';

class TenantBrandingRepository {
  const TenantBrandingRepository(this.apiClient);

  final ApiClient apiClient;

  Future<TenantBranding> read() async {
    final response = await apiClient.requestWithRetry(
      '/company/branding',
      maxRetriesOverride: 0,
      timeoutOverride: const Duration(seconds: 6),
    );

    return TenantBranding.fromApi(extractDataMap(response.data));
  }
}
