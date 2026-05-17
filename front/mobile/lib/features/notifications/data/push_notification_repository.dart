import 'package:leopardo_rh/core/api/api_client.dart';

class PushNotificationRepository {
  final ApiClient apiClient;

  PushNotificationRepository(this.apiClient);

  Future<void> registerDeviceToken({
    required String token,
    required String platform,
    String? deviceName,
  }) async {
    await apiClient.dio.post('/device-tokens', data: {
      'token': token,
      'platform': platform,
      if (deviceName != null) 'device_name': deviceName,
    });
  }

  Future<void> unregisterDeviceToken(String token) async {
    await apiClient.dio.delete('/device-tokens', data: {
      'token': token,
    });
  }

  Future<List<Map<String, dynamic>>> getDeviceTokens() async {
    final response = await apiClient.dio.get('/device-tokens');
    final items = response.data['data'] as List;
    return items.cast<Map<String, dynamic>>();
  }
}
