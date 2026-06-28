import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

class PushNotificationRepository {
  final ApiClient apiClient;

  PushNotificationRepository(this.apiClient);

  static const _actionTimeout = Duration(seconds: 10);
  static const _readTimeout = Duration(seconds: 8);

  Future<void> registerDeviceToken({
    required String token,
    required String platform,
    String? deviceName,
  }) async {
    await apiClient.requestWithRetry(
      '/device-tokens',
      method: 'POST',
      data: {
        'token': token,
        'platform': platform,
        if (deviceName != null) 'device_name': deviceName,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<void> unregisterDeviceToken(String token) async {
    await apiClient.requestWithRetry(
      '/device-tokens',
      method: 'DELETE',
      data: {'token': token},
      maxRetriesOverride: 0,
      timeoutOverride: _actionTimeout,
    );
  }

  Future<List<Map<String, dynamic>>> getDeviceTokens() async {
    final response = await apiClient.requestWithRetry(
      '/device-tokens',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((entry) => entry.cast<String, dynamic>())
        .toList();
  }
}
