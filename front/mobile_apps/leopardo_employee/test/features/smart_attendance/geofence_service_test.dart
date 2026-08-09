import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_employee/features/smart_attendance/data/models/smart_attendance_config.dart';
import 'package:leopardo_employee/features/smart_attendance/services/geofence_service.dart';

/// Tests critiques du géofencing (F-21, #1551) : dans / hors / horizon,
/// config invalide, distance Haversine, reset.
void main() {
  // Centre : Alger (36.7538, 3.0588) — rayon 500 m.
  const centerLat = 36.7538;
  const centerLng = 3.0588;
  const radiusMeters = 500.0;

  SmartAttendanceConfig validConfig() => SmartAttendanceConfig(
        gpsEnabled: true,
        latitude: centerLat,
        longitude: centerLng,
        radius: radiusMeters,
      );

  group('GeofenceService — F-21 (#1551)', () {
    test('première vérification initialise l\'état sans événement', () {
      final service = GeofenceService();
      final event = service.checkPosition(centerLat, centerLng, validConfig());

      expect(event, ZoneEvent.none);
      expect(service.hasBeenChecked, isTrue);
      expect(service.isCurrentlyInside, isTrue);
    });

    test('position dans la zone → aucun événement (pas de transition)', () {
      final service = GeofenceService();
      // Première vérif : dans la zone (~0 m).
      service.checkPosition(centerLat, centerLng, validConfig());
      // Seconde vérif : déplacement dans le rayon (~222 m au nord).
      final event = service.checkPosition(centerLat + 0.002, centerLng, validConfig());

      expect(event, ZoneEvent.none);
      expect(service.isCurrentlyInside, isTrue);
    });

    test('transition hors → dans → ZoneEvent.enter', () {
      final service = GeofenceService();
      // 1. Hors zone (~1 110 m au nord).
      service.checkPosition(centerLat + 0.01, centerLng, validConfig());
      expect(service.isCurrentlyInside, isFalse);
      // 2. Dans la zone → entrée détectée.
      final event = service.checkPosition(centerLat + 0.002, centerLng, validConfig());

      expect(event, ZoneEvent.enter);
      expect(service.isCurrentlyInside, isTrue);
    });

    test('transition dans → hors → ZoneEvent.exit', () {
      final service = GeofenceService();
      // 1. Dans la zone.
      service.checkPosition(centerLat, centerLng, validConfig());
      // 2. Hors zone (~1 110 m) → sortie détectée.
      final event = service.checkPosition(centerLat + 0.01, centerLng, validConfig());

      expect(event, ZoneEvent.exit);
      expect(service.isCurrentlyInside, isFalse);
    });

    test('à l\'horizon exact (distance == rayon) → encore dans la zone', () {
      final service = GeofenceService();
      // ~500 m au nord ≈ rayon exact (0.00449° de latitude ≈ 499 m).
      service.checkPosition(centerLat + 0.00449, centerLng, validConfig());

      expect(service.isCurrentlyInside, isTrue);
    });

    test('config GPS désactivée → toujours none même à l\'intérieur', () {
      final service = GeofenceService();
      final config = SmartAttendanceConfig(
        gpsEnabled: false,
        latitude: centerLat,
        longitude: centerLng,
        radius: radiusMeters,
      );

      expect(service.checkPosition(centerLat, centerLng, config), ZoneEvent.none);
      expect(service.checkPosition(centerLat, centerLng, config), ZoneEvent.none);
      expect(service.isCurrentlyInside, isFalse);
    });

    test('config sans zone valide (rayon nul) → none', () {
      final service = GeofenceService();
      final config = SmartAttendanceConfig(
        gpsEnabled: true,
        latitude: centerLat,
        longitude: centerLng,
        radius: 0,
      );

      expect(config.hasValidZone, isFalse);
      expect(service.checkPosition(centerLat, centerLng, config), ZoneEvent.none);
    });

    test('distanceMeters — ordre de grandeur Haversine (~111 km/degré)', () {
      final service = GeofenceService();
      final metersPerDegree = service.distanceMeters(centerLat, centerLng, centerLat + 1, centerLng);

      // 1° de latitude ≈ 111 km (tolérance ±3 %).
      expect(metersPerDegree, closeTo(111000, 3300));
      // ~222 m pour 0.002°.
      expect(service.distanceMeters(centerLat, centerLng, centerLat + 0.002, centerLng),
          closeTo(222, 15));
    });

    test('reset() réinitialise l\'état de zone', () {
      final service = GeofenceService();
      service.checkPosition(centerLat, centerLng, validConfig());
      expect(service.hasBeenChecked, isTrue);

      service.reset();

      expect(service.hasBeenChecked, isFalse);
      expect(service.isCurrentlyInside, isFalse);
      expect(service.lastCheckTime, isNull);
      expect(service.lastLatitude, isNull);
      expect(service.lastLongitude, isNull);
    });
  });
}
