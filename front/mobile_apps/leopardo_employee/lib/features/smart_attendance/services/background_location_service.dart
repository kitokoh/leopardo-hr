import 'dart:async';

import 'package:geolocator/geolocator.dart';
import 'package:leopardo_employee/features/smart_attendance/data/models/smart_attendance_config.dart';
import 'package:leopardo_employee/features/smart_attendance/data/smart_attendance_repository.dart';
import 'package:leopardo_employee/features/smart_attendance/services/geofence_service.dart';

/// ─────────────────────────────────────────────────────────────────────────────
/// Pourquoi on n'utilise PAS le tracking continu ?
/// ─────────────────────────────────────────────────────────────────────────────
///
/// Le tracking GPS continu (stream de positions en permanence) consomme
/// entre 10 % et 30 % de batterie supplémentaire par heure selon les devices.
/// Sur Android, les restrictions Doze Mode et App Standby réduisent de toute
/// façon la précision après 15 min d'inactivité écran.
///
/// Approach choisie : POLLING TOUTES LES 5 MINUTES
/// ─────────────────────────────────────────────────────────────────────────────
/// • On demande explicitement une position unique toutes les 5 min.
/// • On utilise LocationAccuracy.balanced (compromis précision/conso batterie).
/// • On saute la vérification si la dernière position date de moins de 2 min
///   ET si l'employé n'a pas bougé de plus de 100m → évite les doublons inutiles.
/// • On envoie un événement API uniquement en cas de changement de zone,
///   ce qui limite les appels réseau à 2 max par journée typique
///   (une entrée + une sortie).
///
/// Pour une rigueur encore plus forte en production :
/// • Utiliser workmanager (Android) / BGTaskScheduler (iOS) pour exécuter
///   le polling même si l'app est en arrière-plan ou fermée.
/// • Sur iOS, declarerBGTaskIdentifier dans Info.plist est obligatoire.
/// ─────────────────────────────────────────────────────────────────────────────
class BackgroundLocationService {
  final SmartAttendanceRepository _repository;
  final GeofenceService _geofenceService;

  /// Intervalle de polling en background (5 minutes)
  static const Duration _pollingInterval = Duration(minutes: 5);

  /// Seuil de déplacement minimal pour forcer une nouvelle vérification (mètres)
  static const double _movementThresholdMeters = 100.0;

  /// Délai minimal entre deux vérifications consécutives (anti-doublon)
  static const Duration _minTimeBetweenChecks = Duration(minutes: 2);

  /// Timer interne pour le polling périodique
  Timer? _pollingTimer;

  /// Configuration active
  SmartAttendanceConfig? _activeConfig;

  /// Indique si le service est en cours d'exécution
  bool _isRunning = false;

  BackgroundLocationService({
    required SmartAttendanceRepository repository,
    required GeofenceService geofenceService,
  })  : _repository = repository,
        _geofenceService = geofenceService;

  /// Démarre la surveillance de position.
  ///
  /// [config] : configuration de l'entreprise (zone GPS + mode)
  ///
  /// Vérifie les permissions avant de démarrer.
  /// Lance immédiatement une première vérification, puis toutes les 5 minutes.
  Future<void> startMonitoring(SmartAttendanceConfig config) async {
    if (_isRunning) {
      // Mise à jour de la config sans relancer le timer
      _activeConfig = config;
      return;
    }

    // Vérification préalable si le GPS est activé dans la config
    if (!config.gpsEnabled || !config.hasValidZone) {
      return;
    }

    // Demande des permissions GPS
    final hasPermission = await _requestLocationPermission();
    if (!hasPermission) return;

    _activeConfig = config;
    _isRunning = true;

    // Vérification immédiate au démarrage
    await _performCheck();

    // Démarrage du polling périodique
    _pollingTimer = Timer.periodic(_pollingInterval, (_) async {
      await _performCheck();
    });
  }

