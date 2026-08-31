import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/models/restaurant_order.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/restaurant/providers/restaurant_providers.dart';

/// Écran serveur (RESTO-801/#6222) : file de service + plan de salle.
///
/// Le serveur pilote des transitions déjà validées côté serveur
/// (`OrderStateMachine`, `PayOrderAction`) : « Servir » et « Encaisser ».
/// En cas d'échec réseau, l'encaissement est mis en file offline
/// (RESTO-804/#6225) puis rejoué sans doublon.
class RestaurantServerScreen extends ConsumerStatefulWidget {
  const RestaurantServerScreen({super.key});

  @override
  ConsumerState<RestaurantServerScreen> createState() =>
      _RestaurantServerScreenState();
}

class _RestaurantServerScreenState
    extends ConsumerState<RestaurantServerScreen> {
  bool _busy = false;

  Future<void> _refresh() async {
    ref.invalidate(restaurantServerOrdersProvider);
    ref.invalidate(restaurantServerTablesProvider);
  }

  Future<void> _serve(RestaurantOrder order) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await ref.read(restaurantRepositoryProvider).serveOrder(order.id);
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Commande ${order.reference} servie')),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Impossible de servir la commande'),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pay(RestaurantOrder order) async {
    if (_busy) return;
    final amountController = TextEditingController(
      text: (order.totalMinor / 100).toStringAsFixed(2),
    );
    final tipController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: MobileSurface.card,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text(
          'Encaissement ${order.reference}',
          style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: amountController,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: const InputDecoration(labelText: 'Montant reçu'),
            ),
            TextField(
              controller: tipController,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: const InputDecoration(
                labelText: 'Pourboire (optionnel)',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Encaisser'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final amountMinor =
        (double.tryParse(amountController.text) ?? 0) * 100 ~/ 1;
    final tipMinor = (double.tryParse(tipController.text) ?? 0) * 100 ~/ 1;
    if (amountMinor <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Montant invalide'),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }

    setState(() => _busy = true);
    try {
      await ref
          .read(restaurantRepositoryProvider)
          .payOrder(
            order.id,
            amountMinor: amountMinor,
            tipMinor: tipMinor > 0 ? tipMinor : null,
          );
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('${order.reference} encaissée')));
      }
    } catch (_) {
      // Hors ligne : mise en file idempotente (RESTO-804) — rejeu sans doublon.
      ref
          .read(restaurantOfflineQueueProvider)
          .enqueue(
            type: 'order.pay',
            payload: {
              'order_id': order.id,
              'amount_minor': amountMinor,
              if (tipMinor > 0) 'tip_minor': tipMinor,
            },
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Hors ligne : opération mise en file, rejeu automatique',
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final ordersAsync = ref.watch(restaurantServerOrdersProvider);
    final tablesAsync = ref.watch(restaurantServerTablesProvider);
    const background = MobileSurface.background;

    return Scaffold(
      backgroundColor: background,
      appBar: MobileTopBar(
        title: 'Service',
        subtitle: 'File de commandes et plan de salle',
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
            Text(
              'Tables occupées',
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 8),
            tablesAsync.when(
              loading: () =>
                  const MobileEmptyLoading(label: 'Chargement des tables…'),
              error: (_, __) =>
                  const MobileErrorPanel(message: 'Tables indisponibles'),
              data: (tables) => tables.isEmpty
                  ? Text(
                      'Aucune table ouverte',
                      style: AppTypography.bodySmall.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    )
                  : Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: tables
                          .map(
                            (t) => Chip(
                              label: Text(t.name),
                              avatar: Icon(
                                Icons.circle,
                                size: 10,
                                color: t.isOpen
                                    ? AppColors.success
                                    : MobileSurface.disabled,
                              ),
                            ),
                          )
                          .toList(),
                    ),
            ),
            const SizedBox(height: 24),
            Text(
              'File de service',
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 8),
            ordersAsync.when(
              loading: () =>
                  const MobileEmptyLoading(label: 'Chargement des commandes…'),
              error: (_, __) =>
                  const MobileErrorPanel(message: 'Commandes indisponibles'),
              data: (orders) => orders.isEmpty
                  ? const MobileEmptyLoading(label: 'Aucune commande active')
                  : Column(
                      children: orders.map((order) {
                        return MobileListCard(
                          icon: Icons.receipt_long_outlined,
                          iconColor: AppColors.finance,
                          title: order.reference,
                          subtitle: [
                            if (order.tableName != null) order.tableName!,
                            '${order.itemsCount} article(s)',
                            _statusLabel(order.status),
                          ].join(' · '),
                          trailing: Text(
                            _formatMinor(order.totalMinor, order.currency),
                            style: AppTypography.subtitle.copyWith(
                              color: MobileSurface.text,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          footer: Row(
                            children: [
                              if (!order.isServed) ...[
                                Expanded(
                                  child: OutlinedButton(
                                    onPressed: _busy
                                        ? null
                                        : () => _serve(order),
                                    child: const Text('Servir'),
                                  ),
                                ),
                                const SizedBox(width: 8),
                              ],
                              Expanded(
                                child: FilledButton(
                                  onPressed: _busy ? null : () => _pay(order),
                                  child: const Text('Encaisser'),
                                ),
                              ),
                            ],
                          ),
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
      case 'open':
        return 'ouverte';
      case 'in_preparation':
        return 'en préparation';
      case 'ready':
        return 'prête';
      case 'served':
        return 'servie';
      default:
        return status;
    }
  }

  String _formatMinor(int minor, String currency) {
    final symbol = currency.isEmpty ? '' : '$currency ';
    return '$symbol${(minor / 100).toStringAsFixed(2)}';
  }
}
