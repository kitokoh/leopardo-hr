import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/empty_state.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_manager/features/notifications/providers/notification_provider.dart';

class NotificationListScreen extends ConsumerWidget {
  const NotificationListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notificationsAsync = ref.watch(notificationsProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: 'Notifications',
        subtitle: 'Alertes RH, paie et validations',
        actions: [
          IconButton(
            tooltip: 'Actualiser',
            icon: const Icon(Icons.refresh, color: MobileSurface.secondary),
            onPressed: () => ref.invalidate(notificationsProvider),
          ),
          IconButton(
            tooltip: 'Tout marquer comme lu',
            icon: const Icon(Icons.done_all, color: MobileSurface.secondary),
            onPressed: () async {
              await ref.read(notificationRepositoryProvider).markAllAsRead();
              ref.invalidate(notificationsProvider);
            },
          ),
        ],
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          onPressed: () => Navigator.of(context).pop(),
        ),
      ),
      body: notificationsAsync.when(
        data:
            (notifications) => RefreshIndicator(
              onRefresh: () async => ref.refresh(notificationsProvider.future),
              color: AppColors.rh,
              backgroundColor: MobileSurface.background,
              child:
                  notifications.isEmpty
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
                          return _NotificationTile(
                            title: notification.title,
                            body: notification.body,
                            isRead: notification.isRead,
                            onTap: () async {
                              if (!notification.isRead) {
                                await ref
                                    .read(notificationRepositoryProvider)
                                    .markAsRead(notification.id);
                                ref.invalidate(notificationsProvider);
                              }
                            },
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

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({
    required this.title,
    required this.body,
    required this.isRead,
    required this.onTap,
  });

  final String title;
  final String body;
  final bool isRead;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = isRead ? MobileSurface.disabled : AppColors.info;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Ink(
          padding: const EdgeInsets.all(14),
          decoration: MobileSurface.cardDecoration(
            color:
                isRead
                    ? MobileSurface.surface
                    : AppColors.info.withValues(alpha: 0.08),
            radius: 14,
          ),
          child: Row(
            children: [
              MobileIconBubble(
                icon:
                    isRead
                        ? Icons.notifications_none
                        : Icons.notifications_active,
                color: accent,
                size: 38,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            title,
                            style: AppTypography.bodySmall.copyWith(
                              color: MobileSurface.text,
                              fontWeight:
                                  isRead ? FontWeight.w500 : FontWeight.w700,
                            ),
                          ),
                        ),
                        if (!isRead)
                          Container(
                            width: 7,
                            height: 7,
                            decoration: const BoxDecoration(
                              color: AppColors.info,
                              shape: BoxShape.circle,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 5),
                    Text(
                      body,
                      style: AppTypography.caption.copyWith(
                        color: MobileSurface.secondary,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
