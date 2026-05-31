import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';
import 'app.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
  };
  ErrorWidget.builder = (details) => _StartupRuntimeError(details: details);

  runApp(
    StartupGate(
      appName: 'Leopardo Employee',
      // initializer est requis par l'API mais n'est plus exécuté directement
      // quand criticalInitializer et optionalInitializer sont fournis.
      initializer: _bootstrap,
      criticalInitializer: _bootstrapCritical,
      optionalInitializer: _safeGoogleSignInInitialize,
      child: const ProviderScope(child: LeopardoApp()),
    ),
  );
}

/// Ops critiques : JAMAIS soumises à un timeout.
Future<void> _bootstrapCritical() async {
  await _runStartupStep('Hive offline cache', _openOfflineCache);
  await _runStartupStep('Locale formatting', _initializeLocales);
}

/// Conservé pour compatibilité (non utilisé quand criticalInitializer est fourni).
Future<void> _bootstrap() async {
  unawaited(_safeGoogleSignInInitialize());
  await _openOfflineCache();
  await _initializeLocales();
}

Future<void> _safeGoogleSignInInitialize() async {
  try {
    await GoogleSignIn.instance.initialize(
      // serverClientId est le web client id (type 3) — obligatoire pour que
      // authenticate() retourne un idToken vérifiable par le backend.
      serverClientId:
          '201283742683-3tad975gn325vvr3qpq85vcotsr0cplt.apps.googleusercontent.com',
    );
  } catch (error, stackTrace) {
    debugPrint('Google Sign-In init skipped: $error');
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
  await initializeDateFormatting('fr_CA', null);
  await initializeDateFormatting('fr_BE', null);
  await initializeDateFormatting('ar', null);
  await initializeDateFormatting('ar_SA', null);
  await initializeDateFormatting('ar_MA', null);
  await initializeDateFormatting('tr', null);
  await initializeDateFormatting('tr_TR', null);
  await initializeDateFormatting('en', null);
  await initializeDateFormatting('en_US', null);
  await initializeDateFormatting('en_GB', null);
}

Future<void> _runStartupStep(String label, Future<void> Function() step) async {
  try {
    await step();
  } catch (error, stackTrace) {
    debugPrint('Startup step skipped ($label): $error');
    debugPrintStack(stackTrace: stackTrace);
  }
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
            'Erreur d affichage Leopardo Employee\n${details.exceptionAsString()}',
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xFFE2EAF6), fontSize: 13),
          ),
        ),
      ),
    );
  }
}
