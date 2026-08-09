import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_employee/features/smart_attendance/data/models/smart_attendance_config.dart';
import 'package:leopardo_employee/features/smart_attendance/services/geofence_service.dart';

/// Tests critiques du géofencing (F-21, #1551).
///
/// Couvre : distance Haversine, position dans/hors zone, horizon (limite du
/// rayon), transitions entrée/sortie, config désactivée/invalide, tolérance
/// GPS (position trop imprécise ignorée) et réinitialisation d'état.
void main() {
  // Zone de test : Alger (36.7538, 3.0588), rayon 500 m.
  const centerLat = 36.7538;
  const centerLng = 3.0588;
  const radius = 500.0;

  SmartAttendanceConfig config({
    bool gpsEnabled = true,
    double? lat = centerLat,
    double? lng = centerLng,
    double? r = radius,
  }) {
    return SmartAttendanceConfig(
      gpsEnabled: gpsEnabled,
      latitude: lat,
      longitude: lng,
      radius: r,
    );
  }

  group('distanceMeters (formule de Haversine)', () {
    test('1 degré de latitude ≈ 111,2 km', () {
      final service = GeofenceService();
      final distance = service.distanceMeters(0, 0, 1, 0);
      // 6371000 * π/180 = 111 194,9 m
      expect(distance, closeTo(111194.9, 10));
    });

    test('même point → 0 mètre', () {
      final service = GeofenceService();
      expect(service.distanceMeters(36.7538, 3.0588, 36.7538, 3.0588), 0);
    });

    test('distance symétrique (A→B == B→A)', () {
      final service = GeofenceService();
      final ab = service.distanceMeters(36.75, 3.05, 36.80, 3.10);
      final ba = service.distanceMeters(36.80, 3.10, 36.75, 3.05);
      expect(ab, closeTo(ba, 1e-6));
    });
  });

  group('checkPosition — dans / hors / horizon', () {
    test('position au centre → dans la zone', () {
      final service = GeofenceService();
      final event = service.checkPosition(centerLat, centerLng, config());
      // Première vérification : initialise l'état sans événement.
      expect(event, ZoneEvent.none);
      expect(service.isCurrentlyInside, isTrue);
      expect(service.hasBeenChecked, isTrue);
    });

    test('position à ~333 m du centre (0.003°) → dans la zone', () {
      final service = GeofenceService();
      service.checkPosition(centerLat + 0.003, centerLng, config());
      expect(service.isCurrentlyInside, isTrue);
    });

    test('position à ~1112 m du centre (0.01°) → hors zone', () {
      final service = GeofenceService();
      service.checkPosition(centerLat + 0.01, centerLng, config());
      expect(service.isCurrentlyInside, isFalse);
    });

    test('position exactement à l\'horizon (distance == rayon) → dans la zone', () {
      final service = GeofenceService();
      // 500 m de latitude ≈ 0.00449°.
      service.checkPosition(centerLat + 0.00449, centerLng, config());
      expect(service.isCurrentlyInside, isTrue);
    });
  });

  group('checkPosition — transitions entrée/sortie', () {
    test('sortie → entrée déclenche ZoneEvent.enter une seule fois', () {
      final service = GeofenceService();
      // Départ hors zone (initialise l'état).
      expect(service.checkPosition(centerLat + 0.01, centerLng, config()),
          ZoneEvent.none);
      // Entrée dans la zone → événement.
      expect(service.checkPosition(centerLat + 0.001, centerLng, config()),
          ZoneEvent.enter);
      // Toujours dans la zone → aucun événement (pas de doublon).
      expect(service.checkPosition(centerLat, centerLng, config()),
          ZoneEvent.none);
    });

    test('entrée → sortie déclenche ZoneEvent.exit une seule fois', () {
      final service = GeofenceService();
      service.checkPosition(centerLat, centerLng, config());
      expect(service.checkPosition(centerLat + 0.02, centerLng, config()),
          ZoneEvent.exit);
      expect(service.checkPosition(centerLat + 0.03, centerLng, config()),
          ZoneEvent.none);
    });

    test('aller-retour au voisinage de l\'horizon', () {
      final service = GeofenceService();
      service.checkPosition(centerLat, centerLng, config());
      // Sort légèrement (600 m) → exit.
      expect(service.checkPosition(centerLat + 0.0054, centerLng, config()),
          ZoneEvent.exit);
      // Rentre (400 m) → enter.
      expect(service.checkPosition(centerLat + 0.0036, centerLng, config()),
          ZoneEvent.enter);
    });
  });

  group('checkPosition — configuration désactivée / invalide', () {
    test('gpsEnabled == false → aucun événement, aucun état', () {
      final service = GeofenceService();
      final event = service.checkPosition(
        centerLat,
        centerLng,
        config(gpsEnabled: false),
      );
      expect(event, ZoneEvent.none);
      expect(service.hasBeenChecked, isFalse);
    });

    test('zone incomplète (radius null) → aucun événement', () {
      final service = GeofenceService();
      final event = service.checkPosition(
        centerLat,
        centerLng,
        config(lat: centerLat, lng: centerLng, r: null),
      );
      expect(event, ZoneEvent.none);
      expect(service.hasBeenChecked, isFalse);
    });
  });

  group('checkPosition — fiabilité GPS (tolérance de précision, F-21)', () {
    test('précision dégradée > tolérance → mesure ignorée, état inchangé', () {
      final service = GeofenceService();
      // Rayon 100 m → tolérance = max(50, 100) = 100 m.
      final cfg = config(r: 100.0);
      service.checkPosition(centerLat, centerLng, cfg); // état = inside
      // Position très imprécise (500 m) : ignorée, on reste inside sans événement.
      final event = service.checkPosition(
        centerLat + 0.02,
        centerLng,
        cfg,
        accuracyMeters: 500,
      );
      expect(event, ZoneEvent.none);
      expect(service.isCurrentlyInside, isTrue);
      expect(service.lastLatitude, centerLat);
      expect(service.lastLongitude, centerLng);
    });

    test('précision acceptable (≤ tolérance) → mesure traitée normalement', () {
      final service = GeofenceService();
      final cfg = config(r: 500.0); // tolérance = 500 m
      service.checkPosition(centerLat, centerLng, cfg); // inside
      final event = service.checkPosition(
        centerLat + 0.02,
        centerLng,
        cfg,
        accuracyMeters: 200,
      );
      expect(event, ZoneEvent.exit); // hors zone, précision OK
      expect(service.lastLatitude, centerLat + 0.02);
    });

    test('aucune accuracy fournie → comportement historique inchangé', () {
      final service = GeofenceService();
      final event = service.checkPosition(centerLat, centerLng, config());
      expect(event, ZoneEvent.none);
      expect(service.isCurrentlyInside, isTrue);
    });
  });

  group('reset', () {
    test('réinitialise l\'état interne', () {
      final service = GeofenceService();
      service.checkPosition(centerLat, centerLng, config());
      expect(service.hasBeenChecked, isTrue);
      service.reset();
      expect(service.hasBeenChecked, isFalse);
      expect(service.lastCheckTime, isNull);
      expect(service.lastLatitude, isNull);
      expect(service.lastLongitude, isNull);
    });
  });
}
