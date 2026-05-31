import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';

import 'src/platform_admin_app.dart';

@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
  };
  ErrorWidget.builder = (details) => _StartupRuntimeError(details: details);

  runApp(
    StartupGate(
      appName: 'Leopardo Platform Admin',
      initializer: _bootstrap,
      criticalInitializer: _bootstrap,
      // Pas d'optionalInitializer pour platform admin (pas de Google Sign-In).
      child: const ProviderScope(child: PlatformAdminApp()),
    ),
  );
}

Future<void> _bootstrap() async {
  await _initFirebase();
  await _openOfflineCache();
  await _initializeLocales();
}

Future<void> _initFirebase() async {
  try {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);
  } catch (error, stackTrace) {
    debugPrint('Firebase init skipped (platform_admin): $error');
    debugPrintStack(stackTrace: stackTrace);
  }
}

Future<void> _openOfflineCache() async {
  await Hive.initFlutter();
  try {
    await Hive.openBox('offlineCache');
  } catch (error, stackTrace) {
    debugPrint('Hive offlineCache recovery after open failure: $error');
    debugPrintStack(stackTrace: stackTrace);
    await Hive.deleteBoxFromDisk('offlineCache');
    await Hive.openBox('offlineCache');
  }
}

Future<void> _initializeLocales() async {
  await initializeDateFormatting('fr_FR', null);
  await initializeDateFormatting('ar', null);
  await initializeDateFormatting('tr_TR', null);
  await initializeDateFormatting('en_US', null);
}

class _StartupRuntimeError extends StatelessWidget {
  const _StartupRuntimeError({required this.details});

  final FlutterErrorDetails details;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: const Color(0xFF0B1120),
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            'Erreur d affichage Leopardo Platform Admin\n${details.exceptionAsString()}',
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xFFE2EAF6), fontSize: 13),
          ),
        ),
      ),
    );
  }
}
