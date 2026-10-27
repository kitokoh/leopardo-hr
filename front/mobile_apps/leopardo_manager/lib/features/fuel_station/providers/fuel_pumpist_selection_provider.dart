import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/fuel_station/data/fuel_station_repository.dart';

/// FuelStation (#8585, FUEL-013) — chaîne de sélection du pompiste.
///
/// La version gelée postait `stationId: 0, pumpId: 0, meterId: 0`, rejetés par
/// le backend. Ces providers matérialisent la chaîne réelle
/// station → pompe → compteur, avec deux invariants portés par la structure :
///
/// - changer de station remet la pompe ET le compteur à zéro (sinon un
///   `pumpId` appartenant à une autre station produit un 404) ;
/// - changer de pompe remet le compteur à zéro (les compteurs sont montés sur
///   une pompe précise).
///
/// Les providers `fuelMyShiftsProvider` / `fuelRecordReadingProvider` vivent
/// dans `fuel_station_provider.dart` : les redéclarer ici créerait deux
/// sources de vérité et un conflit d'import.

/// Stations du parc — première étape de la chaîne de sélection.
final fuelStationsProvider = FutureProvider<List<FuelStationDto>>((ref) async {
  final repo = ref.watch(fuelStationRepositoryProvider);
  return await repo.stations();
});

/// Station sélectionnée. `null` = rien de choisi : l'écran doit demander une
/// sélection au lieu de poster un identifiant `0`.
final fuelSelectedStationProvider = StateProvider<int?>((ref) => null);

/// Pompes de la station sélectionnée (vide tant qu'aucune station n'est choisie).
final fuelPumpsProvider = FutureProvider<List<FuelPumpDto>>((ref) async {
  final stationId = ref.watch(fuelSelectedStationProvider);
  if (stationId == null) return const <FuelPumpDto>[];
  final repo = ref.watch(fuelStationRepositoryProvider);
  return await repo.pumps(stationId);
});

/// Pompe sélectionnée.
final fuelSelectedPumpProvider = StateProvider<int?>((ref) => null);

/// Compteurs réellement montés sur la pompe sélectionnée.
final fuelMetersProvider = FutureProvider<List<FuelMeterDto>>((ref) async {
  final stationId = ref.watch(fuelSelectedStationProvider);
  final pumpId = ref.watch(fuelSelectedPumpProvider);
  if (stationId == null || pumpId == null) return const <FuelMeterDto>[];
  final repo = ref.watch(fuelStationRepositoryProvider);
  return await repo.meters(stationId, pumpId: pumpId);
});

/// Compteur sélectionné.
final fuelSelectedMeterProvider = StateProvider<int?>((ref) => null);
