import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
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
    final queue = ref.watch(restaurantOfflineQueueProvider);
    const text = MobileSurface.text;
    const muted = MobileSurface.secondary;
    const background = MobileSurface.background;

    return Scaffold(
      backgroundColor: background,
      appBar: MobileTopBar(
        title: 'Restaurant',
        subtitle: 'Outils de service, livraison et gestion',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
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
                      '${queue.pending.length} opération(s) hors ligne en attente de rejeu',
                      style: AppTypography.bodySmall.copyWith(color: muted),
                    ),
                  ),
                  TextButton(
                    onPressed: () async {
                      await queue.flush();
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                            content: Text('Synchronisation effectuée'),
                          ),
                        );
                      }
                    },
                    child: const Text('Rejouer'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
          ],
          _HubCard(
            icon: Icons.table_restaurant_outlined,
            title: 'Service',
            subtitle: 'File de commandes, plan de salle, encaissement',
            onTap: () => context.push('/restaurant/server'),
          ),
          const SizedBox(height: 14),
          _HubCard(
            icon: Icons.delivery_dining_outlined,
            title: 'Livraison',
            subtitle: 'Tournées assignées et transitions',
            onTap: () => context.push('/restaurant/rider'),
          ),
          const SizedBox(height: 14),
          _HubCard(
            icon: Icons.insights_outlined,
            title: 'Gestion',
            subtitle: 'KPIs du jour, alertes stock, clôture de caisse',
            onTap: () => context.push('/restaurant/manager'),
          ),
          const SizedBox(height: 20),
          Text(
            'Les montants et transitions sont validés côté serveur. En cas de coupure réseau, les opérations sont mises en file et rejouées sans doublon.',
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
