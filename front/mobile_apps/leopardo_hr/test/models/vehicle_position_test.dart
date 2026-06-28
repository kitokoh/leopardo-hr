import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_core/models/vehicle_position.dart';

void main() {
  group('VehiclePosition model', () {
    test('fromJson maps all fields including GPS coordinates', () {
      final vehicle = VehiclePosition.fromJson({
        'vehicle_id': 1,
        'plate_number': '01234-116-16',
        'brand': 'Toyota',
        'model': 'Hilux',
        'latitude': 36.7525,
        'longitude': 3.0420,
        'speed': 65.5,
        'updated_at': '2026-05-13T14:30:00Z',
      });

      expect(vehicle.vehicleId, 1);
      expect(vehicle.plateNumber, '01234-116-16');
      expect(vehicle.brand, 'Toyota');
      expect(vehicle.model, 'Hilux');
      expect(vehicle.latitude, 36.7525);
      expect(vehicle.longitude, 3.0420);
      expect(vehicle.speed, 65.5);
      expect(vehicle.updatedAt, '2026-05-13T14:30:00Z');
    });

    test('fromJson handles stationary vehicle (null speed)', () {
      final vehicle = VehiclePosition.fromJson({
        'vehicle_id': 2,
        'plate_number': '05678-220-31',
        'latitude': 33.5731,
        'longitude': -7.5898,
        'speed': null,
      });

      expect(vehicle.speed, isNull);
      expect(vehicle.brand, isNull);
      expect(vehicle.model, isNull);
    });

    test('fromJson falls back to id when vehicle_id missing', () {
      final vehicle = VehiclePosition.fromJson({
        'id': 99,
        'plate_number': 'TEST-001',
        'latitude': 0,
        'longitude': 0,
      });

      expect(vehicle.vehicleId, 99);
    });

    test('fromJson defaults coordinates to zero', () {
      final vehicle = VehiclePosition.fromJson({
        'vehicle_id': 3,
        'plate_number': 'NO-GPS',
      });

      expect(vehicle.latitude, 0);
      expect(vehicle.longitude, 0);
      expect(vehicle.plateNumber, 'NO-GPS');
    });
  });
}
