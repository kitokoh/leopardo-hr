import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/notification.dart';

class NotificationRepository {
  final ApiClient apiClient;

  NotificationRepository(this.apiClient);

  Future<List<AppNotification>> getMyNotifications({
    bool unreadOnly = false,
    int perPage = 30,
  }) async {
    final response = await apiClient.requestWithRetry<Map<String, dynamic>>(
      '/notifications',
      queryParameters: {'unread': unreadOnly, 'per_page': perPage},
      timeoutOverride: const Duration(seconds: 12),
    );

    return _decodeNotifications(response.data);
  }

  Future<void> markAllAsRead() async {
    await apiClient.requestWithRetry<void>(
      '/notifications/read-all',
      method: 'PUT',
      timeoutOverride: const Duration(seconds: 12),
    );
  }

  Future<void> markAsRead(int id) async {
    await apiClient.requestWithRetry<void>(
      '/notifications/$id/read',
      method: 'PUT',
      timeoutOverride: const Duration(seconds: 12),
    );
  }

  List<AppNotification> _decodeNotifications(dynamic payload) {
    final rawItems =
        payload is Map<String, dynamic> ? payload['data'] : payload;
    if (rawItems is! List) {
      return const <AppNotification>[];
    }

    return rawItems
        .whereType<Map>()
        .map((item) => AppNotification.fromJson(item.cast<String, dynamic>()))
        .toList(growable: false);
  }
}