  /// Arrête la surveillance de position et libère les ressources.
  void stopMonitoring() {
    _pollingTimer?.cancel();
    _pollingTimer = null;
    _isRunning = false;
    _activeConfig = null;
    _geofenceService.reset();
  }

  /// Indique si le service est actif.
  bool get isRunning => _isRunning;

  /// Configuration actuellement utilisée.
  SmartAttendanceConfig? get activeConfig => _activeConfig;

  // ─────────────────────────────────────────────────────────────────────────
  // Méthodes internes
  // ─────────────────────────────────────────────────────────────────────────

  /// Effectue une vérification de position et envoie un événement si nécessaire.
  ///
  /// Optimisation batterie :
  /// - Ignore si la dernière vérification remonte à moins de [_minTimeBetweenChecks]
  ///   ET si on n'a pas bougé de plus de [_movementThresholdMeters].
  Future<void> _performCheck() async {
    final config = _activeConfig;
    if (config == null || !config.gpsEnabled || !config.hasValidZone) return;

    Position? position;

    try {
      // Récupération de la position courante avec précision équilibrée
      position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.balanced,
          timeLimit: Duration(seconds: 10),
        ),
      );
    } catch (e) {
      // Impossible d'obtenir la position (GPS désactivé, refus permission, timeout)
      // On ne fait rien pour préserver la batterie
      return;
    }

    // Optimisation : vérifier si on doit skipper cette mesure
    if (_shouldSkipCheck(position)) return;

    // Vérification de zone via le service de géofencing
    final event = _geofenceService.checkPosition(
      position.latitude,
      position.longitude,
      config,
    );

    // Envoi de l'événement si un changement de zone est détecté
    if (event == ZoneEvent.enter || event == ZoneEvent.exit) {
      await _sendGeoEvent(
        event: event,
        position: position,
      );
    }
  }

  /// Détermine si on doit ignorer cette vérification pour économiser la batterie.
  ///
  /// On skip si :
  /// 1. La dernière vérification date de moins de [_minTimeBetweenChecks]
  /// 2. ET l'employé n'a pas bougé de plus de [_movementThresholdMeters]
  bool _shouldSkipCheck(Position currentPosition) {
    final lastCheck = _geofenceService.lastCheckTime;
    final lastLat = _geofenceService.lastLatitude;
    final lastLng = _geofenceService.lastLongitude;

    if (lastCheck == null || lastLat == null || lastLng == null) {
      // Première vérification : ne pas skiper
      return false;
    }

    final timeSinceLast = DateTime.now().difference(lastCheck);
    if (timeSinceLast >= _minTimeBetweenChecks) {
      // Délai suffisant : ne pas ignorer
      return false;
    }

    // Calcul du déplacement depuis la dernière vérification
    final displacement = _geofenceService.distanceMeters(
      currentPosition.latitude,
      currentPosition.longitude,
      lastLat,
      lastLng,
    );

    // Skip uniquement si peu de déplacement ET délai court
    return displacement < _movementThresholdMeters;
  }

  /// Envoie l'événement géographique à l'API.
  Future<void> _sendGeoEvent({
    required ZoneEvent event,
    required Position position,
  }) async {
    try {
      await _repository.sendGeoEvent(
        eventType: event == ZoneEvent.enter ? 'zone_enter' : 'zone_exit',
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy.round(),
      );
    } catch (_) {
      // Échec silencieux en background : sera retransmis au prochain cycle
      // si le service reste actif
    }
  }

  /// Demande les permissions de localisation nécessaires.
  /// Retourne true si les permissions suffisantes sont accordées.
  Future<bool> _requestLocationPermission() async {
    // Vérification si le service de localisation est activé sur l'appareil
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) return false;

    LocationPermission permission = await Geolocator.checkPermission();

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) return false;
    }

    if (permission == LocationPermission.deniedForever) {
      return false;
    }

    // Permission "whileInUse" est suffisante pour le polling foreground/background léger
    return permission == LocationPermission.whileInUse ||
        permission == LocationPermission.always;
  }
}
