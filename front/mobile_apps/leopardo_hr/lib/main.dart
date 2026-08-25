import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:leopardo_core/core/storage/secure_storage.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:leopardo_core/core/theme/app_colors.dart';
import 'package:leopardo_core/core/widgets/startup_gate.dart';
import 'package:sentry_flutter/sentry_flutter.dart';
import 'app.dart';
import 'package:leopardo_core/core/i18n/device_locale.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  FlutterError.onError = (details) {
    FlutterError.presentError(details);
  };
  ErrorWidget.builder = (details) => _StartupRuntimeError(details: details);

  // #2827 : Sentry initialisé comme les autres apps (employee/manager/platform admin),
  // non bloquant — le premier rendu passe par StartupGate dans appRunner.
  await SentryFlutter.init(
    (options) {
      options.dsn =
          const String.fromEnvironment('SENTRY_DSN', defaultValue: '');
      options.tracesSampleRate = 0.2; // #2766 : échantillonnage borné (PII)
    },
    appRunner: () => runApp(
      StartupGate(
        appName: 'Leopardo RH',
        initializer: _bootstrap,
        criticalInitializer: _bootstrapCritical,
        optionalInitializer: _safeGoogleSignInInitialize,
        child: const ProviderScope(child: LeopardoApp()),
      ),
    ),
  );
}

/// Ops critiques : JAMAIS soumises à un timeout.
Future<void> _bootstrapCritical() async {
  await _runStartupStep('Hive offline cache', _openOfflineCache);
  await _runStartupStep('Locale formatting', _initializeLocales);
}

/// Conservé pour compatibilité.
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
      serverClientId: const String.fromEnvironment(
        'GOOGLE_WEB_CLIENT_ID',
        // T095 (QA 2026-08-15) : l'ID n'est plus codé en dur — fourni par
        // --dart-define en build ; repli DEBUG uniquement (masqué en release).
        // #3294 : aucun repli en dur — l'ID doit venir de --dart-define
        // (GOOGLE_WEB_CLIENT_ID), sinon le sign-in Google est désactivé.
        defaultValue: '',
      ),
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
    SecureStorage.purgeLegacyHiveToken();
  } catch (error, stackTrace) {
    debugPrint('Hive offlineCache recovery after open failure: $error');
    debugPrintStack(stackTrace: stackTrace);
    await Hive.deleteBoxFromDisk('offlineCache');
    await Hive.openBox('offlineCache');
  }
}

Future<void> _initializeLocales() async {
  await initializeDateFormatting(deviceIntlDateLocale, null);
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
      color: AppColors.mobileDarkBg,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            'Erreur d affichage Leopardo RH\n${details.exceptionAsString()}',
            textAlign: TextAlign.center,
            style:
                const TextStyle(color: AppColors.mobileDarkText, fontSize: 13),
          ),
        ),
      ),
    );
  }
}
