import 'dart:async';

import 'package:flutter/foundation.dart';

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

  /// File d'attente des événements géo non envoyés (issue #3862) : un
  /// `zone_enter` pendant une coupure réseau/permission n'est plus jeté
  /// silencieusement — il est conservé (borné) et rejoué au tick suivant.
  /// Persistance : mémoire (durée de vie du service background) ; la
  /// persistance Hive durable est gérée par les couches storage, hors de ce
  /// service de polling.
  final List<_PendingGeoEvent> _pendingGeoEvents = [];

  /// Taille maximale de la file (anti-croissance illimitée sur coupure longue)
  static const int _maxPendingGeoEvents = 20;

  BackgroundLocationService({
    required SmartAttendanceRepository repository,
    required GeofenceService geofenceService,
  }) : _repository = repository,
       _geofenceService = geofenceService;

  /// Démarre la surveillance de position.
  ///
  /// [config] : configuration de l'entreprise (zone GPS + mode)
  ///
  /// Retourne `true` si la surveillance est active, `false` sinon (GPS non
  /// configuré ou permission refusée) — #4960 : l'appelant doit pouvoir
  /// distinguer « ça tourne » de « rien ne tourne » (avant, un refus de
  /// permission rendait l'UI « Surveillance active » factice).
  Future<bool> startMonitoring(SmartAttendanceConfig config) async {
    if (_isRunning) {
      // Mise à jour de la config sans relancer le timer
      _activeConfig = config;
      return true;
    }

    // Vérification préalable si le GPS est activé dans la config
    if (!config.gpsEnabled || !config.hasValidZone) {
      return false;
    }

    // Demande des permissions GPS
    final hasPermission = await _requestLocationPermission();
    if (!hasPermission) return false;

    _activeConfig = config;
    _isRunning = true;

    // Vérification immédiate au démarrage
    await _performCheck();

    // Démarrage du polling périodique
    _pollingTimer = Timer.periodic(_pollingInterval, (_) async {
      await _performCheck();
    });

    return true;
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
    // Issue #3862 : rejouer d'abord les événements en attente (retour réseau
    // rattrapé au prochain tick) — best-effort, ne bloque jamais le tick.
    await _flushPendingGeoEvents();

    final config = _activeConfig;
    if (config == null || !config.gpsEnabled || !config.hasValidZone) return;

    Position? position;

    try {
      // Récupération de la position courante avec précision équilibrée
      position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
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
      // Fiabilité GPS (F-21, #1551) : la précision de la position est
      // transmise au géofencing pour ignorer les mesures trop imprécises.
      accuracyMeters: position.accuracy,
    );

    // Envoi de l'événement si un changement de zone est détecté
    if (event == ZoneEvent.enter || event == ZoneEvent.exit) {
      await _sendGeoEvent(event: event, position: position);
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
  ///
  /// Issue #3862 : en cas d'échec (coupure réseau, permission, timeout),
  /// l'événement est loggé puis mis en file pour réenvoi au prochain tick —
  /// plus de `catch (_)` muet qui perdait le pointage géo en silence.
  Future<void> _sendGeoEvent({
    required ZoneEvent event,
    required Position position,
  }) async {
    final eventType = event == ZoneEvent.enter ? 'zone_enter' : 'zone_exit';
    try {
      await _repository.sendGeoEvent(
        eventType: eventType,
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy.round(),
      );
    } catch (error, stackTrace) {
      debugPrint(
        '[BackgroundLocationService] sendGeoEvent($eventType) failed — '
        'événement mis en file pour réenvoi : $error',
      );
      _enqueueGeoEvent(
        eventType: eventType,
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy.round(),
        error: error,
        stackTrace: stackTrace,
      );
    }
  }

  /// Met en file un événement géo non envoyé (issue #3862).
  void _enqueueGeoEvent({
    required String eventType,
    required double latitude,
    required double longitude,
    required int accuracy,
    Object? error,
    StackTrace? stackTrace,
  }) {
    if (_pendingGeoEvents.length >= _maxPendingGeoEvents) {
      // Coupure très longue : on garde les plus récents (anti-explosion),
      // on ne bloque jamais le tick courant.
      _pendingGeoEvents.removeAt(0);
    }
    _pendingGeoEvents.add(
      _PendingGeoEvent(
        eventType: eventType,
        latitude: latitude,
        longitude: longitude,
        accuracy: accuracy,
        createdAt: DateTime.now(),
        error: error,
        stackTrace: stackTrace,
      ),
    );
  }

  /// Rejoue les événements géo en attente (best-effort, ne bloque jamais le
  /// tick courant). Appelé à chaque check de zone : le retour réseau est donc
  /// rattrapé au prochain cycle de polling (5 min) ou immédiat si le service
  /// reçoit un nouveau check.
  Future<void> _flushPendingGeoEvents() async {
    if (_pendingGeoEvents.isEmpty) return;

    final events = List<_PendingGeoEvent>.from(_pendingGeoEvents);
    for (final pending in events) {
      try {
        await _repository.sendGeoEvent(
          eventType: pending.eventType,
          latitude: pending.latitude,
          longitude: pending.longitude,
          accuracy: pending.accuracy,
        );
        _pendingGeoEvents.remove(pending);
      } catch (error) {
        debugPrint(
          '[BackgroundLocationService] replay ${pending.eventType} '
          '(créé ${pending.createdAt.toIso8601String()}) failed : $error',
        );
        // On garde l'événement pour un prochain tick.
        break;
      }
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

/// Événement géo en attente de réenvoi (issue #3862).
class _PendingGeoEvent {
  const _PendingGeoEvent({
    required this.eventType,
    required this.latitude,
    required this.longitude,
    required this.accuracy,
    required this.createdAt,
    this.error,
    this.stackTrace,
  });

  final String eventType;
  final double latitude;
  final double longitude;
  final int accuracy;
  final DateTime createdAt;
  final Object? error;
  final StackTrace? stackTrace;
}
