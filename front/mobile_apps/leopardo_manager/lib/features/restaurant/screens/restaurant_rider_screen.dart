import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/features/restaurant/data/restaurant_repository.dart';
import 'package:leopardo_core/models/restaurant_delivery.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/restaurant/providers/restaurant_providers.dart';

/// Écran livreur (RESTO-802/#6223) : tournées assignées + transitions.
///
/// Le livreur est résolu par `employee_id` côté serveur ; il ne voit que les
/// livraisons qui lui sont assignées (404 sûr cross-tenant). Chaque
/// transition publie un événement outbox `restaurant.delivery.*.v1`.
class RestaurantRiderScreen extends ConsumerStatefulWidget {
  const RestaurantRiderScreen({super.key});

  @override
  ConsumerState<RestaurantRiderScreen> createState() =>
      _RestaurantRiderScreenState();
}

class _RestaurantRiderScreenState extends ConsumerState<RestaurantRiderScreen> {
  bool _busy = false;

  Future<void> _refresh() async {
    ref.invalidate(restaurantRiderDeliveriesProvider);
  }

  Future<void> _transition(
    RestaurantDelivery delivery,
    Future<void> Function(RestaurantRepository repo) action,
    String successMessage,
  ) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action(ref.read(restaurantRepositoryProvider));
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(successMessage)));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Transition impossible depuis cet état'),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final deliveriesAsync = ref.watch(restaurantRiderDeliveriesProvider);
    const background = MobileSurface.background;

    return Scaffold(
      backgroundColor: background,
      appBar: MobileTopBar(
        title: 'Livraison',
        subtitle: 'Tournées assignées',
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: 'Retour',
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: MobileSurface.secondary),
            onPressed: _refresh,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
          children: [
            deliveriesAsync.when(
              loading: () =>
                  const MobileEmptyLoading(label: 'Chargement des livraisons…'),
              error: (_, __) =>
                  const MobileErrorPanel(message: 'Livraisons indisponibles'),
              data: (deliveries) => deliveries.isEmpty
                  ? const MobileEmptyLoading(label: 'Aucune livraison assignée')
                  : Column(
                      children: deliveries.map((delivery) {
                        return MobileListCard(
                          icon: Icons.delivery_dining_outlined,
                          iconColor: AppColors.finance,
                          title: delivery.reference,
                          subtitle: [
                            delivery.customerName ?? 'Client',
                            if (delivery.address != null) delivery.address!,
                            _statusLabel(delivery.status),
                          ].join(' · '),
                          trailing: delivery.feeMinor != null
                              ? Text(
                                  _formatMinor(delivery.feeMinor!),
                                  style: AppTypography.subtitle.copyWith(
                                    color: MobileSurface.text,
                                    fontWeight: FontWeight.w700,
                                  ),
                                )
                              : null,
                          footer:
                              delivery.isAssigned || delivery.isOutForDelivery
                              ? Row(
                                  children: [
                                    if (delivery.isAssigned) ...[
                                      Expanded(
                                        child: OutlinedButton(
                                          onPressed: _busy
                                              ? null
                                              : () => _transition(
                                                  delivery,
                                                  (repo) => repo.outForDelivery(
                                                    delivery.id,
                                                  ),
                                                  'Départ en livraison',
                                                ),
                                          child: const Text('Départ'),
                                        ),
                                      ),
                                      const SizedBox(width: 8),
                                    ],
                                    if (delivery.isOutForDelivery)
                                      Expanded(
                                        child: FilledButton(
                                          onPressed: _busy
                                              ? null
                                              : () => _transition(
                                                  delivery,
                                                  (repo) =>
                                                      repo.deliver(delivery.id),
                                                  'Livraison effectuée',
                                                ),
                                          child: const Text('Livrée'),
                                        ),
                                      ),
                                  ],
                                )
                              : null,
                        );
                      }).toList(),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  String _statusLabel(String status) {
    switch (status) {
      case 'assigned':
        return 'assignée';
      case 'out_for_delivery':
        return 'en cours';
      case 'delivered':
        return 'livrée';
      default:
        return status;
    }
  }

  String _formatMinor(int minor) => (minor / 100).toStringAsFixed(2);
}
