import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_core/models/notification.dart';

final notificationsProvider = FutureProvider<List<AppNotification>>((
  ref,
) async {
  final timer = Timer.periodic(const Duration(seconds: 30), (_) {
    ref.invalidateSelf();
  });
  ref.onDispose(timer.cancel);

  final repo = ref.watch(notificationRepositoryProvider);
  return await repo.getMyNotifications();
});
