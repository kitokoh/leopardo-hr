import 'package:dio/dio.dart';
import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';
import 'package:leopardo_core/core/api/idempotency_keys.dart';
import 'package:leopardo_employee/features/smart_attendance/data/models/geo_attendance_session.dart';
import 'package:leopardo_employee/features/smart_attendance/data/models/smart_attendance_config.dart';

/// Repository pour le module Pointage Intelligent (Smart Attendance).
/// Effectue tous les appels API liés à la géolocalisation et aux préférences employé.
class SmartAttendanceRepository {
  final ApiClient apiClient;

  SmartAttendanceRepository(this.apiClient);

  static const _readTimeout = Duration(seconds: 6);
  static const _writeTimeout = Duration(seconds: 8);

  /// Récupère la configuration de pointage GPS de l'entreprise.
  /// GET /api/v1/attendance/config
  Future<SmartAttendanceConfig> getConfig() async {
    final response = await apiClient.requestWithRetry(
      '/attendance/config',
      timeoutOverride: _readTimeout,
    );
    final data = extractDataMap(response.data);
    return SmartAttendanceConfig.fromJson(data);
  }

  /// Envoie un événement géographique (entrée ou sortie de zone).
  /// POST /api/v1/attendance/geo-events
  ///
  /// [eventType] : 'zone_enter' ou 'zone_exit'
  /// [latitude] : latitude courante de l'employé
  /// [longitude] : longitude courante de l'employé
  /// [accuracy] : précision GPS en mètres
  Future<void> sendGeoEvent({
    required String eventType,
    required double latitude,
    required double longitude,
    required int accuracy,
  }) async {
    assert(
      eventType == 'zone_enter' || eventType == 'zone_exit',
      'eventType doit être "zone_enter" ou "zone_exit"',
    );

    // RTMX (#5407) : une clé d'idempotence par événement géo — un rejeu
    // réseau (timeout après commit serveur) ne crée pas de doublon (le
    // serveur rejoue la 1ʳᵉ réponse 2xx, #5277).
    await apiClient.requestWithRetry(
      '/attendance/geo-events',
      method: 'POST',
      data: {
        'event_type': eventType,
        'latitude': latitude,
        'longitude': longitude,
        'accuracy': accuracy,
      },
      maxRetriesOverride: 0,
      timeoutOverride: _writeTimeout,
      options: Options(
        headers: {'Idempotency-Key': IdempotencyKeys.newKey()},
      ),
    );
  }

  /// Met à jour la préférence de mode de pointage de l'employé.
  /// PUT /api/v1/attendance/preferences
  ///
  /// [preferredMode] : 'gps_auto' | 'qr_code' | 'manual'
  Future<void> updatePreference(String preferredMode) async {
    await apiClient.requestWithRetry(
      '/attendance/preferences',
      method: 'PUT',
      data: {'preferred_mode': preferredMode},
      maxRetriesOverride: 0,
      timeoutOverride: _writeTimeout,
    );
  }

  /// Récupère la liste des sessions GPS de l'employé connecté.
  /// GET /api/v1/attendance/my-sessions
  Future<List<GeoAttendanceSession>> getMySessions() async {
    final response = await apiClient.requestWithRetry(
      '/attendance/my-sessions',
      timeoutOverride: _readTimeout,
    );
    final items = extractDataList(response.data);
    return items
        .whereType<Map>()
        .map((e) => GeoAttendanceSession.fromJson(e.cast<String, dynamic>()))
        .toList();
  }

  /// Extrait le sous-objet 'data' d'une réponse API.
  static Map<String, dynamic> extractDataMap(dynamic responseData) {
    if (responseData is Map) {
      final map = responseData.cast<String, dynamic>();
      final payload = map['data'];
      if (payload is Map) return payload.cast<String, dynamic>();
      return map;
    }
    return const {};
  }
}
