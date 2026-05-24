import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/core/theme/app_colors.dart';
import 'package:leopardo_rh/core/theme/app_typography.dart';
import 'package:leopardo_rh/core/widgets/empty_state.dart';
import 'package:leopardo_rh/features/notifications/providers/notification_provider.dart';

class NotificationListScreen extends ConsumerWidget {
  const NotificationListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notificationsAsync = ref.watch(notificationsProvider);

    return Scaffold(
      backgroundColor: AppColors.bgDark,
      appBar: AppBar(
        backgroundColor: AppColors.bgDark,
        elevation: 0,
        title: Text(
          'Notifications',
          style: AppTypography.subtitle.copyWith(color: AppColors.textDark),
        ),
        actions: [
          IconButton(
            tooltip: 'Actualiser',
            icon: const Icon(Icons.refresh, color: AppColors.textDark),
            onPressed: () => ref.invalidate(notificationsProvider),
          ),
          IconButton(
            tooltip: 'Tout marquer comme lu',
            icon: const Icon(Icons.done_all, color: AppColors.textDark),
            onPressed: () async {
              await ref
                  .read(notificationRepositoryProvider)
                  .markAllAsRead();
              ref.invalidate(notificationsProvider);
            },
          ),
        ],
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textDark),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: notificationsAsync.when(
        data:
            (notifications) =>
                RefreshIndicator(
                  onRefresh: () async => ref.refresh(notificationsProvider.future),
                  child: notifications.isEmpty
                      ? ListView(
                        padding: const EdgeInsets.all(20),
                        children: [
                          EmptyState(
                            icon: Icons.notifications_none,
                            title: 'Aucune notification',
                            description:
                                'Vous etes a jour. Cette page se rafraichit automatiquement.',
                          ),
                        ],
                      )
                      : ListView.builder(
                        padding: const EdgeInsets.all(20),
                        itemCount: notifications.length,
                        itemBuilder: (context, index) {
                          final notification = notifications[index];
                          return Card(
                            color: AppColors.cardDark,
                            margin: const EdgeInsets.only(bottom: 12),
                            child: ListTile(
                              onTap: () async {
                                if (!notification.isRead) {
                                  await ref
                                      .read(notificationRepositoryProvider)
                                      .markAsRead(notification.id);
                                  ref.invalidate(notificationsProvider);
                                }
                              },
                              leading: Icon(
                                notification.isRead
                                    ? Icons.notifications_none
                                    : Icons.notifications_active,
                                color:
                                    notification.isRead
                                        ? AppColors.textMutedDark
                                        : AppColors.info,
                              ),
                              title: Text(
                                notification.title,
                                style: AppTypography.subtitle.copyWith(
                                  color: AppColors.textDark,
                                  fontWeight:
                                      notification.isRead
                                          ? FontWeight.normal
                                          : FontWeight.bold,
                                ),
                              ),
                              subtitle: Text(
                                notification.body,
                                style: AppTypography.bodySmall.copyWith(
                                  color: AppColors.textMutedDark,
                                ),
                              ),
                              trailing: notification.isRead
                                  ? null
                                  : const Icon(
                                      Icons.circle,
                                      size: 10,
                                      color: AppColors.info,
                                    ),
                            ),
                          );
                        },
                      ),
                ),
        loading: () => const Center(child: CircularProgressIndicator()),
        error:
            (e, _) => Center(
              child: Text(
                e.toString(),
                style: const TextStyle(color: AppColors.danger),
              ),
            ),
      ),
    );
  }
}
