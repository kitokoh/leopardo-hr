import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_cameras/core/providers/core_providers.dart';
import 'package:leopardo_cameras/features/cameras/models/camera_device.dart';
import 'package:leopardo_cameras/features/cameras/providers/cameras_providers.dart';
import 'package:leopardo_cameras/features/cameras/screens/cameras_home_screen.dart';
import 'package:leopardo_core/core/api/api_exceptions.dart';
import 'package:leopardo_core/core/storage/app_preferences.dart';

void main() {
  Widget buildHome(List<Override> overrides) {
    return ProviderScope(
      overrides: [
        appPreferencesProvider.overrideWithValue(AppPreferences()),
        ...overrides,
      ],
      child: const MaterialApp(home: CamerasHomeScreen()),
    );
  }

  group('CamerasHomeScreen (BC-19 DEVICE, #7426)', () {
    testWidgets('liste les caméras du tenant (AC 1)', (tester) async {
      await tester.pumpWidget(
        buildHome([
          cameraWallProvider.overrideWith(
            (ref) async => const CameraWall(
              cameras: [
                CameraDevice(id: 1, name: 'Entrée principale', isActive: true),
                CameraDevice(id: 2, name: 'Quai de chargement'),
              ],
            ),
          ),
        ]),
      );
      await tester.pumpAndSettle();

      expect(find.text('Leopardo Caméras'), findsOneWidget);
      expect(find.text('Mur des caméras'), findsOneWidget);
      expect(find.text('Entrée principale'), findsOneWidget);
      expect(find.text('Quai de chargement'), findsOneWidget);
      expect(find.text('Active'), findsOneWidget);
      expect(find.text('Inactive'), findsOneWidget);
    });

    testWidgets(
        'un tenant sans le flag cameras voit un refus explicite, pas un écran vide (AC 2)',
        (tester) async {
      await tester.pumpWidget(
        buildHome([
          cameraWallProvider.overrideWith(
            (ref) => Future<CameraWall>.error(
              ApiException(
                'Your plan does not include the cameras module.',
                statusCode: 403,
                code: 'FEATURE_NOT_ENABLED',
              ),
            ),
          ),
        ]),
      );
      await tester.pumpAndSettle();

      expect(find.text('Module Caméras non activé'), findsOneWidget);
    });

    testWidgets('un rôle non-manager voit un refus explicite (AC 2)',
        (tester) async {
      await tester.pumpWidget(
        buildHome([
          cameraWallProvider.overrideWith(
            (ref) => Future<CameraWall>.error(
              ApiException('Forbidden', statusCode: 403),
            ),
          ),
        ]),
      );
      await tester.pumpAndSettle();

      expect(find.text('Accès refusé'), findsOneWidget);
    });

    testWidgets('mur vide : message explicite, jamais un écran vide',
        (tester) async {
      await tester.pumpWidget(
        buildHome([
          cameraWallProvider.overrideWith(
            (ref) async => const CameraWall(),
          ),
        ]),
      );
      await tester.pumpAndSettle();

      expect(find.text('Aucune caméra enregistrée.'), findsOneWidget);
    });
  });
}
