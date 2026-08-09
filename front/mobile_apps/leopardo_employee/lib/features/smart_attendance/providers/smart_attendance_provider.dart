import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_employee/core/providers/core_providers.dart';
import 'package:leopardo_employee/features/smart_attendance/data/models/geo_attendance_session.dart';
import 'package:leopardo_employee/features/smart_attendance/data/models/smart_attendance_config.dart';
import 'package:leopardo_employee/features/smart_attendance/data/smart_attendance_repository.dart';
import 'package:leopardo_employee/features/smart_attendance/services/background_location_service.dart';
import 'package:leopardo_employee/features/smart_attendance/services/geofence_service.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Providers d'infrastructure
// ─────────────────────────────────────────────────────────────────────────────

/// Repository d'accès aux APIs Smart Attendance.
final smartAttendanceRepositoryProvider = Provider<SmartAttendanceRepository>((
  ref,
) {
  final apiClient = ref.watch(apiClientProvider);
  return SmartAttendanceRepository(apiClient);
});

/// Service de géofencing (calcul zone/distance Haversine).
final geofenceServiceProvider = Provider<GeofenceService>((ref) {
  return GeofenceService();
});

/// Service de surveillance GPS en background.
final backgroundLocationServiceProvider = Provider<BackgroundLocationService>((
  ref,
) {
  final repository = ref.watch(smartAttendanceRepositoryProvider);
  final geofenceService = ref.watch(geofenceServiceProvider);

  final service = BackgroundLocationService(
    repository: repository,
    geofenceService: geofenceService,
  );

  // Nettoyage lors de la destruction du provider
  ref.onDispose(service.stopMonitoring);

  return service;
});

// ─────────────────────────────────────────────────────────────────────────────
// Configuration
// ─────────────────────────────────────────────────────────────────────────────

/// Charge la configuration Smart Attendance de l'entreprise.
/// Ex: GPS activé, mode forcé, coordonnées du centre, rayon.
final smartAttendanceConfigProvider = FutureProvider<SmartAttendanceConfig>((
  ref,
) async {
  final repository = ref.watch(smartAttendanceRepositoryProvider);
  return repository.getConfig();
});

// ─────────────────────────────────────────────────────────────────────────────
// Mode effectif
// ─────────────────────────────────────────────────────────────────────────────

/// Résout le mode de pointage effectif pour l'employé courant.
///
/// Logique de priorité :
/// 1. Si l'entreprise a un mode forcé → ce mode s'applique, l'employé ne peut pas choisir
/// 2. Sinon → la préférence stockée de l'employé, ou 'manual' par défaut
///
/// Retourne une chaîne : 'gps_auto' | 'qr_code' | 'manual'
final attendanceModeProvider = Provider<String>((ref) {
  final configAsync = ref.watch(smartAttendanceConfigProvider);

  return configAsync.when(
    data: (config) {
      // Mode forcé par l'entreprise : on l'applique directement
      if (config.hasForced) return config.forcedMode!;
      // Pas de mode forcé : mode manuel par défaut (l'employé peut changer)
      return 'manual';
    },
    loading: () => 'manual',
    error: (_, __) => 'manual',
  );
});

/// Indique si l'employé peut changer son mode de pointage.
/// Retourne false si l'entreprise a forcé un mode.
final canChangeAttendanceModeProvider = Provider<bool>((ref) {
  final configAsync = ref.watch(smartAttendanceConfigProvider);
  return configAsync.maybeWhen(
    data: (config) => !config.hasForced,
    orElse: () => false,
  );
});

// ─────────────────────────────────────────────────────────────────────────────
// Session GPS active
// ─────────────────────────────────────────────────────────────────────────────

/// État de la session GPS active de l'employé.
class ActiveGeoSessionState {
  /// Session GPS en cours (null si aucune session active)
  final GeoAttendanceSession? activeSession;

  /// Liste de toutes les sessions récentes
  final List<GeoAttendanceSession> recentSessions;

  /// Chargement en cours
  final bool isLoading;

  /// Message d'erreur éventuel
  final String? error;

  /// Indique si la surveillance GPS est démarrée
  final bool isMonitoring;

  const ActiveGeoSessionState({
    this.activeSession,
    this.recentSessions = const [],
    this.isLoading = false,
    this.error,
    this.isMonitoring = false,
  });

  ActiveGeoSessionState copyWith({
    GeoAttendanceSession? activeSession,
    List<GeoAttendanceSession>? recentSessions,
    bool? isLoading,
    String? error,
    bool? isMonitoring,
    bool clearError = false,
    bool clearActive = false,
  }) {
    return ActiveGeoSessionState(
      activeSession: clearActive ? null : (activeSession ?? this.activeSession),
      recentSessions: recentSessions ?? this.recentSessions,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      isMonitoring: isMonitoring ?? this.isMonitoring,
    );
  }
}

/// Notifier gérant la session GPS active et le démarrage/arrêt de la surveillance.
class ActiveGeoSessionNotifier extends StateNotifier<ActiveGeoSessionState> {
  final SmartAttendanceRepository _repository;
  final BackgroundLocationService _backgroundService;

  ActiveGeoSessionNotifier(this._repository, this._backgroundService)
      : super(const ActiveGeoSessionState()) {
    loadSessions();
  }

  /// Charge la liste des sessions GPS de l'employé.
  Future<void> loadSessions() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final sessions = await _repository.getMySessions();

      // La session active est la première session sans date de fin
      final active = sessions.firstWhere(
        (s) => s.isActive,
        orElse: () => sessions.isEmpty ? sessions.first : sessions.first,
      );

      // Filtrage : session active uniquement si réellement en cours
      final activeSession = sessions.any((s) => s.isActive) ? active : null;

      state = state.copyWith(
        isLoading: false,
        recentSessions: sessions,
        activeSession: activeSession,
        clearActive: activeSession == null,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error:
            'Impossible de charger les sessions GPS. Vérifiez votre connexion.',
      );
    }
  }

  /// Démarre la surveillance GPS en background.
  Future<void> startMonitoring(SmartAttendanceConfig config) async {
    if (state.isMonitoring) return;

    await _backgroundService.startMonitoring(config);
    state = state.copyWith(isMonitoring: true);
  }

  /// Arrête la surveillance GPS.
  void stopMonitoring() {
    _backgroundService.stopMonitoring();
    state = state.copyWith(isMonitoring: false);
  }

  /// Rafraîchit les sessions et l'état.
  Future<void> refresh() => loadSessions();
}

/// Provider de la session GPS active — StateNotifierProvider.
final activeGeoSessionProvider =
    StateNotifierProvider<ActiveGeoSessionNotifier, ActiveGeoSessionState>((
  ref,
) {
  final repository = ref.watch(smartAttendanceRepositoryProvider);
  final backgroundService = ref.watch(backgroundLocationServiceProvider);
  return ActiveGeoSessionNotifier(repository, backgroundService);
});

/// Provider des sessions récentes uniquement (dérivé de activeGeoSessionProvider)
final recentGeoSessionsProvider = Provider<List<GeoAttendanceSession>>((ref) {
  return ref.watch(activeGeoSessionProvider).recentSessions;
});
