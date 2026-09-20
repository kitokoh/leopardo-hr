import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:leopardo_cameras/core/providers/core_providers.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_alert.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_device.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_event.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';

/// Providers du module Surveillance Caméras (BC-19 DEVICE, #7426).

/// Classification d'un refus d'accès au module (AC 2 : un tenant sans le
/// flag `cameras` — ou un rôle non-manager — voit un refus explicite,
/// jamais un écran vide).
enum CamerasAccessRefusal {
  /// 403 FEATURE_NOT_ENABLED : le tenant n'a pas le module (plan/flag).
  featureLocked,

  /// 403 autre (rôle non-manager, COMPANY_NOT_FOUND…).
  forbidden,

  /// Toute autre erreur (réseau, 5xx…).
  none,
}

CamerasAccessRefusal classifyRefusal(Object error) {
  if (error is ApiException && error.statusCode == 403) {
    return error.code == 'FEATURE_NOT_ENABLED'
        ? CamerasAccessRefusal.featureLocked
        : CamerasAccessRefusal.forbidden;
  }
  return CamerasAccessRefusal.none;
}

/// Mur des caméras du tenant (GET /cameras).
final cameraWallProvider = FutureProvider<CameraWall>((ref) {
  return ref.watch(camerasRepositoryProvider).listCameras();
});

/// Stream token frais pour le direct (GET /cameras/{id}/stream-token).
/// autoDispose : le jeton est régénéré à chaque ouverture de l'écran direct
/// (durée de vie courte, jamais mis en cache).
final cameraStreamProvider =
    FutureProvider.autoDispose.family<CameraDevice, int>((ref, cameraId) {
  return ref.watch(camerasRepositoryProvider).streamToken(cameraId);
});

/// Filtre caméra du journal des événements (null = toutes les caméras).
final eventsCameraFilterProvider = StateProvider<int?>((ref) => null);

/// Journal des événements (GET /cameras/events, #7427).
final cameraEventsProvider =
    FutureProvider.autoDispose<List<CameraEvent>>((ref) {
  final cameraId = ref.watch(eventsCameraFilterProvider);
  return ref.watch(camerasRepositoryProvider).listEvents(cameraId: cameraId);
});

/// Alertes dédupliquées (GET /cameras/alerts, #7427).
final cameraAlertsProvider =
    FutureProvider.autoDispose<List<CameraAlert>>((ref) {
  return ref.watch(camerasRepositoryProvider).listAlerts();
});
