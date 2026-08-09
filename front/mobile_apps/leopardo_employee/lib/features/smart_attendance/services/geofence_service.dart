import 'dart:math' as math;
import 'package:leopardo_employee/features/smart_attendance/data/models/smart_attendance_config.dart';

/// Événement de zone détecté par le service de géofencing.
enum ZoneEvent {
  /// L'employé vient d'entrer dans la zone de l'entreprise
  enter,

  /// L'employé vient de sortir de la zone de l'entreprise
  exit,

  /// Pas de changement d'état (déjà dans le même état)
  none,
}

/// Service de géofencing basé sur la formule Haversine.
///
/// Ce service calcule si l'employé se trouve dans la zone géographique
/// de l'entreprise et détecte les transitions entrée/sortie.
/// Il maintient un état interne (_wasInsideZone) pour éviter les
/// doublons d'événements.
class GeofenceService {
  /// Rayon de la Terre en mètres (valeur WGS-84 équatorialale)
  static const double _earthRadiusMeters = 6371000.0;

  /// État précédent : true = était dans la zone, false = était hors zone,
  /// null = état inconnu (première vérification)
  bool? _wasInsideZone;

  /// Timestamp de la dernière vérification
  DateTime? _lastCheckTime;

  /// Dernière position connue (latitude)
  double? _lastLat;

  /// Dernière position connue (longitude)
  double? _lastLng;

  /// Vérifie si la position courante déclenche un événement de zone.
  ///
  /// [lat] : latitude courante de l'employé
  /// [lng] : longitude courante de l'employé
  /// [config] : configuration de l'entreprise (centre + rayon)
  ///
  /// Retourne :
  /// - [ZoneEvent.enter] si l'employé vient d'entrer dans la zone
  /// - [ZoneEvent.exit] si l'employé vient de sortir de la zone
  /// - [ZoneEvent.none] si l'état n'a pas changé ou si la config est invalide
  /// Fiabilité GPS (F-21, #1551) : au-delà de cette tolérance (en mètres),
  /// une position est considérée trop imprécise pour déclencher un événement
  /// de zone. Tolérance = max(50 m, rayon de la zone) : on accepte une
  /// précision dégradée tant qu'elle reste meilleure que la taille de la zone.
  static const double _accuracyFloorMeters = 50.0;

  ZoneEvent checkPosition(
    double lat,
    double lng,
    SmartAttendanceConfig config, {
    double? accuracyMeters,
  }) {
    // Vérification que la configuration GPS est valide
    if (!config.gpsEnabled || !config.hasValidZone) {
      return ZoneEvent.none;
    }

    final centerLat = config.latitude!;
    final centerLng = config.longitude!;
    final radiusMeters = config.radius!;

    // Position trop imprécise (GPS approximatif) : on ignore la mesure sans
    // modifier l'état interne — évite les faux enter/exit au voisinage de
    // l'horizon quand la précision se dégrade (test de fiabilité F-21).
    final tolerance = radiusMeters > _accuracyFloorMeters
        ? radiusMeters
        : _accuracyFloorMeters;
    if (accuracyMeters != null && accuracyMeters > tolerance) {
      return ZoneEvent.none;
    }

    // Calcul de la distance entre la position courante et le centre
    final distance = distanceMeters(lat, lng, centerLat, centerLng);
    final isInsideNow = distance <= radiusMeters;

    // Mémorisation pour les prochaines vérifications
    _lastLat = lat;
    _lastLng = lng;
    _lastCheckTime = DateTime.now();

    // Première vérification : on initialise l'état sans déclencher d'événement
    if (_wasInsideZone == null) {
      _wasInsideZone = isInsideNow;
      return ZoneEvent.none;
    }

    // Détection de transition
    if (!_wasInsideZone! && isInsideNow) {
      // Transition sortie → entrée
      _wasInsideZone = true;
      return ZoneEvent.enter;
    } else if (_wasInsideZone! && !isInsideNow) {
      // Transition entrée → sortie
      _wasInsideZone = false;
      return ZoneEvent.exit;
    }

    // Pas de changement
    return ZoneEvent.none;
  }

  /// Calcule la distance en mètres entre deux coordonnées GPS
  /// en utilisant la formule de Haversine.
  ///
  /// [lat1], [lng1] : coordonnées du premier point (degrés décimaux)
  /// [lat2], [lng2] : coordonnées du second point (degrés décimaux)
  ///
  /// Retourne la distance en mètres.
  double distanceMeters(
    double lat1,
    double lng1,
    double lat2,
    double lng2,
  ) {
    // Conversion degrés → radians
    final phi1 = _toRadians(lat1);
    final phi2 = _toRadians(lat2);
    final deltaPhi = _toRadians(lat2 - lat1);
    final deltaLambda = _toRadians(lng2 - lng1);

    // Formule Haversine
    final a = math.sin(deltaPhi / 2) * math.sin(deltaPhi / 2) +
        math.cos(phi1) *
            math.cos(phi2) *
            math.sin(deltaLambda / 2) *
            math.sin(deltaLambda / 2);

    final c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a));

    return _earthRadiusMeters * c;
  }

  /// Retourne true si l'employé est actuellement dans la zone selon
  /// le dernier état connu.
  bool get isCurrentlyInside => _wasInsideZone ?? false;

  /// Indique si une vérification a déjà été effectuée.
  bool get hasBeenChecked => _wasInsideZone != null;

  /// Timestamp de la dernière vérification de position.
  DateTime? get lastCheckTime => _lastCheckTime;

  /// Dernière latitude connue.
  double? get lastLatitude => _lastLat;

  /// Dernière longitude connue.
  double? get lastLongitude => _lastLng;

  /// Réinitialise l'état de la zone (utile lors d'un changement d'entreprise
  /// ou d'une mise à jour de configuration).
  void reset() {
    _wasInsideZone = null;
    _lastCheckTime = null;
    _lastLat = null;
    _lastLng = null;
  }

  // Conversion degrés → radians
  static double _toRadians(double degrees) => degrees * math.pi / 180.0;
}
