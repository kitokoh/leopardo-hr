import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/models/vehicle_position.dart';

class VehiclePositionRepository {
  final ApiClient apiClient;

  VehiclePositionRepository(this.apiClient);

  Future<VehiclePosition> getPosition(int vehicleId) async {
    final response = await apiClient.dio.get('/vehicles/$vehicleId/position');
    return VehiclePosition.fromJson(response.data['data'] ?? response.data);
  }

  Future<List<VehiclePosition>> getMyVehicles() async {
    final response = await apiClient.dio.get('/me/vehicles');
    final items = response.data['data'] as List;
    return items.map((e) => VehiclePosition.fromJson(e)).toList();
  }
}
