import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
import 'package:leopardo_core/l10n/l10n.dart';
import 'package:leopardo_core/models/restaurant_pos_session.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/restaurant/providers/restaurant_providers.dart';

/// Écran gérant (RESTO-803/#6224) : KPIs du jour, alertes stock, clôture.
///
/// Les indicateurs sont calculés côté serveur (jamais agrégés côté client) ;
/// la clôture délègue à `ClosePosSessionAction` (écart serveur +
/// `restaurant.pos.closed.v1`, RBAC principal/rh/manager).
class RestaurantManagerScreen extends ConsumerStatefulWidget {
  const RestaurantManagerScreen({super.key});

  @override
  ConsumerState<RestaurantManagerScreen> createState() =>
      _RestaurantManagerScreenState();
}

class _RestaurantManagerScreenState
    extends ConsumerState<RestaurantManagerScreen> {
  bool _busy = false;

  Future<void> _refresh() async {
    ref.invalidate(restaurantManagerKpisProvider);
    ref.invalidate(restaurantManagerStockAlertsProvider);
    ref.invalidate(restaurantManagerPosSessionProvider);
  }

  Future<void> _closeSession(RestaurantPosSession session) async {
    final l10n = context.l10n;
    if (_busy) return;
    final controller = TextEditingController(
      text: session.expectedMinor != null
          ? (session.expectedMinor! / 100).toStringAsFixed(2)
          : '',
    );
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: MobileSurface.card,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Text(
          l10n.restaurantMobileManagerCloseTitle,
          style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
        ),
        content: TextField(
          controller: controller,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(
            labelText: l10n.restaurantMobileManagerCountedLabel,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text(l10n.restaurantMobileCancel),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: Text(l10n.restaurantMobileManagerClose),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final countedMinor = (double.tryParse(controller.text) ?? 0) * 100 ~/ 1;
    if (countedMinor <= 0) {
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
          .closePosSession(session.id, countedCashMinor: countedMinor);
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(l10n.restaurantMobileManagerClosedOk)),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.restaurantMobileManagerCloseError),
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
    final kpisAsync = ref.watch(restaurantManagerKpisProvider);
    final alertsAsync = ref.watch(restaurantManagerStockAlertsProvider);
    final sessionAsync = ref.watch(restaurantManagerPosSessionProvider);

    return Scaffold(
      backgroundColor: MobileSurface.background,
      appBar: MobileTopBar(
        title: l10n.restaurantMobileHubManager,
        subtitle: l10n.restaurantMobileManagerSubtitle,
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
            kpisAsync.when(
              loading: () => MobileEmptyLoading(
                label: l10n.restaurantMobileManagerKpisLoading,
              ),
              error: (_, __) => MobileErrorPanel(
                message: l10n.restaurantMobileManagerKpisError,
              ),
              data: (kpis) => Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _KpiTile(
                    label: l10n.restaurantMobileManagerRevenueToday,
                    value: _formatMinor(kpis.todayRevenueMinor, kpis.currency),
                  ),
                  _KpiTile(
                    label: l10n.restaurantMobileManagerOrders,
                    value: '${kpis.ordersCount}',
                  ),
                  _KpiTile(
                    label: l10n.restaurantMobileManagerAvgBasket,
                    value: _formatMinor(kpis.avgBasketMinor, kpis.currency),
                  ),
                  _KpiTile(
                    label: l10n.restaurantMobileManagerTablesOpen,
                    value: '${kpis.tablesOpenedToday}',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            Text(
              l10n.restaurantMobileManagerCash,
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 8),
            sessionAsync.when(
              loading: () => const SizedBox.shrink(),
              error: (_, __) => MobileErrorPanel(
                message: l10n.restaurantMobileManagerSessionError,
              ),
              data: (session) => session == null
                  ? Text(
                      l10n.restaurantMobileManagerNoSession,
                      style: AppTypography.bodySmall.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    )
                  : MobileListCard(
                      icon: Icons.point_of_sale_outlined,
                      iconColor: AppColors.finance,
                      title:
                          '${l10n.restaurantMobileManagerCash} #${session.id}',
                      subtitle: session.isOpen
                          ? l10n.restaurantMobileManagerSessionOpen
                          : session.status,
                      trailing: session.isOpen
                          ? FilledButton(
                              onPressed: _busy
                                  ? null
                                  : () => _closeSession(session),
                              child: Text(l10n.restaurantMobileManagerClose),
                            )
                          : null,
                    ),
            ),
            const SizedBox(height: 24),
            Text(
              l10n.restaurantMobileManagerStockAlerts,
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 8),
            alertsAsync.when(
              loading: () => const SizedBox.shrink(),
              error: (_, __) => MobileErrorPanel(
                message: l10n.restaurantMobileManagerStockAlertsError,
              ),
              data: (alerts) => alerts.isEmpty
                  ? Text(
                      l10n.restaurantMobileManagerNoStockAlerts,
                      style: AppTypography.bodySmall.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    )
                  : Column(
                      children: alerts.map((alert) {
                        return MobileListCard(
                          icon: Icons.warning_amber_outlined,
                          iconColor: AppColors.warning,
                          title:
                              alert.ingredient ??
                              l10n.restaurantMobileManagerIngredient(alert.id),
                          subtitle: l10n.restaurantMobileManagerStockLevel(
                            alert.quantity,
                            alert.alertThreshold ?? '-',
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

  String _formatMinor(int minor, String? currency) {
    final symbol = (currency == null || currency.isEmpty) ? '' : '$currency ';
    return '$symbol${(minor / 100).toStringAsFixed(2)}';
  }
}

class _KpiTile extends StatelessWidget {
  const _KpiTile({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: (MediaQuery.of(context).size.width - 40 - 12) / 2,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: MobileSurface.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: MobileSurface.border, width: 0.7),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: AppTypography.bodySmall.copyWith(
              color: MobileSurface.secondary,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            value,
            style: AppTypography.title.copyWith(
              color: MobileSurface.text,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}
