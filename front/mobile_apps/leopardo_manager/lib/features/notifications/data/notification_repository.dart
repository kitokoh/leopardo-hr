import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/notification.dart';

class NotificationRepository {
  final ApiClient apiClient;

  NotificationRepository(this.apiClient);

  Future<List<AppNotification>> getMyNotifications() async {
    final response = await apiClient.dio.get('/notifications');
    final items = response.data['data'] as List;
    return items.map((e) => AppNotification.fromJson(e)).toList();
  }

  Future<void> markAllAsRead() async {
    await apiClient.dio.put('/notifications/read-all');
  }

  Future<void> markAsRead(int id) async {
    await apiClient.dio.put('/notifications/$id/read');
  }
}
