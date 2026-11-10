import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/features/stock/presentation/stock_screen.dart';
import 'package:leopardo_core/features/stock/providers/stock_providers.dart';
import 'package:leopardo_core/l10n/l10n.dart';

/// Écran de stock du gérant restaurant (#9273).
///
/// Simple habillage de [StockScreen] : la logique (lecture, saisie, états) est
/// partagée dans `leopardo_core` — dupliquer l'écran par app est exactement ce
/// que la garde `check-mobile-duplicated-drift.sh` interdit.
///
/// Le mobile reste une OPTION : réception, ajustement et consultation de
/// l'historique existent aussi en web (voir `docs/mobile/STOCK_MOBILE.md`).
class RestaurantStockScreen extends ConsumerWidget {
  const RestaurantStockScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;

    return StockScreen(
      repositoryProvider: restaurantStockRepositoryProvider,
      title: l10n.stockMobileTitle,
      subtitle: l10n.stockMobileSubtitle,
    );
  }
}
