import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_core/core/providers/core_providers.dart';
import 'package:leopardo_core/features/stock/data/stock_repository.dart';

/// Repository de stock Retail (POS tablette et gérant).
final retailStockRepositoryProvider = Provider<StockRepository>((ref) {
  return StockRepository(
    ref.watch(apiClientProvider),
    vertical: StockVertical.retail,
  );
});

/// Repository de stock Restaurant (gérant).
final restaurantStockRepositoryProvider = Provider<StockRepository>((ref) {
  return StockRepository(
    ref.watch(apiClientProvider),
    vertical: StockVertical.restaurant,
  );
});
