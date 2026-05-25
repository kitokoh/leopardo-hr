import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_core/models/vehicle_position.dart';

final myVehiclesProvider = FutureProvider<List<VehiclePosition>>((ref) async {
  final repo = ref.watch(vehiclePositionRepositoryProvider);
  return await repo.getMyVehicles();
});
