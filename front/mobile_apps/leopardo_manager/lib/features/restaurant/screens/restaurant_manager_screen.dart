import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/theme/app_typography.dart';
import 'package:leopardo_core/core/widgets/mobile_surface.dart';
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
          'Clôturer la caisse',
          style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
        ),
        content: TextField(
          controller: controller,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: const InputDecoration(labelText: 'Compté en caisse'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Annuler'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Clôturer'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final countedMinor = (double.tryParse(controller.text) ?? 0) * 100 ~/ 1;
    if (countedMinor <= 0) {
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
          .closePosSession(session.id, countedCashMinor: countedMinor);
      await _refresh();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Caisse clôturée')));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Clôture impossible'),
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
    final kpisAsync = ref.watch(restaurantManagerKpisProvider);
    final alertsAsync = ref.watch(restaurantManagerStockAlertsProvider);
    final sessionAsync = ref.watch(restaurantManagerPosSessionProvider);
    const background = MobileSurface.background;

    return Scaffold(
      backgroundColor: background,
      appBar: MobileTopBar(
        title: 'Gestion',
        subtitle: 'KPIs, stock et caisse',
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
            kpisAsync.when(
              loading: () =>
                  const MobileEmptyLoading(label: 'Chargement des KPIs…'),
              error: (_, __) =>
                  const MobileErrorPanel(message: 'KPIs indisponibles'),
              data: (kpis) => Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _KpiTile(
                    label: 'Chiffre du jour',
                    value: _formatMinor(kpis.todayRevenueMinor, kpis.currency),
                  ),
                  _KpiTile(label: 'Commandes', value: '${kpis.ordersCount}'),
                  _KpiTile(
                    label: 'Panier moyen',
                    value: _formatMinor(kpis.avgBasketMinor, kpis.currency),
                  ),
                  _KpiTile(
                    label: 'Tables ouvertes',
                    value: '${kpis.tablesOpenedToday}',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            Text(
              'Caisse',
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 8),
            sessionAsync.when(
              loading: () => const SizedBox.shrink(),
              error: (_, __) =>
                  const MobileErrorPanel(message: 'Session indisponible'),
              data: (session) => session == null
                  ? Text(
                      'Aucune session de caisse ouverte',
                      style: AppTypography.bodySmall.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    )
                  : MobileListCard(
                      icon: Icons.point_of_sale_outlined,
                      iconColor: AppColors.finance,
                      title: 'Session #${session.id}',
                      subtitle: session.isOpen ? 'ouverte' : session.status,
                      trailing: session.isOpen
                          ? FilledButton(
                              onPressed: _busy
                                  ? null
                                  : () => _closeSession(session),
                              child: const Text('Clôturer'),
                            )
                          : null,
                    ),
            ),
            const SizedBox(height: 24),
            Text(
              'Alertes stock',
              style: AppTypography.subtitle.copyWith(color: MobileSurface.text),
            ),
            const SizedBox(height: 8),
            alertsAsync.when(
              loading: () => const SizedBox.shrink(),
              error: (_, __) =>
                  const MobileErrorPanel(message: 'Alertes indisponibles'),
              data: (alerts) => alerts.isEmpty
                  ? Text(
                      'Aucune alerte de seuil',
                      style: AppTypography.bodySmall.copyWith(
                        color: MobileSurface.secondary,
                      ),
                    )
                  : Column(
                      children: alerts.map((alert) {
                        return MobileListCard(
                          icon: Icons.warning_amber_outlined,
                          iconColor: AppColors.warning,
                          title: alert.ingredient ?? 'Ingrédient #${alert.id}',
                          subtitle:
                              'Stock : ${alert.quantity} / seuil : ${alert.alertThreshold ?? '-'}',
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
