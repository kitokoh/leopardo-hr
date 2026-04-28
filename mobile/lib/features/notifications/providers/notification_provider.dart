import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/models/notification.dart';

final notificationsProvider = FutureProvider<List<AppNotification>>((ref) async {
  final repo = ref.watch(notificationRepositoryProvider);
  return await repo.getMyNotifications();
});
