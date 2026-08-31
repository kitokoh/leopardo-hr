import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/models/restaurant_order.dart';
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
    final l10n = context.l10n;
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await ref.read(restaurantRepositoryProvider).serveOrder(order.id);
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.restaurantMobileServerServedOk(order.reference)),
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.restaurantMobileServerServeError),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _pay(RestaurantOrder order) async {
    final l10n = context.l10n;
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
          l10n.restaurantMobileServerPayTitle(order.reference),
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
              decoration: InputDecoration(
                labelText: l10n.restaurantMobileServerAmountLabel,
              ),
            ),
            TextField(
              controller: tipController,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText: l10n.restaurantMobileServerTipLabel,
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(l10n.restaurantMobileCancel),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(l10n.restaurantMobileServerPay),
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
        SnackBar(
          content: Text(l10n.restaurantMobileInvalidAmount),
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
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.restaurantMobileServerPaidOk(order.reference)),
          ),
        );
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
          SnackBar(content: Text(l10n.restaurantMobileServerOfflineQueued)),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final ordersAsync = ref.watch(restaurantServerOrdersProvider);
    final tablesAsync = ref.watch(restaurantServerTablesProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: l10n.restaurantMobileHubServer,
        subtitle: l10n.restaurantMobileServerSubtitle,
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
            Text(
              l10n.restaurantMobileServerTables,
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 8),
            tablesAsync.when(
              loading: () => MobileEmptyLoading(
                label: l10n.restaurantMobileServerTablesLoading,
              ),
              error: (_, __) => MobileErrorPanel(
                message: l10n.restaurantMobileServerTablesError,
              ),
              data: (tables) => tables.isEmpty
                  ? Text(
                      l10n.restaurantMobileServerNoTables,
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
              l10n.restaurantMobileServerQueue,
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 8),
            ordersAsync.when(
              loading: () => MobileEmptyLoading(
                label: l10n.restaurantMobileServerOrdersLoading,
              ),
              error: (_, __) => MobileErrorPanel(
                message: l10n.restaurantMobileServerOrdersError,
              ),
              data: (orders) => orders.isEmpty
                  ? MobileEmptyLoading(
                      label: l10n.restaurantMobileServerNoOrders,
                    )
                  : Column(
                      children: orders.map((order) {
                        return MobileListCard(
                          icon: Icons.receipt_long_outlined,
                          iconColor: AppColors.finance,
                          title: order.reference,
                          subtitle: [
                            if (order.tableName != null) order.tableName!,
                            l10n.restaurantMobileServerItemsCount(
                              order.itemsCount,
                            ),
                            _statusLabel(l10n, order.status),
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
                                    child: Text(
                                      l10n.restaurantMobileServerServe,
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 8),
                              ],
                              Expanded(
                                child: FilledButton(
                                  onPressed: _busy ? null : () => _pay(order),
                                  child: Text(l10n.restaurantMobileServerPay),
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

  String _statusLabel(AppLocalizations l10n, String status) {
    switch (status) {
      case 'open':
        return l10n.restaurantMobileStatusOpen;
      case 'in_preparation':
        return l10n.restaurantMobileStatusInPreparation;
      case 'ready':
        return l10n.restaurantMobileStatusReady;
      case 'served':
        return l10n.restaurantMobileStatusServed;
      default:
        return status;
    }
  }

  String _formatMinor(int minor, String currency) {
    final symbol = currency.isEmpty ? '' : '$currency ';
    return '$symbol${(minor / 100).toStringAsFixed(2)}';
  }
}
