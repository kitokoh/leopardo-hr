import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/models/vehicle_position.dart';

class VehiclePositionRepository {
  final ApiClient apiClient;

  VehiclePositionRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 8);

  Future<VehiclePosition> getPosition(int vehicleId) async {
    final response = await apiClient.requestWithRetry(
      '/vehicles/$vehicleId/position',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    return VehiclePosition.fromJson(extractDataMap(response.data));
  }

  Future<List<VehiclePosition>> getMyVehicles() async {
    final response = await apiClient.requestWithRetry(
      '/me/vehicles',
      maxRetriesOverride: 0,
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items.map((e) => VehiclePosition.fromJson(e)).toList();
  }
}
