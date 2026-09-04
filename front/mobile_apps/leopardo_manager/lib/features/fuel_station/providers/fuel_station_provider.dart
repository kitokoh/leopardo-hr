import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_manager/core/providers/core_providers.dart';
import 'package:leopardo_manager/features/fuel_station/data/fuel_station_repository.dart';

/// Shifts actifs du pompiste connecté (FUEL-013, #5807).
final fuelMyShiftsProvider = FutureProvider<List<FuelShiftAssignmentDto>>((
  ref,
) async {
  final repo = ref.watch(fuelStationRepositoryProvider);
  return await repo.myShifts();
});

/// Enregistre un relevé de compteur et retourne le résultat (anomalie/delta).
final fuelRecordReadingProvider = FutureProvider.autoDispose
    .family<FuelReadingResultDto, FuelReadingInput>((ref, input) async {
      final repo = ref.watch(fuelStationRepositoryProvider);
      return await repo.recordReading(
        stationId: input.stationId,
        pumpId: input.pumpId,
        meterId: input.meterId,
        readingValueMinor: input.readingValueMinor,
        idempotencyKey: input.idempotencyKey,
        shiftId: input.shiftId,
      );
    });

class FuelReadingInput {
  final int stationId;
  final int pumpId;
  final int meterId;
  final int readingValueMinor;
  final String idempotencyKey;
  final int? shiftId;

  FuelReadingInput({
    required this.stationId,
    required this.pumpId,
    required this.meterId,
    required this.readingValueMinor,
    required this.idempotencyKey,
    this.shiftId,
  });
}
