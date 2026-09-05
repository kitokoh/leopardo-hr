import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/features/restaurant/data/restaurant_repository.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/models/restaurant_delivery.dart';
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
          SnackBar(
            content: Text(context.l10n.restaurantMobileRiderTransitionError),
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
    final l10n = context.l10n;
    final deliveriesAsync = ref.watch(restaurantRiderDeliveriesProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: l10n.restaurantMobileHubRider,
        subtitle: l10n.restaurantMobileRiderSubtitle,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: MobileSurface.secondary),
          tooltip: l10n.restaurantMobileBack,
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
                  MobileEmptyLoading(label: l10n.restaurantMobileRiderLoading),
              error: (_, __) =>
                  MobileErrorPanel(message: l10n.restaurantMobileRiderError),
              data: (deliveries) => deliveries.isEmpty
                  ? MobileEmptyLoading(label: l10n.restaurantMobileRiderEmpty)
                  : Column(
                      children: deliveries.map((delivery) {
                        return MobileListCard(
                          icon: Icons.delivery_dining_outlined,
                          iconColor: AppColors.finance,
                          title: delivery.reference,
                          subtitle: [
                            delivery.customerName ??
                                l10n.restaurantMobileRiderCustomer,
                            if (delivery.address != null) delivery.address!,
                            _statusLabel(l10n, delivery.status),
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
                                                  l10n.restaurantMobileRiderDeparted,
                                                ),
                                          child: Text(
                                            l10n.restaurantMobileRiderDepart,
                                          ),
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
                                                  l10n.restaurantMobileRiderDeliveredOk,
                                                ),
                                          child: Text(
                                            l10n.restaurantMobileRiderDeliver,
                                          ),
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

  String _statusLabel(AppLocalizations l10n, String status) {
    switch (status) {
      case 'assigned':
        return l10n.restaurantMobileStatusAssigned;
      case 'out_for_delivery':
        return l10n.restaurantMobileStatusOutForDelivery;
      case 'delivered':
        return l10n.restaurantMobileStatusDelivered;
      default:
        return status;
    }
  }

  String _formatMinor(int minor) => (minor / 100).toStringAsFixed(2);
}
