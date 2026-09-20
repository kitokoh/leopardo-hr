import 'package:leopardo_core/core/api/api_client.dart';
import 'package:leopardo_core/core/api/api_payload.dart';

import '../models/camera_alert.dart';
import '../models/camera_device.dart';
import '../models/camera_event.dart';

/// Accès aux endpoints du module Surveillance Caméras (BC-19 DEVICE, #7426).
///
/// Le module backend est servi sous `/cameras/*` (routes
/// api/routes/modules/cameras.php, middlewares `auth:sanctum` + `tenant` +
/// `module.cameras` + `api.manager`). Un tenant sans le flag `cameras`
/// reçoit un 403 `FEATURE_NOT_ENABLED` ; un rôle non-manager un 403 — les
/// écrans affichent alors un refus explicite, jamais un écran vide (AC 2).
///
/// Aucune URL/identifiant RTSP ne transite par ces endpoints (AC 4) : seuls
/// `stream_url` (WebRTC MediaMTX) et le `stream_token` JWT signé circulent.
class CamerasRepository {
  CamerasRepository(this._apiClient);

  final ApiClient _apiClient;

  static const int _pageSize = 50;

  /// Mur des caméras du tenant (GET /cameras) + limite plan.
  Future<CameraWall> listCameras() async {
    final response = await _apiClient.requestWithRetry('/cameras');

    final cameras = extractDataList(response.data)
        .whereType<Map>()
        .map((item) => CameraDevice.fromJson(item.cast<String, dynamic>()))
        .toList();

    CameraPlanLimit? planLimit;
    final payload = response.data;
    if (payload is Map && payload['plan_limit'] is Map) {
      planLimit = CameraPlanLimit.fromJson(
        (payload['plan_limit'] as Map).cast<String, dynamic>(),
      );
    }

    return CameraWall(cameras: cameras, planLimit: planLimit);
  }

  /// Détail d'une caméra (GET /cameras/{id}).
  Future<CameraDevice> getCamera(int cameraId) async {
    final response = await _apiClient.requestWithRetry('/cameras/$cameraId');
    return CameraDevice.fromJson(extractDataMap(response.data));
  }

  /// Régénère un stream token signé pour le direct
  /// (GET /cameras/{id}/stream-token) — même contrat que la surface web
  /// (#7425) : payload caméra avec `stream_url` + `stream_token` frais.
  Future<CameraDevice> streamToken(int cameraId) async {
    final response = await _apiClient.requestWithRetry(
      '/cameras/$cameraId/stream-token',
    );
    return CameraDevice.fromJson(extractDataMap(response.data));
  }

  /// Journal des événements (GET /cameras/events, #7427).
  Future<List<CameraEvent>> listEvents({
    int? cameraId,
    String? type,
    String? severity,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/cameras/events',
      queryParameters: {
        'per_page': _pageSize,
        if (cameraId != null) 'camera_id': cameraId,
        if (type != null && type.isNotEmpty) 'type': type,
        if (severity != null && severity.isNotEmpty) 'severity': severity,
      },
    );

    return extractDataList(response.data)
        .whereType<Map>()
        .map((item) => CameraEvent.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  /// Alertes dédupliquées (GET /cameras/alerts, #7427).
  Future<List<CameraAlert>> listAlerts({
    String? status,
    String? severity,
    int? cameraId,
  }) async {
    final response = await _apiClient.requestWithRetry(
      '/cameras/alerts',
      queryParameters: {
        'per_page': _pageSize,
        if (status != null && status.isNotEmpty) 'status': status,
        if (severity != null && severity.isNotEmpty) 'severity': severity,
        if (cameraId != null) 'camera_id': cameraId,
      },
    );

    return extractDataList(response.data)
        .whereType<Map>()
        .map((item) => CameraAlert.fromJson(item.cast<String, dynamic>()))
        .toList();
  }

  /// Acquitte une alerte (POST /cameras/alerts/{id}/acknowledge, idempotent).
  Future<CameraAlert> acknowledgeAlert(int alertId) async {
    final response = await _apiClient.requestWithRetry(
      '/cameras/alerts/$alertId/acknowledge',
      method: 'POST',
    );
    return CameraAlert.fromJson(extractDataMap(response.data));
  }

  /// Clôture une alerte (POST /cameras/alerts/{id}/resolve, idempotent).
  Future<CameraAlert> resolveAlert(int alertId) async {
    final response = await _apiClient.requestWithRetry(
      '/cameras/alerts/$alertId/resolve',
      method: 'POST',
    );
    return CameraAlert.fromJson(extractDataMap(response.data));
  }
}
