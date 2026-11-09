import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/features/stock/data/stock_repository.dart';
import 'package:leopardo_core/features/stock/presentation/stock_screen.dart';

import '../../core/api/retail_api_client.dart';
import '../../core/api/retail_pos_stock_data_source.dart';

/// Provider de la source de stock du POS tablette (#9274).
///
/// Réutilise le Dio et la file offline du client de caisse : une réception
/// saisie hors ligne part dans la MÊME file que les paiements, donc le même
/// bandeau de synchronisation la montre et le même moteur la rejoue.
final retailPosStockDataSourceProvider = Provider<StockDataSource>((ref) {
  final api = ref.watch(retailApiClientProvider);

  return RetailPosStockDataSource(dio: api.dio, queue: api.queue);
});

/// Onglet Stock de la caisse tablette (#9274).
///
/// Écran partagé `StockScreen` (leopardo_core) branché sur la source POS : le
/// vendeur consulte les niveaux de SON emplacement, voit les alertes et
/// réceptionne un carton sans quitter la session de caisse.
///
/// Le mobile reste une OPTION : tout ce qui est faisable ici l'est aussi en
/// web (voir `docs/mobile/STOCK_MOBILE.md`).
class RetailPosStockScreen extends ConsumerWidget {
  const RetailPosStockScreen({super.key, required this.locationId});

  final int locationId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return StockScreen(
      repositoryProvider: retailPosStockDataSourceProvider,
      locationId: locationId,
      title: 'Stock',
      subtitle: 'Emplacement $locationId',
    );
  }
}
