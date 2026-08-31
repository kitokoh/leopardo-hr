import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_manager/features/restaurant/providers/restaurant_providers.dart';

/// Hub RestaurantManager (RESTO-028/#6155, #6406).
///
/// Point d'entrée des trois surfaces mobiles : serveur (file de service),
/// livreur (tournées) et gérant (KPIs / stock / clôture). Le bandeau offline
/// rappelle les opérations en attente de rejeu (RESTO-804/#6225).
class RestaurantHubScreen extends ConsumerWidget {
  const RestaurantHubScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final queue = ref.watch(restaurantOfflineQueueProvider);
    const muted = MobileSurface.secondary;

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: l10n.restaurantMobileHubTitle,
        subtitle: l10n.restaurantMobileHubSubtitle,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: l10n.restaurantMobileBack,
          onPressed: () => context.pop(),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
        children: [
          if (queue.hasPending) ...[
            MobilePanel(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  const MobileIconBubble(
                    icon: Icons.cloud_off_outlined,
                    color: AppColors.warning,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      l10n.restaurantMobileOfflinePending(queue.pending.length),
                      style: AppTypography.bodySmall.copyWith(color: muted),
                    ),
                  ),
                  TextButton(
                    onPressed: () async {
                      await queue.flush();
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(l10n.restaurantMobileOfflineSynced),
                          ),
                        );
                      }
                    },
                    child: Text(l10n.restaurantMobileOfflineReplay),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
          ],
          _HubCard(
            icon: Icons.table_restaurant_outlined,
            title: l10n.restaurantMobileHubServer,
            subtitle: l10n.restaurantMobileHubServerDesc,
            onTap: () => context.push('/restaurant/server'),
          ),
          const SizedBox(height: 14),
          _HubCard(
            icon: Icons.delivery_dining_outlined,
            title: l10n.restaurantMobileHubRider,
            subtitle: l10n.restaurantMobileHubRiderDesc,
            onTap: () => context.push('/restaurant/rider'),
          ),
          const SizedBox(height: 14),
          _HubCard(
            icon: Icons.insights_outlined,
            title: l10n.restaurantMobileHubManager,
            subtitle: l10n.restaurantMobileHubManagerDesc,
            onTap: () => context.push('/restaurant/manager'),
          ),
          const SizedBox(height: 20),
          Text(
            l10n.restaurantMobileHubFooter,
            style: AppTypography.bodySmall.copyWith(color: muted),
          ),
        ],
      ),
    );
  }
}

class _HubCard extends StatelessWidget {
  const _HubCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: MobileSurface.surface,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: MobileSurface.border, width: 0.7),
        ),
        child: Row(
          children: [
            MobileIconBubble(icon: icon, color: AppColors.finance),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: AppTypography.subtitle.copyWith(
                      color: MobileSurface.text,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: AppTypography.bodySmall.copyWith(
                      color: MobileSurface.secondary,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: MobileSurface.secondary),
          ],
        ),
      ),
    );
  }
}
