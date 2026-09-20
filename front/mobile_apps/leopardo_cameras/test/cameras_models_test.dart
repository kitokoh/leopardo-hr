import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_alert.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_device.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_event.dart';

void main() {
  group('CameraDevice (contrat Camera, api/openapi.yaml)', () {
    test('parse un payload complet', () {
      final camera = CameraDevice.fromJson(const {
        'id': 7,
        'name': 'Entrée principale',
        'location': 'Rez-de-chaussée',
        'is_active': true,
        'sort_order': 1,
        'thumbnail_url': 'https://api.example.test/storage/cam7.jpg',
        'stream_url': 'wss://proxy.leopardo-rh.com/cam/7/webrtc',
        'stream_token': 'jwt-signe',
        'token_expires_at': '2026-09-21T10:00:00Z',
        'created_at': '2026-09-01T08:00:00Z',
      });

      expect(camera.id, 7);
      expect(camera.name, 'Entrée principale');
      expect(camera.location, 'Rez-de-chaussée');
      expect(camera.isActive, isTrue);
      expect(camera.streamUrl, 'wss://proxy.leopardo-rh.com/cam/7/webrtc');
      expect(camera.tokenExpiresAt, '2026-09-21T10:00:00Z');
    });

    test('tolère un payload minimal (champs nullables)', () {
      final camera = CameraDevice.fromJson(const {'id': 1});

      expect(camera.id, 1);
      expect(camera.name, '');
      expect(camera.isActive, isFalse);
      expect(camera.streamUrl, isNull);
      expect(camera.streamToken, isNull);
    });

    test('le contrat n\'expose jamais de champ RTSP (AC 4, #7426)', () {
      // Garde documentaire : le modèle mobile n'a aucun champ rtsp_url —
      // l'API ne le sert pas et le mobile ne doit jamais le stocker.
      final camera = CameraDevice.fromJson(const {
        'id': 2,
        'name': 'Quai',
        // Un backend défaillant qui enverrait rtsp_url serait ignoré :
        'rtsp_url': 'rtsp://user:pass@10.0.0.9/stream',
      });

      expect(camera.streamUrl, isNull);
      expect(
        camera.toString().contains('rtsp'),
        isFalse,
        reason: 'aucune trace RTSP côté mobile',
      );
    });
  });

  group('CameraPlanLimit / CameraWall', () {
    test('parse plan_limit', () {
      final limit = CameraPlanLimit.fromJson(const {
        'max_cameras': 8,
        'current_count': 3,
      });
      expect(limit.maxCameras, 8);
      expect(limit.currentCount, 3);
    });

    test('plan illimité (max_cameras null)', () {
      final limit = CameraPlanLimit.fromJson(const {'current_count': 2});
      expect(limit.maxCameras, isNull);
      expect(limit.currentCount, 2);
    });
  });

  group('CameraEvent (contrat CameraEvent, #7427)', () {
    test('parse un événement complet', () {
      final event = CameraEvent.fromJson(const {
        'id': 41,
        'camera_id': 7,
        'camera_name': 'Entrée principale',
        'type': 'person',
        'severity': 'high',
        'detected_at': '2026-09-20T22:14:00Z',
        'has_snapshot': true,
        'created_at': '2026-09-20T22:14:03Z',
      });

      expect(event.id, 41);
      expect(event.cameraId, 7);
      expect(event.type, 'person');
      expect(event.severity, 'high');
      expect(event.hasSnapshot, isTrue);
    });

    test('replis sûrs sur payload minimal (RGPD : pas de chemin snapshot)',
        () {
      final event = CameraEvent.fromJson(const {'id': 1});

      expect(event.type, 'motion');
      expect(event.severity, 'info');
      expect(event.hasSnapshot, isFalse);
    });
  });

  group('CameraAlert (contrat CameraAlert, #7427)', () {
    test('parse une alerte ouverte avec événement lié (deep link AC 3)', () {
      final alert = CameraAlert.fromJson(const {
        'id': 9,
        'camera_id': 7,
        'camera_name': 'Entrée principale',
        'camera_event_id': 41,
        'type': 'motion',
        'severity': 'warning',
        'status': 'open',
        'created_at': '2026-09-20T22:14:05Z',
      });

      expect(alert.isOpen, isTrue);
      expect(alert.isResolved, isFalse);
      expect(alert.cameraEventId, 41);
    });

    test('cycle open -> acknowledged -> resolved', () {
      final acknowledged = CameraAlert.fromJson(const {
        'id': 9,
        'camera_id': 7,
        'type': 'motion',
        'severity': 'warning',
        'status': 'acknowledged',
        'acknowledged_at': '2026-09-20T22:20:00Z',
      });
      expect(acknowledged.isOpen, isFalse);
      expect(acknowledged.isResolved, isFalse);

      final resolved = CameraAlert.fromJson(const {
        'id': 9,
        'camera_id': 7,
        'type': 'motion',
        'severity': 'warning',
        'status': 'resolved',
        'resolved_at': '2026-09-20T23:00:00Z',
      });
      expect(resolved.isResolved, isTrue);
    });
  });
}
